<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cart\CartResource;
use App\Models\Cart;
use App\Models\EcommerceSetting;
use App\Models\Order;
use App\Services\CartService;
use App\Services\Checkout\CheckoutPreviewService;
use App\Services\Orders\OrderService;
use App\Services\Payments\MercadoPagoPaymentReconciler;
use App\Services\Payments\StripePaymentService;
use App\Services\SalesChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutPreviewService $checkoutPreviewService,
        protected OrderService $orderService,
        protected StripePaymentService $stripePaymentService,
        protected MercadoPagoPaymentReconciler $mercadoPagoReconciler,
        protected SalesChannelService $salesChannelService
    ) {
    }

    public function preview(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());

        if ($recoverableOrder) {
            return response()->json([
                'ok' => true,
                'message' => 'Hay un pedido pendiente de pago. Puedes reintentar el pago o recuperar tu carrito.',
                'data' => [
                    'can_checkout' => false,
                    'cart' => null,
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ]);
        }

        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        $cart = $this->salesChannelService->applyToCart(
            $cart,
            $this->salesChannelService->fromRequest($request),
            $this->salesChannelService->trackingFromRequest($request)
        );
        $cart = $this->cartService->recalculateCart($cart);

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer', 'min:1'],
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
            'sales_channel' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ]);

        $preview = $this->checkoutPreviewService->build(
            $cart,
            $validated['address_id'] ?? null,
            $validated['dir_cli_id'] ?? null
        );

        return response()->json([
            'ok' => true,
            'message' => 'Checkout calculado correctamente.',
            'data' => $preview,
        ]);
    }

    public function guestPreview(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $guestToken = $this->guestTokenFromRequest($request);
        $recoverableOrder = $guestToken
            ? $this->orderService->findRecoverableGuestPendingOrder($guestToken)
            : null;

        if ($recoverableOrder) {
            return response()->json([
                'ok' => false,
                'message' => 'Hay un pedido invitado pendiente de pago. Puedes reintentar el pago o recuperar tu carrito.',
                'data' => [
                    'can_checkout' => false,
                    'cart' => null,
                    'blockers' => [
                        [
                            'code' => 'recoverable_pending_order',
                            'message' => 'Reintenta el pago del pedido pendiente o recupera tu carrito.',
                        ],
                    ],
                    'recoverable_order' => $this->orderService->guestRecoverableOrderPayload($recoverableOrder),
                ],
            ]);
        }

        $guest = $this->guestPayloadFromRequest($request, requireGuestData: false);
        $cart = $this->cartService->getOrCreateGuestCart($guestToken);
        $cart = $this->salesChannelService->applyToCart(
            $cart,
            $this->salesChannelService->fromRequest($request),
            $this->salesChannelService->trackingFromRequest($request)
        );
        $cart = $this->syncGuestMetadata($cart, $guest);
        $cart = $this->cartService->recalculateCart($cart);

        $preview = $this->checkoutPreviewService->build(
            $cart,
            null,
            null,
            data_get($guest, 'shipping_address')
        );

        return response()->json([
            'ok' => true,
            'message' => 'Checkout invitado calculado correctamente.',
            'data' => $preview,
        ]);
    }

    public function validateCart(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());

        if ($recoverableOrder) {
            return response()->json([
                'ok' => false,
                'message' => 'Hay un pedido pendiente de pago. Puedes reintentar el pago o recuperar tu carrito.',
                'data' => [
                    'can_checkout' => false,
                    'blockers' => [
                        [
                            'code' => 'recoverable_pending_order',
                            'message' => 'Reintenta el pago del pedido pendiente o recupera tu carrito.',
                        ],
                    ],
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ], 409);
        }

        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        $cart = $this->salesChannelService->applyToCart(
            $cart,
            $this->salesChannelService->fromRequest($request),
            $this->salesChannelService->trackingFromRequest($request)
        );
        $cart = $this->cartService->recalculateCart($cart);

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer', 'min:1'],
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
            'sales_channel' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ]);

        $preview = $this->checkoutPreviewService->build(
            $cart,
            $validated['address_id'] ?? null,
            $validated['dir_cli_id'] ?? null
        );

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

    public function guestValidateCart(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $guestToken = $this->guestTokenFromRequest($request, required: true);
        $recoverableOrder = $this->orderService->findRecoverableGuestPendingOrder($guestToken);

        if ($recoverableOrder) {
            return response()->json([
                'ok' => false,
                'message' => 'Hay un pedido invitado pendiente de pago. Puedes reintentar el pago o recuperar tu carrito.',
                'data' => [
                    'can_checkout' => false,
                    'blockers' => [
                        [
                            'code' => 'recoverable_pending_order',
                            'message' => 'Reintenta el pago del pedido pendiente o recupera tu carrito.',
                        ],
                    ],
                    'recoverable_order' => $this->orderService->guestRecoverableOrderPayload($recoverableOrder),
                ],
            ], 409);
        }

        $guest = $this->guestPayloadFromRequest($request, requireGuestData: false);
        $cart = $this->cartService->getOrCreateGuestCart($guestToken);
        $cart = $this->salesChannelService->applyToCart(
            $cart,
            $this->salesChannelService->fromRequest($request),
            $this->salesChannelService->trackingFromRequest($request)
        );
        $cart = $this->syncGuestMetadata($cart, $guest);
        $cart = $this->cartService->recalculateCart($cart);

        $preview = $this->checkoutPreviewService->build(
            $cart,
            null,
            null,
            data_get($guest, 'shipping_address')
        );

        return response()->json([
            'ok' => $preview['can_checkout'],
            'message' => $preview['can_checkout']
                ? 'El carrito invitado está listo para checkout.'
                : 'El carrito invitado tiene detalles por resolver antes del checkout.',
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
            'address_id' => ['nullable', 'integer', 'min:1'],
            'dir_cli_id' => ['nullable', 'integer', 'min:1'],
            'document_notes' => ['nullable', 'string', 'max:1000'],
            'sales_channel' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ]);

        $order = $this->orderService->createPendingFromActiveCart(
            $request->user(),
            $validated['address_id'] ?? null,
            $validated['dir_cli_id'] ?? null,
            $validated['document_notes'] ?? null,
            $this->salesChannelService->fromRequest($request),
            $this->salesChannelService->trackingFromRequest($request)
        );

        return response()->json([
            'ok' => true,
            'message' => 'Pedido creado correctamente.',
            'data' => $this->orderPayload($order),
        ], 201);
    }

    public function guestCreateOrder(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $validated = $request->validate([
            'document_notes' => ['nullable', 'string', 'max:1000'],
            'sales_channel' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ]);
        $guest = $this->guestPayloadFromRequest($request, requireGuestData: true);

        $order = $this->orderService->createGuestPendingFromCart(
            guestToken: $this->guestTokenFromRequest($request, required: true),
            guest: $guest,
            documentNotes: $validated['document_notes'] ?? null,
            salesChannel: $this->salesChannelService->fromRequest($request),
            salesChannelTracking: $this->salesChannelService->trackingFromRequest($request)
        );

        return response()->json([
            'ok' => true,
            'message' => 'Pedido invitado creado correctamente.',
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

        $session = $this->stripePaymentService->createCheckoutSession(
            $order,
            $this->storefrontOrigin($request)
        );

        return response()->json([
            'ok' => true,
            'message' => 'Sesión de Stripe creada correctamente.',
            'data' => $session,
        ]);
    }

    public function guestCreateStripeSession(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $guestToken = $this->guestTokenFromRequest($request, required: true);
        $order = Order::query()
            ->with(['items', 'user'])
            ->whereKey($validated['order_id'])
            ->where('guest_token', $guestToken)
            ->firstOrFail();

        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');

        $session = $this->stripePaymentService->createCheckoutSession(
            $order,
            $this->storefrontOrigin($request)
        );

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

    public function guestConfirmStripeSession(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $guestToken = $this->guestTokenFromRequest($request, required: true);
        $order = $this->stripePaymentService->syncCheckoutSession($validated['session_id']);

        abort_unless($order, 404, 'No se encontró un pedido para esta sesión de Stripe.');
        abort_unless(hash_equals((string) $order->guest_token, $guestToken), 403, 'No tienes acceso a este pedido.');

        return response()->json([
            'ok' => true,
            'message' => $order->payment_status === Order::PAYMENT_PAID
                ? 'Pago confirmado correctamente.'
                : 'La sesión de Stripe todavía no aparece como pagada.',
            'data' => $this->orderPayload($order),
        ]);
    }

    public function guestShowOrder(Request $request, Order $order): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        abort_unless(hash_equals((string) $order->guest_token, $this->guestTokenFromRequest($request, required: true)), 403, 'No tienes acceso a este pedido.');

        return response()->json([
            'ok' => true,
            'message' => 'Pedido invitado obtenido correctamente.',
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

        $order = $this->refreshPaymentOrderBeforeRestore($order);

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

        $order = $this->refreshPaymentOrderBeforeRestore($order);

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

    public function guestRestoreRecoverableOrder(Request $request): JsonResponse
    {
        $this->ensureGuestCheckoutIsEnabled();

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['nullable', 'string', 'max:80'],
        ]);
        $guestToken = $this->guestTokenFromRequest($request, required: true);
        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('guest_token', $guestToken)
            ->firstOrFail();

        $order = $this->refreshPaymentOrderBeforeRestore($order);
        abort_unless($order->isPendingPayment(), 422, 'Este pedido ya no se puede recuperar porque no está pendiente de pago.');

        $cart = $this->orderService->restoreGuestCartFromPendingOrder(
            order: $order,
            guestToken: $guestToken,
            reason: $validated['reason'] ?? 'guest_recoverable_order_accepted'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Carrito invitado recuperado correctamente.',
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
            'guest_token' => $order->guest_token,
            'checkout_mode' => $order->guest_token ? 'guest' : 'authenticated',
            'number' => $order->number,
            'orden_compra' => $order->orden_compra,
            'sales_channel' => $order->sales_channel ?: SalesChannelService::DEFAULT_CHANNEL,
            'sales_channel_label' => $this->salesChannelService->label($order->sales_channel),
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
            'stripe_account_id' => data_get($order->metadata, 'stripe_connect.stripe_account_id'),
            'charge_type' => data_get($order->metadata, 'stripe_connect.charge_type', 'platform'),
            'paid_at' => $order->paid_at,
            'promotions_applied' => data_get($order->metadata, 'promotions_applied', []),
            'coupon' => data_get($order->metadata, 'coupon'),
            'tax_breakdown' => data_get($order->metadata, 'tax_breakdown', []),
            'shipping_address' => $order->shipping_address_snapshot,
            'guest' => data_get($order->metadata, 'guest'),
            'document_notes' => $order->document_notes,
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

    protected function refreshPaymentOrderBeforeRestore(Order $order): Order
    {
        $order = $this->refreshStripeOrderBeforeRestore($order);

        if (! $order->isPendingPayment()) {
            return $order;
        }

        $payment = $order->payments()
            ->where('provider', 'mercadopago')
            ->where('status', Order::PAYMENT_PENDING)
            ->whereNotNull('external_reference')
            ->latest('id')
            ->first();

        if (! $payment) {
            return $order;
        }

        $result = $this->mercadoPagoReconciler->reconcileByExternalReference(
            tenant(),
            (string) $payment->external_reference
        );

        abort_if($result && ! $result['order'], 409, 'No fue posible conciliar el pago pendiente.');

        return $result['order'] ?? $order;
    }

    protected function ensureGuestCheckoutIsEnabled(): void
    {
        abort_if(
            (bool) data_get(EcommerceSetting::accessRulesSettings(), 'requires_login_to_purchase', true),
            403,
            'La tienda requiere iniciar sesión para comprar.'
        );
    }

    protected function guestTokenFromRequest(Request $request, bool $required = false): ?string
    {
        $token = $request->header('X-Guest-Token')
            ?: $request->input('guest_token')
            ?: $request->query('guest_token');

        $token = $this->cartService->normalizeGuestToken($token);

        abort_if($required && ! $token, 422, 'El token de carrito invitado es obligatorio.');

        return $token;
    }

    protected function guestPayloadFromRequest(Request $request, bool $requireGuestData): array
    {
        $rules = [
            'shipping_address' => ['sometimes', 'array'],
            'shipping_address.contact_name' => ['nullable', 'string', 'max:120'],
            'shipping_address.phone' => ['nullable', 'string', 'max:30'],
            'shipping_address.email' => ['nullable', 'email', 'max:160'],
            'shipping_address.street' => ['nullable', 'string', 'max:500'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:180'],
            'shipping_address.external_number' => ['nullable', 'string', 'max:40'],
            'shipping_address.internal_number' => ['nullable', 'string', 'max:40'],
            'shipping_address.neighborhood' => ['nullable', 'string', 'max:120'],
            'shipping_address.zip_code' => ['nullable', 'string', 'max:20'],
            'shipping_address.city' => ['nullable', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:120'],
            'shipping_address.references' => ['nullable', 'string', 'max:500'],
            'guest' => ['sometimes', 'array'],
            'guest.name' => ['nullable', 'string', 'max:120'],
            'guest.email' => ['nullable', 'email', 'max:160'],
            'guest.phone' => ['nullable', 'string', 'max:30'],
            'guest.shipping_address' => ['nullable', 'array'],
            'guest.shipping_address.contact_name' => ['nullable', 'string', 'max:120'],
            'guest.shipping_address.phone' => ['nullable', 'string', 'max:30'],
            'guest.shipping_address.email' => ['nullable', 'email', 'max:160'],
            'guest.shipping_address.street' => ['nullable', 'string', 'max:500'],
            'guest.shipping_address.address_line_2' => ['nullable', 'string', 'max:180'],
            'guest.shipping_address.external_number' => ['nullable', 'string', 'max:40'],
            'guest.shipping_address.internal_number' => ['nullable', 'string', 'max:40'],
            'guest.shipping_address.neighborhood' => ['nullable', 'string', 'max:120'],
            'guest.shipping_address.zip_code' => ['nullable', 'string', 'max:20'],
            'guest.shipping_address.city' => ['nullable', 'string', 'max:120'],
            'guest.shipping_address.state' => ['nullable', 'string', 'max:120'],
            'guest.shipping_address.references' => ['nullable', 'string', 'max:500'],
        ];

        $validated = $request->validate($rules, $this->guestCheckoutValidationMessages());
        $guest = $validated['guest'] ?? [];
        $rootShippingAddress = $validated['shipping_address'] ?? [];

        if ($guest === [] && $rootShippingAddress === []) {
            return [];
        }

        $shippingAddress = array_replace_recursive(
            $rootShippingAddress,
            data_get($guest, 'shipping_address', [])
        );

        $guest['name'] = data_get($guest, 'name')
            ?: data_get($shippingAddress, 'contact_name')
            ?: 'Cliente invitado';
        $guest['phone'] = data_get($guest, 'phone') ?: data_get($shippingAddress, 'phone');
        $guest['email'] = data_get($guest, 'email') ?: data_get($shippingAddress, 'email');
        $shippingAddress['contact_name'] = data_get($shippingAddress, 'contact_name') ?: data_get($guest, 'name');
        $shippingAddress['phone'] = data_get($shippingAddress, 'phone') ?: data_get($guest, 'phone');
        $shippingAddress['email'] = data_get($shippingAddress, 'email') ?: data_get($guest, 'email');

        if ($requireGuestData) {
            abort_if(blank(data_get($shippingAddress, 'street')), 422, 'La dirección de envío es obligatoria.');
            abort_if(blank(data_get($shippingAddress, 'phone')), 422, 'El teléfono de envío es obligatorio.');
        }

        $shippingAddress['full_address'] = collect([
            data_get($shippingAddress, 'street'),
            data_get($shippingAddress, 'address_line_2'),
            data_get($shippingAddress, 'external_number'),
            data_get($shippingAddress, 'internal_number') ? 'Int. ' . data_get($shippingAddress, 'internal_number') : null,
            data_get($shippingAddress, 'neighborhood'),
            data_get($shippingAddress, 'zip_code'),
            data_get($shippingAddress, 'city'),
            data_get($shippingAddress, 'state'),
        ])->filter()->implode(', ');

        $guest['shipping_address'] = $shippingAddress;

        return $guest;
    }

    protected function guestCheckoutValidationMessages(): array
    {
        return [
            'shipping_address.array' => 'La dirección de envío debe enviarse como un objeto.',
            'shipping_address.phone.max' => 'El teléfono de envío no puede superar 30 caracteres.',
            'shipping_address.email.email' => 'El correo de envío no tiene un formato válido.',
            'shipping_address.street.max' => 'La dirección de envío no puede superar 500 caracteres.',
            'shipping_address.address_line_2.max' => 'El interior o complemento no puede superar 180 caracteres.',
            'shipping_address.references.max' => 'Las referencias no pueden superar 500 caracteres.',
            'guest.array' => 'Los datos del invitado deben enviarse como un objeto.',
            'guest.name.max' => 'El nombre del invitado no puede superar 120 caracteres.',
            'guest.email.email' => 'El correo del invitado no tiene un formato válido.',
            'guest.phone.max' => 'El teléfono del invitado no puede superar 30 caracteres.',
            'guest.shipping_address.array' => 'La dirección de envío del invitado debe enviarse como un objeto.',
            'guest.shipping_address.phone.max' => 'El teléfono de envío no puede superar 30 caracteres.',
            'guest.shipping_address.email.email' => 'El correo de envío no tiene un formato válido.',
            'guest.shipping_address.street.max' => 'La dirección de envío no puede superar 500 caracteres.',
            'guest.shipping_address.address_line_2.max' => 'El interior o complemento no puede superar 180 caracteres.',
            'guest.shipping_address.references.max' => 'Las referencias no pueden superar 500 caracteres.',
        ];
    }

    protected function syncGuestMetadata(Cart $cart, array $guest): Cart
    {
        if ($guest === []) {
            return $cart;
        }

        $metadata = $cart->metadata ?? [];
        $metadata['guest'] = array_replace_recursive(data_get($metadata, 'guest', []), $guest);
        $cart->forceFill(['metadata' => $metadata])->save();

        return $cart->fresh([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
    }

    protected function storefrontOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('X-Store-Origin');

        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $origin = trim($origin);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $tenantHost = $request->attributes->get('tenant_host');

        if (! in_array($scheme, ['http', 'https'], true) || ! $host || $host !== $tenantHost) {
            return null;
        }

        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }
}
