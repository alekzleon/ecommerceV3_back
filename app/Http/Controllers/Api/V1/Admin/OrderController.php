<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashbackTransaction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
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
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
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
}
