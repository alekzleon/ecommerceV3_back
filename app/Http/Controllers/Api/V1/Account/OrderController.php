<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Services\SalesChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected SalesChannelService $salesChannelService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 12);
        $perPage = $perPage > 0 ? min($perPage, 50) : 12;
        $sortBy = $request->string('sort_by', 'latest')->toString();

        $query = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items as items_lines_count')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('orden_compra', 'like', "%{$search}%")
                        ->orWhere('stripe_session_id', 'like', "%{$search}%")
                        ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')->toString()))
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
            'message' => 'Pedidos del cliente obtenidos correctamente.',
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

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomerOrder($request, $order);

        $order->load([
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

    public function purchaseOrderPdf(Request $request, Order $order)
    {
        $this->authorizeCustomerOrder($request, $order);

        return app(AdminOrderController::class)->purchaseOrderPdf($order);
    }

    protected function authorizeCustomerOrder(Request $request, Order $order): void
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403, 'No tienes acceso a este pedido.');
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
            'status_label' => $this->statusLabel((string) $order->status),
            'payment_status' => $order->payment_status,
            'payment_status_label' => $this->paymentStatusLabel((string) $order->payment_status),
            'payment_method' => $order->payment_method,
            'currency' => strtolower($order->currency),
            'items_count' => $order->items_count,
            'items_lines_count' => $order->items_lines_count ?? $order->items()->count(),
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount,
            'tax' => (float) $order->tax,
            'shipping' => (float) $order->shipping,
            'total' => (float) $order->total,
            'document_notes' => $order->document_notes,
            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
            'links' => [
                'detail' => "/api/v1/account/orders/{$order->id}",
                'purchase_order_pdf' => "/api/v1/account/orders/{$order->id}/purchase-order.pdf",
            ],
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
            'cashback_transactions' => $this->cashbackTransactionsPayload($order),
            'tax_breakdown' => data_get($order->metadata, 'tax_breakdown', []),
            'shipping_address' => $order->shipping_address_snapshot,
            'items' => $order->items->map(fn ($item) => $this->orderItemPayload($item))->values(),
            'payments' => $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
                'currency' => strtolower($payment->currency),
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
            ])->values(),
            'updated_at' => $order->updated_at,
        ];
    }

    protected function orderItemPayload($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->sku_snapshot,
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
            'accounting' => data_get($item->metadata, 'accounting'),
            'breakdown' => data_get($item->metadata, 'breakdown'),
        ];
    }

    protected function cashbackTransactionsPayload(Order $order): array
    {
        return CashbackTransaction::query()
            ->where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->latest('id')
            ->get()
            ->map(fn (CashbackTransaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
            ])
            ->values()
            ->all();
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PAID => 'Pagado',
            Order::STATUS_PAYMENT_FAILED => 'Pago fallido',
            Order::STATUS_CANCELLED => 'Cancelado',
            default => 'Pendiente de pago',
        };
    }

    protected function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            Order::PAYMENT_PAID => 'Pagado',
            Order::PAYMENT_FAILED => 'Fallido',
            default => 'Pendiente',
        };
    }
}
