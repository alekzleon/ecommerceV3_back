<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashbackTransaction;
use App\Models\EcommerceSetting;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\Pdf\SimplePdf;
use App\Services\SalesChannelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(protected SalesChannelService $salesChannelService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $sortBy = $request->string('sort_by', 'latest')->toString();

        $query = Order::query()
            ->with(['user:id,name,email,username'])
            ->withCount('items as items_lines_count')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('stripe_session_id', 'like', "%{$search}%")
                        ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('user_id', (int) $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')));

        match ($sortBy) {
            'oldest' => $query->orderBy('id'),
            'total_asc' => $query->orderBy('total'),
            'total_desc' => $query->orderByDesc('total'),
            'paid_at_desc' => $query->orderByDesc('paid_at'),
            default => $query->orderByDesc('id'),
        };

        $orders = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Pedidos obtenidos correctamente.',
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->orderSummaryPayload($order)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort(405);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user:id,name,email,username',
            'items.product:id,name,sku,slug,image_path',
            'payments' => fn ($query) => $query->latest('id'),
            'cart:id,status,converted_at,order_id',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Pedido obtenido correctamente.',
            'data' => $this->orderDetailPayload($order),
        ]);
    }

    public function purchaseOrderPdf(Order $order)
    {
        $order->load([
            'user:id,name,email,username',
            'items.product:id,name,sku,slug,image_path',
            'payments' => fn ($query) => $query->latest('id'),
            'cart:id,status,converted_at,order_id',
        ]);

        $pdf = app(SimplePdf::class)->fromPages($this->purchaseOrderPdfPages($order));
        $filename = 'orden-compra-' . ($order->number ?: $order->id) . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in([
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_PAID,
                Order::STATUS_PAYMENT_FAILED,
                Order::STATUS_CANCELLED,
            ])],
            'payment_status' => ['sometimes', 'string', Rule::in([
                Order::PAYMENT_PENDING,
                Order::PAYMENT_PAID,
                Order::PAYMENT_FAILED,
            ])],
            'sales_channel' => ['sometimes', 'nullable', 'string', Rule::in(SalesChannelService::ALLOWED_CHANNELS)],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'document_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        if (($validated['status'] ?? null) === Order::STATUS_PAID) {
            $validated['payment_status'] = Order::PAYMENT_PAID;
            $validated['paid_at'] = $validated['paid_at'] ?? $order->paid_at ?? now();
        }

        if (($validated['payment_status'] ?? null) === Order::PAYMENT_PAID) {
            $validated['status'] = Order::STATUS_PAID;
            $validated['paid_at'] = $validated['paid_at'] ?? $order->paid_at ?? now();
        }

        if (($validated['status'] ?? null) === Order::STATUS_CANCELLED && $order->payment_status === Order::PAYMENT_PAID) {
            return response()->json([
                'ok' => false,
                'message' => 'No se puede cancelar desde este endpoint un pedido ya pagado.',
            ], 422);
        }

        if (array_key_exists('metadata', $validated)) {
            $validated['metadata'] = array_merge($order->metadata ?? [], $validated['metadata'] ?? []);
        }

        if (array_key_exists('document_notes', $validated)) {
            $validated['document_notes'] = $this->normalizeDocumentNotes($validated['document_notes']);
            $validated['metadata'] = array_merge($order->metadata ?? [], $validated['metadata'] ?? [], [
                'document_notes' => $validated['document_notes'],
            ]);
        }

        if (array_key_exists('sales_channel', $validated)) {
            $validated['sales_channel'] = $validated['sales_channel'] ?: SalesChannelService::DEFAULT_CHANNEL;
            $validated['metadata'] = array_merge($order->metadata ?? [], $validated['metadata'] ?? [], [
                'sales_channel' => $validated['sales_channel'],
            ]);
        }

        $order->update($validated);

        if ($order->payment_status === Order::PAYMENT_PAID) {
            $this->updateCashbackTransactions($order, CashbackTransaction::STATUS_AVAILABLE);
        } elseif (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_PAYMENT_FAILED], true)) {
            $this->updateCashbackTransactions($order, CashbackTransaction::STATUS_CANCELLED);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Pedido actualizado correctamente.',
            'data' => $this->orderDetailPayload($order->fresh(['user', 'items.product', 'payments', 'cart'])),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        if ($order->payment_status === Order::PAYMENT_PAID) {
            return response()->json([
                'ok' => false,
                'message' => 'No se puede eliminar un pedido pagado.',
            ], 422);
        }

        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'metadata' => array_merge($order->metadata ?? [], [
                'cancelled_by_admin' => true,
                'cancelled_at' => now()->toISOString(),
            ]),
        ]);

        $this->updateCashbackTransactions($order, CashbackTransaction::STATUS_CANCELLED);

        return response()->json([
            'ok' => true,
            'message' => 'Pedido cancelado correctamente.',
            'data' => $this->orderDetailPayload($order->fresh(['user', 'items.product', 'payments', 'cart'])),
        ]);
    }

    protected function orderSummaryPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'orden_compra' => $order->orden_compra,
            'sales_channel' => $order->sales_channel ?: SalesChannelService::DEFAULT_CHANNEL,
            'sales_channel_label' => $this->salesChannelService->label($order->sales_channel),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'customer' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'username' => $order->user->username,
            ] : null,
            'currency' => strtolower($order->currency),
            'items_count' => $order->items_count,
            'items_lines_count' => $order->items_lines_count ?? $order->items()->count(),
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount,
            'tax' => (float) $order->tax,
            'shipping' => (float) $order->shipping,
            'total' => (float) $order->total,
            'paid_at' => $order->paid_at,
            'document_notes' => $order->document_notes,
            'created_at' => $order->created_at,
        ];
    }

    protected function orderDetailPayload(Order $order): array
    {
        return [
            ...$this->orderSummaryPayload($order),
            'cart' => $order->cart ? [
                'id' => $order->cart->id,
                'status' => $order->cart->status,
                'converted_at' => $order->cart->converted_at,
            ] : null,
            'stripe' => [
                'session_id' => $order->stripe_session_id,
                'payment_intent_id' => $order->stripe_payment_intent_id,
            ],
            'promotions_applied' => data_get($order->metadata, 'promotions_applied', []),
            'coupon' => data_get($order->metadata, 'coupon'),
            'loyalty' => data_get($order->metadata, 'loyalty', []),
            'cashback' => data_get($order->metadata, 'loyalty.cashback', []),
            'savings' => [
                'order_discount' => (float) $order->discount,
                'coupon_discount' => (float) data_get($order->metadata, 'coupon.discount_amount', 0),
                'first_purchase_discount' => (float) data_get($order->metadata, 'loyalty.first_purchase_discount.amount', 0),
                'cashback_used' => (float) data_get($order->metadata, 'loyalty.cashback.applied_amount', 0),
                'cashback_earned' => (float) data_get($order->metadata, 'loyalty.cashback.earn.amount', 0),
            ],
            'tax_breakdown' => data_get($order->metadata, 'tax_breakdown', []),
            'shipping_address' => $order->shipping_address_snapshot,
            'items' => $order->items->map(fn ($item) => $this->orderItemPayload($item))->values(),
            'payments' => $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'stripe_session_id' => $payment->stripe_session_id,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount' => (float) $payment->amount,
                'currency' => strtolower($payment->currency),
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
            ])->values(),
            'metadata' => $order->metadata,
            'updated_at' => $order->updated_at,
        ];
    }

    protected function orderItemPayload($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->sku_snapshot,
            'clave_articulo_id' => $item->clave_articulo_id_snapshot,
            'clave_articulo' => $item->clave_articulo_snapshot,
            'rol_clave_art_id' => $item->rol_clave_art_id_snapshot,
            'contenido_empaque' => $item->contenido_empaque_snapshot !== null
                ? (float) $item->contenido_empaque_snapshot
                : null,
            'name' => $item->name_snapshot,
            'brand' => $item->brand_snapshot,
            'image' => $item->image_snapshot,
            'selected_attribute_value_ids' => data_get($item->metadata, 'selected_attribute_value_ids', []),
            'selected_attributes' => data_get($item->metadata, 'selected_attributes', []),
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount' => (float) $item->discount,
            'line_total' => (float) $item->line_total,
            'regular_units' => (float) data_get($item->metadata, 'regular_units', max(0, (float) $item->quantity - (float) data_get($item->metadata, 'gift_units', 0))),
            'regular_line_total' => (float) data_get($item->metadata, 'regular_line_total', 0),
            'gift_units' => (float) data_get($item->metadata, 'gift_units', data_get($item->promotion_snapshot, 'gift_units', 0)),
            'gift_item_units' => (float) data_get($item->metadata, 'gift_item_units', data_get($item->promotion_snapshot, 'gift_item_units', 0)),
            'gift_items' => data_get($item->metadata, 'gift_items', data_get($item->promotion_snapshot, 'gift_items', [])),
            'gift_unit_accounting_price' => data_get($item->metadata, 'gift_unit_accounting_price', data_get($item->promotion_snapshot, 'gift_unit_accounting_price')),
            'gift_line_total' => (float) data_get($item->metadata, 'gift_line_total', data_get($item->promotion_snapshot, 'gift_line_total', 0)),
            'base_subtotal' => (float) data_get($item->metadata, 'base_subtotal', 0),
            'promotion' => $item->promotion_id ? [
                'id' => $item->promotion_id,
                'name' => $item->promotion_name_snapshot,
                'snapshot' => $item->promotion_snapshot,
            ] : null,
            'accounting' => data_get($item->metadata, 'accounting', [
                'requires_gift_minimum_price' => (float) data_get($item->metadata, 'gift_units', data_get($item->promotion_snapshot, 'gift_units', 0)) > 0,
                'gift_unit_price' => data_get($item->metadata, 'gift_unit_accounting_price', data_get($item->promotion_snapshot, 'gift_unit_accounting_price')),
                'gift_line_total' => (float) data_get($item->metadata, 'gift_line_total', data_get($item->promotion_snapshot, 'gift_line_total', 0)),
                'note' => (float) data_get($item->metadata, 'gift_units', data_get($item->promotion_snapshot, 'gift_units', 0)) > 0
                    ? 'Las unidades de regalo se facturan a $0.10 por unidad.'
                    : null,
            ]),
            'breakdown' => data_get($item->metadata, 'breakdown'),
            'metadata' => $item->metadata,
        ];
    }

    protected function updateCashbackTransactions(Order $order, string $status): void
    {
        CashbackTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', CashbackTransaction::STATUS_PENDING)
            ->update(['status' => $status]);
    }

    protected function purchaseOrderPdfPages(Order $order): array
    {
        $coupon = data_get($order->metadata, 'coupon');
        $loyalty = data_get($order->metadata, 'loyalty', []);
        $cashback = data_get($loyalty, 'cashback', []);
        $address = $order->shipping_address_snapshot ?? [];
        $currency = strtoupper((string) ($order->currency ?: config('app.currency', 'MXN')));
        $pages = [];
        $page = $this->purchaseOrderBasePage($order, 1);

        $this->drawSummaryCard($page, 40, 612, 254, 74, 'Cliente', [
            $order->user?->name ?: 'Cliente invitado',
            $order->user?->email ?: 'Sin correo',
        ]);

        $this->drawSummaryCard($page, 318, 612, 254, 74, 'Envio', [
            $this->shortText($this->addressLine($address), 74),
            trim(collect([data_get($address, 'contact_name'), data_get($address, 'phone')])->filter()->implode(' | ')) ?: 'Sin contacto adicional',
        ]);

        $this->drawSummaryCard($page, 40, 522, 254, 62, 'Pago', [
            $this->paymentStatusLabel((string) $order->payment_status) . ' | ' . ($order->payment_method ?: 'Sin metodo'),
            'Pagado: ' . (optional($order->paid_at)->format('Y-m-d H:i') ?: 'No registrado'),
        ]);

        $this->drawSummaryCard($page, 318, 522, 254, 62, 'Documento', [
            'Pedido: ' . ($order->number ?: '#' . $order->id),
            'Orden de compra: ' . ($order->orden_compra ?: 'N/A'),
        ]);

        $y = 474;
        $this->drawProductsHeader($page, $y);
        $y -= 38;

        foreach ($order->items->values() as $index => $item) {
            if ($y < 126) {
                $pages[] = $this->finishPurchaseOrderPage($page, count($pages) + 1);
                $page = $this->purchaseOrderBasePage($order, count($pages) + 1);
                $y = 650;
                $this->drawProductsHeader($page, $y);
                $y -= 38;
            }

            $this->drawProductRow($page, $item, $index + 1, $y, $currency);
            $y -= 58;
        }

        $needsDocumentNotes = filled($order->document_notes);
        $minimumYForSummary = $needsDocumentNotes ? 348 : 258;

        if ($y < $minimumYForSummary) {
            $pages[] = $this->finishPurchaseOrderPage($page, count($pages) + 1);
            $page = $this->purchaseOrderBasePage($order, count($pages) + 1);
            $y = 650;
        }

        $this->drawBenefitsCard($page, 40, $y - 98, 254, 98, $coupon, $loyalty, $cashback, $currency);
        $this->drawTotalsCard($page, 318, $y - 126, 254, 126, $order, $currency);

        if ($needsDocumentNotes) {
            $this->drawDocumentNotesCard($page, 40, $y - 206, 532, 62, (string) $order->document_notes);
        }

        $pages[] = $this->finishPurchaseOrderPage($page, count($pages) + 1);

        return $pages;
    }

    protected function purchaseOrderBasePage(Order $order, int $pageNumber): array
    {
        $brand = $this->brandTitle();
        $initials = $this->brandInitials($brand);
        $logo = $this->logoImageElement(58, 719, 112, 28);

        $page = [
            ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 612, 'h' => 792, 'color' => '#f8fafc'],
            ['type' => 'rect', 'x' => 40, 'y' => 704, 'w' => 532, 'h' => 58, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#e5e7eb'],
            ['type' => 'text', 'x' => 398, 'y' => 738, 'size' => 18, 'text' => 'ORDEN DE COMPRA'],
            ['type' => 'text', 'x' => 404, 'y' => 720, 'size' => 9, 'text' => 'Fecha: ' . optional($order->created_at)->format('Y-m-d H:i')],
            ['type' => 'line', 'x1' => 40, 'y1' => 694, 'x2' => 572, 'y2' => 694, 'color' => '#dbe4ee', 'width' => 1],
        ];

        if ($logo) {
            array_splice($page, 2, 0, [$logo]);
            $page[] = ['type' => 'text', 'x' => 58, 'y' => 710, 'size' => 8, 'text' => $brand];
        } else {
            array_splice($page, 2, 0, [
                ['type' => 'rect', 'x' => 58, 'y' => 718, 'w' => 30, 'h' => 30, 'color' => '#13426b'],
                ['type' => 'text', 'x' => 66, 'y' => 728, 'size' => 11, 'text' => $initials, 'color' => '#ffffff'],
                ['type' => 'text', 'x' => 98, 'y' => 738, 'size' => 13, 'text' => $brand],
                ['type' => 'text', 'x' => 98, 'y' => 722, 'size' => 9, 'text' => 'Orden de compra ecommerce'],
            ]);
        }

        return $page;
    }

    protected function finishPurchaseOrderPage(array $page, int $pageNumber): array
    {
        $page[] = ['type' => 'line', 'x1' => 40, 'y1' => 44, 'x2' => 572, 'y2' => 44, 'color' => '#e5e7eb', 'width' => 1];
        $page[] = ['type' => 'text', 'x' => 40, 'y' => 26, 'size' => 8, 'text' => 'Documento generado automaticamente por el ecommerce.'];
        $page[] = ['type' => 'text', 'x' => 526, 'y' => 26, 'size' => 8, 'text' => 'Pag. ' . $pageNumber];

        return $page;
    }

    protected function drawSummaryCard(array &$page, float $x, float $y, float $w, float $h, string $title, array $lines): void
    {
        $page[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#e5e7eb'];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 20, 'size' => 9, 'text' => strtoupper($title)];

        foreach (array_values($lines) as $index => $line) {
            $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 38 - ($index * 14), 'size' => $index === 0 ? 10 : 8, 'text' => $this->shortText((string) $line, 78)];
        }
    }

    protected function drawProductsHeader(array &$page, float $y): void
    {
        $page[] = ['type' => 'text', 'x' => 40, 'y' => $y + 20, 'size' => 13, 'text' => 'Productos'];
        $page[] = ['type' => 'rect', 'x' => 40, 'y' => $y - 10, 'w' => 532, 'h' => 24, 'color' => '#13426b'];
        $page[] = ['type' => 'text', 'x' => 52, 'y' => $y - 2, 'size' => 8, 'text' => 'Producto', 'color' => '#ffffff'];
        $page[] = ['type' => 'text', 'x' => 306, 'y' => $y - 2, 'size' => 8, 'text' => 'Cant.', 'color' => '#ffffff'];
        $page[] = ['type' => 'text', 'x' => 358, 'y' => $y - 2, 'size' => 8, 'text' => 'Precio', 'color' => '#ffffff'];
        $page[] = ['type' => 'text', 'x' => 430, 'y' => $y - 2, 'size' => 8, 'text' => 'Desc.', 'color' => '#ffffff'];
        $page[] = ['type' => 'text', 'x' => 500, 'y' => $y - 2, 'size' => 8, 'text' => 'Total', 'color' => '#ffffff'];
    }

    protected function drawProductRow(array &$page, $item, int $index, float $y, string $currency): void
    {
        $attributes = $this->selectedAttributesText(data_get($item->metadata, 'selected_attributes', []));
        $promotion = $item->promotion_name_snapshot ? 'Promo: ' . $item->promotion_name_snapshot : '';

        $page[] = ['type' => 'rect', 'x' => 40, 'y' => $y - 28, 'w' => 532, 'h' => 52, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#eef2f7'];
        $page[] = ['type' => 'text', 'x' => 52, 'y' => $y + 7, 'size' => 9, 'text' => $index . '. ' . $this->shortText((string) $item->name_snapshot, 46)];
        $page[] = ['type' => 'text', 'x' => 52, 'y' => $y - 7, 'size' => 8, 'text' => 'SKU: ' . ($item->sku_snapshot ?: 'N/A')];
        $page[] = ['type' => 'text', 'x' => 52, 'y' => $y - 20, 'size' => 7, 'text' => $this->shortText(trim($attributes . ($promotion ? ' | ' . $promotion : '')), 68)];
        $page[] = ['type' => 'text', 'x' => 306, 'y' => $y - 1, 'size' => 9, 'text' => number_format((float) $item->quantity, 2)];
        $page[] = ['type' => 'text', 'x' => 358, 'y' => $y - 1, 'size' => 9, 'text' => $this->money((float) $item->unit_price, $currency)];
        $page[] = ['type' => 'text', 'x' => 430, 'y' => $y - 1, 'size' => 9, 'text' => $this->money((float) $item->discount, $currency)];
        $page[] = ['type' => 'text', 'x' => 500, 'y' => $y - 1, 'size' => 9, 'text' => $this->money((float) $item->line_total, $currency)];
    }

    protected function drawBenefitsCard(array &$page, float $x, float $y, float $w, float $h, mixed $coupon, array $loyalty, array $cashback, string $currency): void
    {
        $page[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#e5e7eb'];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 20, 'size' => 11, 'text' => 'Ahorros y beneficios'];

        $lines = [
            'Cupon: ' . (data_get($coupon, 'code') ?: 'N/A') . ' | ' . $this->money((float) data_get($coupon, 'discount_amount', 0), $currency),
            'Primera compra: ' . $this->money((float) data_get($loyalty, 'first_purchase_discount.amount', 0), $currency),
            'Cashback usado: ' . $this->money((float) data_get($cashback, 'applied_amount', 0), $currency),
            'Cashback ganado: ' . $this->money((float) data_get($cashback, 'earn.amount', 0), $currency),
        ];

        foreach ($lines as $index => $line) {
            $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 40 - ($index * 14), 'size' => 8, 'text' => $line];
        }
    }

    protected function drawTotalsCard(array &$page, float $x, float $y, float $w, float $h, Order $order, string $currency): void
    {
        $page[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#e5e7eb'];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 20, 'size' => 11, 'text' => 'Totales'];

        $lines = [
            ['Subtotal', (float) $order->subtotal],
            ['Descuento', (float) $order->discount],
            ['Impuestos', (float) $order->tax],
            ['Envio', (float) $order->shipping],
        ];

        foreach ($lines as $index => [$label, $amount]) {
            $lineY = $y + $h - 42 - ($index * 15);
            $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $lineY, 'size' => 9, 'text' => $label];
            $page[] = ['type' => 'text', 'x' => $x + 164, 'y' => $lineY, 'size' => 9, 'text' => $this->money($amount, $currency)];
        }

        $page[] = ['type' => 'line', 'x1' => $x + 14, 'y1' => $y + 34, 'x2' => $x + $w - 14, 'y2' => $y + 34, 'color' => '#e5e7eb', 'width' => 1];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + 16, 'size' => 12, 'text' => 'Total'];
        $page[] = ['type' => 'text', 'x' => $x + 164, 'y' => $y + 16, 'size' => 12, 'text' => $this->money((float) $order->total, $currency)];
    }

    protected function drawDocumentNotesCard(array &$page, float $x, float $y, float $w, float $h, string $notes): void
    {
        $page[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => '#ffffff', 'stroke' => true, 'stroke_color' => '#e5e7eb'];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 20, 'size' => 11, 'text' => 'Notas del documento'];
        $page[] = ['type' => 'text', 'x' => $x + 14, 'y' => $y + $h - 40, 'size' => 8, 'text' => $this->shortText($notes, 132)];
    }

    protected function brandTitle(): string
    {
        $navTitle = EcommerceSetting::getValue(EcommerceSetting::KEY_NAV_TITLE, []);
        $siteTitle = SiteSetting::current()->site_title;

        return trim((string) (data_get($navTitle, 'title') ?: $siteTitle ?: config('app.name', 'Ecommerce')));
    }

    protected function brandInitials(string $brand): string
    {
        $words = collect(explode(' ', preg_replace('/\s+/', ' ', trim($brand))))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_substr((string) $word, 0, 1))
            ->implode('');

        return mb_strtoupper($words ?: 'EC');
    }

    protected function logoImageElement(float $x, float $y, float $maxWidth, float $maxHeight): ?array
    {
        $settings = SiteSetting::current();

        if (! $settings->logo_path || ! Storage::disk($settings->logo_disk ?: 'public')->exists($settings->logo_path)) {
            return null;
        }

        $path = Storage::disk($settings->logo_disk ?: 'public')->path($settings->logo_path);
        $contents = @file_get_contents($path);

        if (! $contents || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return null;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        if (! $jpeg) {
            return null;
        }

        return [
            'type' => 'image',
            'x' => $x,
            'y' => $y + (($maxHeight - $targetHeight) / 2),
            'w' => $targetWidth,
            'h' => $targetHeight,
            'image_width' => $targetWidth,
            'image_height' => $targetHeight,
            'data' => $jpeg,
        ];
    }

    protected function shortText(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(0, $length - 3))) . '...';
    }

    protected function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            Order::PAYMENT_PAID => 'Pagado',
            Order::PAYMENT_FAILED => 'Fallido',
            default => 'Pendiente',
        };
    }

    protected function selectedAttributesText(array $selectedAttributes): string
    {
        return collect($selectedAttributes)
            ->map(fn ($selected) => trim((string) data_get($selected, 'attribute')) . ': ' . trim((string) data_get($selected, 'value')))
            ->filter(fn ($text) => $text !== ':')
            ->implode(' / ');
    }

    protected function normalizeDocumentNotes(?string $documentNotes): ?string
    {
        $documentNotes = trim(preg_replace('/\s+/', ' ', (string) $documentNotes));

        return $documentNotes !== '' ? $documentNotes : null;
    }

    protected function addressLine(array $address): string
    {
        $parts = [
            data_get($address, 'street'),
            data_get($address, 'external_number'),
            data_get($address, 'internal_number'),
            data_get($address, 'neighborhood'),
            data_get($address, 'city'),
            data_get($address, 'state'),
            data_get($address, 'zip_code'),
        ];

        $line = collect($parts)->filter(fn ($part) => filled($part))->implode(', ');

        return $line ?: (data_get($address, 'full_address') ?: 'N/A');
    }

    protected function money(float $amount, ?string $currency = null): string
    {
        return '$' . number_format($amount, 2) . ' ' . strtoupper((string) ($currency ?: config('app.currency', 'MXN')));
    }
}
