<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cart\CartResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\Checkout\CheckoutPreviewService;
use App\Services\Orders\OrderService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutPreviewService $checkoutPreviewService,
        protected OrderService $orderService,
        protected StripePaymentService $stripePaymentService
    ) {
    }

    public function preview(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());

        if ($recoverableOrder) {
            return response()->json([
                'ok' => true,
                'message' => 'Hay un carrito pendiente de recuperar antes de continuar checkout.',
                'data' => [
                    'can_checkout' => false,
                    'cart' => null,
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ]);
        }

        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        $cart = $this->cartService->recalculateCart($cart);

        $validated = $request->validate([
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $preview = $this->checkoutPreviewService->build($cart, $validated['dir_cli_id'] ?? null);

        return response()->json([
            'ok' => true,
            'message' => 'Checkout calculado correctamente.',
            'data' => $preview,
        ]);
    }

    public function validateCart(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());

        if ($recoverableOrder) {
            return response()->json([
                'ok' => false,
                'message' => 'Hay un carrito pendiente de recuperar antes de continuar checkout.',
                'data' => [
                    'can_checkout' => false,
                    'blockers' => [
                        [
                            'code' => 'recoverable_pending_order',
                            'message' => 'Recupera tu carrito pendiente antes de continuar.',
                        ],
                    ],
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ], 409);
        }

        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        $cart = $this->cartService->recalculateCart($cart);

        $validated = $request->validate([
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $preview = $this->checkoutPreviewService->build($cart, $validated['dir_cli_id'] ?? null);

        return response()->json([
            'ok' => $preview['can_checkout'],
            'message' => $preview['can_checkout']
                ? 'El carrito está listo para checkout.'
                : 'El carrito tiene detalles por resolver antes del checkout.',
            'data' => [
                'cart_id' => $preview['cart_id'],
                'can_checkout' => $preview['can_checkout'],
                'blockers' => $preview['blockers'],
                'shipping' => $preview['shipping'],
                'totals' => $preview['totals'],
            ],
        ], $preview['can_checkout'] ? 200 : 422);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $this->orderService->createPendingFromActiveCart(
            $request->user(),
            $validated['dir_cli_id'] ?? null
        );

        return response()->json([
            'ok' => true,
            'message' => 'Pedido creado correctamente.',
            'data' => $this->orderPayload($order),
        ], 201);
    }

    public function createStripeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::query()
            ->with(['items', 'user'])
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');

        $session = $this->stripePaymentService->createCheckoutSession($order);

        return response()->json([
            'ok' => true,
            'message' => 'Sesión de Stripe creada correctamente.',
            'data' => $session,
        ]);
    }

    public function confirmStripeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $order = $this->stripePaymentService->syncCheckoutSession($validated['session_id']);

        abort_unless($order, 404, 'No se encontró un pedido para esta sesión de Stripe.');
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403, 'No tienes acceso a este pedido.');

        return response()->json([
            'ok' => true,
            'message' => $order->payment_status === Order::PAYMENT_PAID
                ? 'Pago confirmado correctamente.'
                : 'La sesión de Stripe todavía no aparece como pagada.',
            'data' => $this->orderPayload($order),
        ]);
    }

    public function showOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403, 'No tienes acceso a este pedido.');

        return response()->json([
            'ok' => true,
            'message' => 'Pedido obtenido correctamente.',
            'data' => $this->orderPayload($order),
        ]);
    }

    public function restoreCartFromOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403, 'No tienes acceso a este pedido.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:80'],
        ]);

        $order = $this->refreshStripeOrderBeforeRestore($order);

        abort_unless($order->isPendingPayment(), 422, 'Este pedido ya no se puede recuperar porque no está pendiente de pago.');

        $this->stripePaymentService->expireCheckoutSession($order);

        $cart = $this->orderService->restoreCartFromPendingOrder(
            order: $order,
            user: $request->user(),
            reason: $validated['reason'] ?? 'payment_cancelled'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Carrito recuperado correctamente.',
            'data' => [
                'cart' => new CartResource($cart),
                'restored_from_order_id' => $order->id,
            ],
        ]);
    }

    public function restoreRecoverableOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'reason' => ['nullable', 'string', 'max:80'],
        ]);

        $order = $this->orderService->findRecoverablePendingOrder(
            user: $request->user(),
            orderId: $validated['order_id'] ?? null
        );

        abort_unless($order, 404, 'No hay un pedido pendiente recuperable.');

        $order = $this->refreshStripeOrderBeforeRestore($order);

        abort_unless($order->isPendingPayment(), 422, 'Este pedido ya no se puede recuperar porque no está pendiente de pago.');

        $this->stripePaymentService->expireCheckoutSession($order);

        $cart = $this->orderService->restoreCartFromPendingOrder(
            order: $order,
            user: $request->user(),
            reason: $validated['reason'] ?? 'recoverable_order_accepted'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Carrito recuperado correctamente.',
            'data' => [
                'cart' => new CartResource($cart),
                'restored_from_order_id' => $order->id,
                'order_deleted' => true,
            ],
        ]);
    }

    protected function orderPayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'id' => $order->id,
            'number' => $order->number,
            'orden_compra' => $order->orden_compra,
            'folio_microsip' => $order->folio_microsip,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'currency' => strtolower($order->currency),
            'items_count' => $order->items_count,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount,
            'tax' => (float) $order->tax,
            'shipping' => (float) $order->shipping,
            'total' => (float) $order->total,
            'stripe_session_id' => $order->stripe_session_id,
            'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
            'paid_at' => $order->paid_at,
            'promotions_applied' => data_get($order->metadata, 'promotions_applied', []),
            'coupon' => data_get($order->metadata, 'coupon'),
            'tax_breakdown' => data_get($order->metadata, 'tax_breakdown', []),
            'shipping_address' => $order->shipping_address_snapshot,
            'items' => $order->items->map(fn ($item) => [
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
                'price_info' => data_get($item->metadata, 'price_info'),
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
                    'type' => $item->promotion_type,
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
            ])->values(),
            'created_at' => $order->created_at,
        ];
    }

    protected function refreshStripeOrderBeforeRestore(Order $order): Order
    {
        if (blank($order->stripe_session_id) || $order->payment_status === Order::PAYMENT_PAID) {
            return $order;
        }

        return $this->stripePaymentService->syncCheckoutSession($order->stripe_session_id) ?? $order;
    }
}
