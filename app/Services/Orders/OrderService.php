<?php

namespace App\Services\Orders;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CashbackTransaction;
use App\Models\ClaveArticulo;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Services\Checkout\CheckoutPreviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutPreviewService $checkoutPreviewService
    ) {
    }

    public function createPendingFromActiveCart(User $user, ?int $dirCliId = null): Order
    {
        return DB::transaction(function () use ($user, $dirCliId) {
            $cart = $this->cartService->getOrCreateActiveCart($user);
            $cart = $this->cartService->recalculateCart($cart);
            $preview = $this->checkoutPreviewService->build($cart, $dirCliId);

            abort_unless($preview['can_checkout'], 422, 'El carrito tiene detalles por resolver antes del checkout.');
            abort_unless((float) data_get($preview, 'totals.total', 0) > 0, 422, 'El total del pedido debe ser mayor a cero.');

            $existingOrder = Order::query()
                ->where('cart_id', $cart->id)
                ->where('user_id', $user->id)
                ->where('payment_status', Order::PAYMENT_PENDING)
                ->latest('id')
                ->first();

            $ordenCompra = $this->randomOrdenCompra();

            if ($existingOrder) {
                $folioMicrosip = $existingOrder->folio_microsip ?: $this->webFolio($existingOrder->id);

                $existingOrder->forceFill([
                    'orden_compra' => $ordenCompra,
                    'folio_microsip' => $folioMicrosip,
                    'shipping_address_snapshot' => data_get($preview, 'shipping.selected_address'),
                    'metadata' => array_merge($existingOrder->metadata ?? [], [
                        'orden_compra' => $ordenCompra,
                        'folio_microsip' => $folioMicrosip,
                        'dir_cli_id' => data_get($preview, 'shipping.selected_address.dir_cli_id'),
                    ]),
                ])->save();

                return $existingOrder->load('items');
            }

            $order = Order::create([
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'number' => $this->nextOrderNumber(),
                'orden_compra' => $ordenCompra,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'currency' => strtoupper((string) data_get($preview, 'currency', 'MXN')),
                'items_count' => (int) round((float) data_get($preview, 'totals.items_count', 0)),
                'subtotal' => data_get($preview, 'totals.subtotal', 0),
                'discount' => data_get($preview, 'totals.discount', 0),
                'tax' => data_get($preview, 'totals.tax', 0),
                'shipping' => data_get($preview, 'totals.shipping', 0),
                'total' => data_get($preview, 'totals.total', 0),
                'payment_status' => Order::PAYMENT_PENDING,
                'shipping_address_snapshot' => data_get($preview, 'shipping.selected_address'),
                'metadata' => [
                    'cart_id' => $cart->id,
                    'orden_compra' => $ordenCompra,
                    'dir_cli_id' => data_get($preview, 'shipping.selected_address.dir_cli_id'),
                    'promotions_applied' => data_get($preview, 'promotions_applied', []),
                    'coupon' => data_get($preview, 'coupon'),
                    'loyalty' => data_get($preview, 'loyalty', []),
                    'tax_breakdown' => data_get($preview, 'totals.tax_breakdown', []),
                ],
            ]);

            $folioMicrosip = $this->webFolio($order->id);
            $order->forceFill([
                'folio_microsip' => $folioMicrosip,
                'metadata' => array_merge($order->metadata ?? [], [
                    'folio_microsip' => $folioMicrosip,
                ]),
            ])->save();

            foreach (data_get($preview, 'items', []) as $item) {
                $microsipOrderKey = $this->microsipOrderKeySnapshot(data_get($item, 'product_id'));

                $order->items()->create([
                    'product_id' => data_get($item, 'product_id'),
                    'sku_snapshot' => data_get($item, 'sku'),
                    'clave_articulo_id_snapshot' => $microsipOrderKey['clave_articulo_id'],
                    'clave_articulo_snapshot' => $microsipOrderKey['clave_articulo'],
                    'rol_clave_art_id_snapshot' => $microsipOrderKey['rol_clave_art_id'],
                    'contenido_empaque_snapshot' => $microsipOrderKey['contenido_empaque'],
                    'name_snapshot' => data_get($item, 'name'),
                    'brand_snapshot' => data_get($item, 'brand'),
                    'image_snapshot' => data_get($item, 'image'),
                    'quantity' => data_get($item, 'quantity', 0),
                    'unit_price' => data_get($item, 'unit_price', 0),
                    'discount' => data_get($item, 'discount', 0),
                    'line_total' => data_get($item, 'total', 0),
                    'promotion_id' => data_get($item, 'promotion.id'),
                    'promotion_name_snapshot' => data_get($item, 'promotion.name'),
                    'promotion_snapshot' => data_get($item, 'promotion.snapshot'),
                    'metadata' => [
                        'regular_units' => data_get($item, 'regular_units'),
                        'regular_line_total' => data_get($item, 'regular_line_total'),
                        'price_info' => data_get($item, 'price_info'),
                        'gift_units' => data_get($item, 'gift_units'),
                        'gift_item_units' => data_get($item, 'gift_item_units'),
                        'gift_items' => data_get($item, 'gift_items', []),
                        'gift_unit_accounting_price' => data_get($item, 'gift_unit_accounting_price'),
                        'gift_line_total' => data_get($item, 'gift_line_total'),
                        'base_subtotal' => data_get($item, 'base_subtotal'),
                        'taxable_base' => data_get($item, 'taxable_base'),
                        'tax' => data_get($item, 'tax'),
                        'taxes' => data_get($item, 'taxes', []),
                        'accounting' => data_get($item, 'accounting'),
                        'breakdown' => data_get($item, 'breakdown'),
                        'microsip_order_key' => $microsipOrderKey,
                    ],
                ]);
            }

            $this->recordLoyaltyTransactions($order, $preview);
            $this->recordCouponRedemption($order, $preview);

            $cart->forceFill([
                'status' => CartStatus::CONVERTED->value,
                'converted_at' => now(),
                'order_id' => $order->id,
            ])->save();

            $this->cartService->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'order_created',
                eventData: [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'total' => (float) $order->total,
                ]
            );

            return $order->load('items');
        });
    }

    protected function recordLoyaltyTransactions(Order $order, array $preview): void
    {
        $cashbackApplied = round((float) data_get($preview, 'loyalty.cashback.applied_amount', 0), 2);
        $cashbackEarned = round((float) data_get($preview, 'loyalty.cashback.earn.amount', 0), 2);

        if ($cashbackApplied > 0) {
            CashbackTransaction::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => CashbackTransaction::TYPE_DEBIT,
                'status' => CashbackTransaction::STATUS_PENDING,
                'amount' => $cashbackApplied,
                'description' => 'Cashback usado en pedido ' . $order->number,
                'metadata' => [
                    'order_number' => $order->number,
                ],
            ]);
        }

        if ($cashbackEarned > 0) {
            CashbackTransaction::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => CashbackTransaction::TYPE_CREDIT,
                'status' => CashbackTransaction::STATUS_PENDING,
                'amount' => $cashbackEarned,
                'description' => 'Cashback generado por pedido ' . $order->number,
                'metadata' => [
                    'order_number' => $order->number,
                    'earn_percentage' => data_get($preview, 'loyalty.cashback.earn.percentage'),
                ],
            ]);
        }
    }

    protected function recordCouponRedemption(Order $order, array $preview): void
    {
        $couponId = data_get($preview, 'coupon.id');
        $couponDiscount = round((float) data_get($preview, 'coupon.discount_amount', 0), 2);

        if (!$couponId || $couponDiscount <= 0) {
            return;
        }

        $alreadyExists = CouponRedemption::query()
            ->where('coupon_id', $couponId)
            ->where('order_id', $order->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        CouponRedemption::create([
            'coupon_id' => $couponId,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $couponDiscount,
            'metadata' => [
                'order_number' => $order->number,
                'coupon' => data_get($preview, 'coupon'),
            ],
        ]);

        Coupon::query()
            ->whereKey($couponId)
            ->increment('usage_count');
    }

    public function restoreCartFromPendingOrder(Order $order, User $user, string $reason = 'payment_cancelled'): Cart
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $order = Order::query()
                ->with('cart')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $order->user_id === (int) $user->id, 403, 'No tienes acceso a este pedido.');
            abort_unless($order->isPendingPayment(), 422, 'Solo se puede recuperar un carrito de un pedido pendiente de pago.');
            abort_unless($order->cart, 422, 'Este pedido no tiene un carrito asociado para recuperar.');
            abort_unless($order->cart->status === CartStatus::CONVERTED->value, 422, 'El carrito asociado no está convertido.');

            $cart = $order->cart;

            $cart->forceFill([
                'status' => CartStatus::ACTIVE->value,
                'converted_at' => null,
                'order_id' => null,
                'last_activity_at' => now(),
            ])->save();

            $this->cartService->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'order_cancelled_cart_restored',
                eventData: [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'reason' => $reason,
                ]
            );

            $order->delete();

            return $this->cartService->recalculateCart($cart)->load([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function findRecoverablePendingOrder(User $user, ?int $orderId = null): ?Order
    {
        $hasActiveCart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::ACTIVE->value)
            ->exists();

        if ($hasActiveCart) {
            return null;
        }

        return Order::query()
            ->with('cart')
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('payment_status', Order::PAYMENT_PENDING)
            ->where('created_at', '>=', now()->subDay())
            ->when($orderId, fn ($query) => $query->whereKey($orderId))
            ->whereHas('cart', fn ($query) => $query->where('status', CartStatus::CONVERTED->value))
            ->latest('id')
            ->first();
    }

    public function recoverableOrderPayload(?Order $order): ?array
    {
        if (! $order) {
            return null;
        }

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'cart_id' => $order->cart_id,
            'total' => (float) $order->total,
            'currency' => strtolower($order->currency),
            'created_at' => $order->created_at,
            'restore_endpoint' => "/api/v1/checkout/recoverable-order/restore",
        ];
    }

    protected function nextOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $next = Order::query()
            ->where('number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count() + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function webFolio(int $id): string
    {
        return 'W' . $id;
    }

    protected function randomOrdenCompra(): string
    {
        $prefix = 'OC-' . now()->format('Ymd') . '-';

        do {
            $ordenCompra = $prefix . Str::upper(Str::random(12));
        } while (Order::query()->where('orden_compra', $ordenCompra)->exists());

        return $ordenCompra;
    }

    protected function microsipOrderKeySnapshot(mixed $productId): array
    {
        $key = filled($productId)
            ? ClaveArticulo::query()
                ->where('product_id', $productId)
                ->where('rol_clave_art_id', 17)
                ->first()
            : null;

        return [
            'clave_articulo_id' => $key?->clave_articulo_id,
            'clave_articulo' => $key?->clave_articulo,
            'rol_clave_art_id' => $key?->rol_clave_art_id,
            'contenido_empaque' => $key?->contenido_empaque,
        ];
    }
}
