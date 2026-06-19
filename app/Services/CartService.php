<?php

namespace App\Services;

use App\Enums\CartItemStatus;
use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartEvent;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\GiftItem;
use App\Models\ImpuestoArticulo;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ProductPriceService;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        protected PromotionEngine $promotionEngine,
        protected ProductPriceService $productPriceService,
        protected LoyaltyService $loyaltyService
    ) {
    }

    public function getActiveCart(User $user): ?Cart
    {
        return Cart::query()
            ->forUser($user->id)
            ->active()
            ->with([
                'user',
                'items.product.category',
                'items.product.family',
            ])
            ->latest('id')
            ->first();
    }

    public function getOrCreateActiveCart(User $user): Cart
    {
        $cart = $this->getActiveCart($user);

        if ($cart) {
            return $cart;
        }

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE->value,
            'currency' => 'MXN',
            'items_count' => 0,
            'subtotal_snapshot' => 0,
            'discount_snapshot' => 0,
            'tax_snapshot' => 0,
            'total_snapshot' => 0,
            'last_activity_at' => now(),
        ]);

        $this->registerEvent(
            cart: $cart,
            user: $user,
            eventType: 'cart_created',
            eventData: ['message' => 'Carrito creado automáticamente.']
        );

        return $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
    }

    public function addItem(User $user, Product $product, float $quantity = 1): Cart
    {
        return DB::transaction(function () use ($user, $product, $quantity) {
            $cart = $this->getOrCreateActiveCart($user);

            $quantity = round((float) $quantity, 2);

            if ($quantity <= 0) {
                abort(422, 'La cantidad debe ser mayor a cero.');
            }

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('status', CartItemStatus::ACTIVE->value)
                ->first();

            if ($item) {
                $item->quantity = round((float) $item->quantity + $quantity, 2);
            } else {
                $item = new CartItem([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'status' => CartItemStatus::ACTIVE->value,
                    'quantity' => $quantity,
                ]);
            }

            $this->abortIfInsufficientStock($product, (float) $item->quantity);

            $this->fillItemSnapshot($item, $product, $user);

            $item->base_unit_price_snapshot = round((float) $item->price_snapshot, 2);
            $item->final_unit_price_snapshot = round((float) $item->price_snapshot, 2);
            $item->discount_snapshot = 0;
            $item->line_discount_snapshot = 0;
            $item->promotion_id = null;
            $item->promotion_type = null;
            $item->promotion_name_snapshot = null;
            $item->promotion_snapshot = null;
            $item->line_subtotal_snapshot = round((float) $item->price_snapshot * (float) $item->quantity, 2);

            $item->save();

            $this->recalculateCart($cart);

            $this->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'item_added',
                cartItem: $item,
                eventData: [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'final_quantity' => $item->quantity,
                ]
            );

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function updateItemQuantity(User $user, CartItem $item, float $quantity): Cart
    {
        return DB::transaction(function () use ($user, $item, $quantity) {
            $cart = $item->cart()->with([
                'user',
                'items.product.category',
                'items.product.family',
            ])->firstOrFail();

            $this->ensureCartOwnership($cart, $user);

            $quantity = round((float) $quantity, 2);

            if ($quantity <= 0) {
                return $this->removeItem($user, $item);
            }

            if ($item->product) {
                $this->abortIfInsufficientStock($item->product, $quantity);
            }

            $item->quantity = $quantity;
            $item->line_subtotal_snapshot = round((float) $item->price_snapshot * (float) $item->quantity, 2);
            $item->save();

            $this->recalculateCart($cart);

            $this->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'item_quantity_updated',
                cartItem: $item,
                eventData: [
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                ]
            );

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        return DB::transaction(function () use ($user, $item) {
            $cart = $item->cart()->with([
                'user',
                'items.product.category',
                'items.product.family',
            ])->firstOrFail();

            $this->ensureCartOwnership($cart, $user);

            $eventData = [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'price' => (float) $item->price_snapshot,
                'name' => $item->name_snapshot,
                'sku' => $item->sku_snapshot,
                'brand' => $item->brand_snapshot,
                'line_subtotal' => (float) $item->line_subtotal_snapshot,
            ];

            $this->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'item_removed',
                cartItem: $item,
                eventData: $eventData
            );

            $item->delete();

            $this->recalculateCart($cart);

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function clearCart(User $user): Cart
    {
        return DB::transaction(function () use ($user) {
            $cart = $this->getOrCreateActiveCart($user);

            $items = CartItem::query()
                ->where('cart_id', $cart->id)
                ->get();

            foreach ($items as $item) {
                $this->registerEvent(
                    cart: $cart,
                    user: $user,
                    eventType: 'item_removed',
                    cartItem: $item,
                    eventData: [
                        'product_id' => $item->product_id,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price_snapshot,
                        'name' => $item->name_snapshot,
                        'sku' => $item->sku_snapshot,
                        'brand' => $item->brand_snapshot,
                        'line_subtotal' => (float) $item->line_subtotal_snapshot,
                    ]
                );
            }

            CartItem::query()
                ->where('cart_id', $cart->id)
                ->delete();

            $this->recalculateCart($cart);

            $this->registerEvent(
                cart: $cart,
                user: $user,
                eventType: 'cart_cleared',
                eventData: ['message' => 'El carrito fue vaciado.']
            );

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function bulkAddItems(User $user, array $rows): Cart
    {
        return DB::transaction(function () use ($user, $rows) {
            $cart = $this->getOrCreateActiveCart($user);

            foreach ($rows as $row) {
                /** @var Product $product */
                $product = $row['product'];
                $quantity = round((float) $row['quantity'], 2);

                if ($quantity <= 0) {
                    continue;
                }

                $item = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('product_id', $product->id)
                    ->where('status', CartItemStatus::ACTIVE->value)
                    ->first();

                if ($item) {
                    $item->quantity = round((float) $item->quantity + $quantity, 2);
                } else {
                    $item = new CartItem([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'status' => CartItemStatus::ACTIVE->value,
                        'quantity' => $quantity,
                    ]);
                }

                $this->abortIfInsufficientStock($product, (float) $item->quantity);

                $this->fillItemSnapshot($item, $product, $user);

                $item->base_unit_price_snapshot = round((float) $item->price_snapshot, 2);
                $item->final_unit_price_snapshot = round((float) $item->price_snapshot, 2);
                $item->discount_snapshot = 0;
                $item->line_discount_snapshot = 0;
                $item->promotion_id = null;
                $item->promotion_type = null;
                $item->promotion_name_snapshot = null;
                $item->promotion_snapshot = null;
                $item->line_subtotal_snapshot = round((float) $item->price_snapshot * (float) $item->quantity, 2);
                $item->save();

                $this->registerEvent(
                    cart: $cart,
                    user: $user,
                    eventType: 'item_added_from_excel',
                    cartItem: $item,
                    eventData: [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'final_quantity' => $item->quantity,
                        'sku' => $product->sku,
                    ]
                );
            }

            $this->recalculateCart($cart);

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        });
    }

    public function selectPromotionGiftItem(Cart $cart, Promotion $promotion, GiftItem $giftItem): Cart
    {
        $metadata = $cart->metadata ?? [];
        $selectedGiftItems = data_get($metadata, 'selected_gift_items', []);
        $selectedGiftItems[(string) $promotion->id] = $giftItem->id;
        $metadata['selected_gift_items'] = $selectedGiftItems;

        $cart->forceFill([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ])->save();

        return $this->recalculateCart($cart);
    }

    public function clearPromotionGiftItemSelection(Cart $cart, Promotion $promotion): Cart
    {
        $metadata = $cart->metadata ?? [];
        $selectedGiftItems = data_get($metadata, 'selected_gift_items', []);
        unset($selectedGiftItems[(string) $promotion->id]);
        $metadata['selected_gift_items'] = $selectedGiftItems;

        $cart->forceFill([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ])->save();

        return $this->recalculateCart($cart);
    }

    public function applyCoupon(User $user, string $code): Cart
    {
        return DB::transaction(function () use ($user, $code) {
            $cart = $this->getOrCreateActiveCart($user);
            $coupon = Coupon::query()
                ->where('code', strtoupper(trim($code)))
                ->first();

            abort_unless($coupon, 422, 'El cupón no existe.');

            $validationMessage = $this->couponValidationMessage($coupon, $user);
            abort_if($validationMessage, 422, $validationMessage);

            $metadata = $cart->metadata ?? [];
            $metadata['coupon'] = [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'is_valid' => true,
                'message' => 'Cupón aplicado correctamente.',
                'discount_amount' => 0,
            ];

            $cart->forceFill([
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ])->save();

            return $this->recalculateCart($cart);
        });
    }

    public function clearCoupon(User $user): Cart
    {
        return DB::transaction(function () use ($user) {
            $cart = $this->getOrCreateActiveCart($user);
            $metadata = $cart->metadata ?? [];
            unset($metadata['coupon']);

            $cart->forceFill([
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ])->save();

            return $this->recalculateCart($cart);
        });
    }

    public function recalculateCart(Cart $cart): Cart
    {
        $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);

        if ($cart->items->isEmpty()) {
            $cart->forceFill([
                'items_count' => 0,
                'subtotal_snapshot' => 0,
                'discount_snapshot' => 0,
                'tax_snapshot' => 0,
                'total_snapshot' => 0,
                'metadata' => array_merge($cart->metadata ?? [], [
                    'taxes' => [
                        'total' => 0.0,
                        'items' => [],
                    ],
                ]),
                'last_activity_at' => now(),
            ])->save();

            return $cart->fresh([
                'user',
                'items.product.category',
                'items.product.family',
            ]);
        }

        $this->refreshItemPriceSnapshots($cart);
        $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);

        $this->promotionEngine->applyToCart($cart, $cart->user);
        $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
        $this->refreshItemStockSnapshots($cart);
        $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);

        $itemsCount = round((float) $cart->items->sum('quantity'), 2);

        $subtotal = round(
            (float) $cart->items->sum(function ($item) {
                return round((float) $item->base_unit_price_snapshot * (float) $item->quantity, 2);
            }),
            2
        );

        $itemDiscount = round((float) $cart->items->sum('line_discount_snapshot'), 2);
        $firstPurchaseBase = max(0, round($subtotal - $itemDiscount, 2));
        $firstPurchaseDiscount = $this->loyaltyService->firstPurchaseDiscount($cart->user, $firstPurchaseBase);
        $taxBreakdown = $this->calculateTaxes($cart);
        $tax = round((float) $taxBreakdown['total'], 2);
        $metadata = $cart->metadata ?? [];
        $coupon = $this->calculateCouponDiscount(
            cart: $cart,
            baseAmount: max(0, round($subtotal - $itemDiscount - $firstPurchaseDiscount['amount'], 2)),
            metadata: $metadata
        );
        $preCashbackTotal = max(0, round($subtotal - $itemDiscount - $firstPurchaseDiscount['amount'] - $coupon['discount_amount'] + $tax, 2));
        $cashbackRequested = round((float) data_get($metadata, 'loyalty.cashback.applied_amount', 0), 2);
        $cashbackApplied = min($cashbackRequested, $this->loyaltyService->maxRedeemable($cart->user, $preCashbackTotal));
        $cashbackEarn = $this->loyaltyService->cashbackEarn(max(0, round($preCashbackTotal - $cashbackApplied, 2)));
        $discount = round($itemDiscount + $firstPurchaseDiscount['amount'] + $coupon['discount_amount'] + $cashbackApplied, 2);
        $total = max(0, round($preCashbackTotal - $cashbackApplied, 2));

        $metadata['taxes'] = $taxBreakdown;

        if ($coupon['id'] || $coupon['code']) {
            $metadata['coupon'] = $coupon;
        } else {
            unset($metadata['coupon']);
        }
        $metadata['loyalty'] = [
            'first_purchase_discount' => $firstPurchaseDiscount,
            'cashback' => [
                'available_balance' => $this->loyaltyService->availableCashback($cart->user),
                'max_redeemable' => $this->loyaltyService->maxRedeemable($cart->user, $preCashbackTotal),
                'applied_amount' => $cashbackApplied,
                'earn' => $cashbackEarn,
            ],
        ];

        $cart->forceFill([
            'items_count' => $itemsCount,
            'subtotal_snapshot' => $subtotal,
            'discount_snapshot' => $discount,
            'tax_snapshot' => $tax,
            'total_snapshot' => $total,
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ])->save();

        return $cart->fresh([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
    }

    public function registerEvent(
        Cart $cart,
        User $user,
        string $eventType,
        ?CartItem $cartItem = null,
        ?int $cartItemId = null,
        ?array $eventData = null
    ): CartEvent {
        return CartEvent::create([
            'cart_id' => $cart->id,
            'cart_item_id' => $cartItem?->id ?? $cartItemId,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'created_at' => now(),
        ]);
    }

    protected function fillItemSnapshot(CartItem $item, Product $product, ?User $user = null, ?array $pricePayload = null): void
    {
        $pricePayload ??= $this->productPriceService->priceForProduct($product, $user);
        $price = (float) $pricePayload['price'];

        $item->sku_snapshot = $product->sku;
        $item->name_snapshot = $product->name;
        $item->brand_snapshot = $product->brand ?? null;
        $item->image_snapshot = $product->image_path ?? null;
        $item->category_snapshot = $product->category?->name ?? null;
        $item->family_snapshot = $product->family?->name ?? null;
        $item->price_snapshot = round($price, 2);
        $metadata = $item->metadata ?? [];
        $metadata['price'] = [
            'precio_empresa_id' => $pricePayload['precio_empresa_id'],
            'requested_precio_empresa_id' => $pricePayload['requested_precio_empresa_id'],
            'is_default_price_list' => $pricePayload['is_default_price_list'],
            'source' => $pricePayload['source'],
        ];
        $item->metadata = $metadata;
    }

    protected function refreshItemPriceSnapshots(Cart $cart): void
    {
        $prices = $this->productPriceService->pricesForProducts(
            $cart->items->pluck('product')->filter()->values(),
            $cart->user
        );

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $this->fillItemSnapshot($item, $item->product, $cart->user, $prices->get((int) $item->product_id));
            $item->base_unit_price_snapshot = round((float) $item->price_snapshot, 2);
            $item->final_unit_price_snapshot = round((float) $item->price_snapshot, 2);
            $item->line_subtotal_snapshot = round((float) $item->price_snapshot * (float) $item->quantity, 2);
            $this->applyStockSnapshot($item);

            $item->save();
        }
    }

    protected function refreshItemStockSnapshots(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $this->applyStockSnapshot($item);
            $item->save();
        }
    }

    protected function abortIfInsufficientStock(Product $product, float $quantity): void
    {
        if ($product->stock === null) {
            return;
        }

        abort_if((float) $product->stock <= 0, 422, 'Producto sin inventario disponible.');
        abort_if((float) $product->stock < $quantity, 422, "Solo hay {$product->stock} pieza(s) disponibles.");
    }

    protected function applyStockSnapshot(CartItem $item): void
    {
        $stock = $item->product?->stock;
        $metadata = $item->metadata ?? [];

        if ($stock === null) {
            $metadata['stock'] = [
                'is_tracked' => false,
                'is_valid' => true,
                'available_stock' => null,
                'requested_quantity' => (float) $item->quantity,
                'message' => null,
            ];
            $item->status = CartItemStatus::ACTIVE->value;
            $item->metadata = $metadata;
            return;
        }

        $availableStock = (float) $stock;
        $requestedQuantity = (float) $item->quantity;
        $isValid = $availableStock > 0 && $requestedQuantity <= $availableStock;

        $metadata['stock'] = [
            'is_tracked' => true,
            'is_valid' => $isValid,
            'available_stock' => $availableStock,
            'requested_quantity' => $requestedQuantity,
            'message' => $isValid
                ? ($availableStock < 5 ? 'Hay pocas piezas disponibles.' : null)
                : ($availableStock <= 0 ? 'Producto sin inventario disponible.' : "Solo hay {$availableStock} pieza(s) disponibles."),
        ];

        $item->status = $isValid ? CartItemStatus::ACTIVE->value : CartItemStatus::UNAVAILABLE->value;
        $item->metadata = $metadata;
    }

    protected function calculateTaxes(Cart $cart): array
    {
        $articuloIds = $cart->items
            ->map(fn (CartItem $item) => $item->product?->microsip_id)
            ->filter(fn ($microsipId) => filled($microsipId))
            ->map(fn ($microsipId) => (string) $microsipId)
            ->unique()
            ->values();

        $taxesByArticuloId = $articuloIds->isEmpty()
            ? collect()
            : ImpuestoArticulo::query()
                ->with('impuesto')
                ->whereIn('articulo_id', $articuloIds)
                ->get()
                ->groupBy(fn (ImpuestoArticulo $impuestoArticulo) => (string) $impuestoArticulo->articulo_id);

        $totalTax = 0.0;
        $items = [];

        foreach ($cart->items as $item) {
            $microsipId = $item->product?->microsip_id;
            $taxableBase = round((float) $item->line_subtotal_snapshot, 2);
            $itemTaxes = [];
            $itemTaxTotal = 0.0;

            if (filled($microsipId)) {
                foreach ($taxesByArticuloId[(string) $microsipId] ?? [] as $impuestoArticulo) {
                    $impuesto = $impuestoArticulo->impuesto;
                    $percentage = $impuesto ? (float) $impuesto->pctje_impuesto : 0.0;

                    if ($percentage <= 0) {
                        continue;
                    }

                    $taxAmount = round($taxableBase * ($percentage / 100), 2);

                    $itemTaxes[] = [
                        'impuesto_art_id' => $impuestoArticulo->impuesto_art_id,
                        'impuesto_id' => $impuestoArticulo->impuesto_id,
                        'nombre' => $impuesto?->nombre,
                        'pctje_impuesto' => $percentage,
                        'importe' => $taxAmount,
                    ];

                    $itemTaxTotal += $taxAmount;
                }
            }

            $itemTaxTotal = round($itemTaxTotal, 2);
            $totalTax += $itemTaxTotal;

            $itemTaxPayload = [
                'taxable_base' => $taxableBase,
                'tax_amount' => $itemTaxTotal,
                'taxes' => $itemTaxes,
            ];

            $itemMetadata = $item->metadata ?? [];
            $itemMetadata['tax'] = $itemTaxPayload;
            $item->forceFill(['metadata' => $itemMetadata])->save();

            $items[] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'microsip_id' => $microsipId,
                ...$itemTaxPayload,
            ];
        }

        return [
            'total' => round($totalTax, 2),
            'items' => $items,
        ];
    }

    protected function calculateCouponDiscount(Cart $cart, float $baseAmount, array $metadata): array
    {
        $couponId = data_get($metadata, 'coupon.id');
        $couponCode = data_get($metadata, 'coupon.code');

        if (!$couponId && !$couponCode) {
            return [
                'id' => null,
                'code' => null,
                'name' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'is_valid' => false,
                'message' => null,
            ];
        }

        $coupon = Coupon::query()
            ->when($couponId, fn ($query) => $query->whereKey($couponId))
            ->when(!$couponId && $couponCode, fn ($query) => $query->where('code', strtoupper((string) $couponCode)))
            ->first();

        if (!$coupon) {
            return [
                'id' => $couponId,
                'code' => $couponCode,
                'name' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'is_valid' => false,
                'message' => 'El cupón ya no existe.',
            ];
        }

        $validationMessage = $this->couponValidationMessage($coupon, $cart->user);

        if ($validationMessage) {
            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'discount_amount' => 0,
                'is_valid' => false,
                'message' => $validationMessage,
            ];
        }

        $discountAmount = $coupon->discount_type === Coupon::DISCOUNT_TYPE_PERCENTAGE
            ? round($baseAmount * ((float) $coupon->discount_value / 100), 2)
            : round((float) $coupon->discount_value, 2);

        $discountAmount = min($discountAmount, $baseAmount);

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'discount_amount' => $discountAmount,
            'is_valid' => true,
            'message' => 'Cupón aplicado correctamente.',
        ];
    }

    protected function couponValidationMessage(Coupon $coupon, User $user): ?string
    {
        if (!$coupon->is_active) {
            return 'El cupón no está activo.';
        }

        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            return 'El cupón aún no está vigente.';
        }

        if ($coupon->ends_at && now()->gt($coupon->ends_at)) {
            return 'El cupón ya venció.';
        }

        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            return 'El cupón ya alcanzó su límite de usos.';
        }

        if (!$coupon->is_general && !$coupon->users()->whereKey($user->id)->exists()) {
            return 'Este cupón no está asignado a tu cuenta.';
        }

        return null;
    }

    protected function ensureCartOwnership(Cart $cart, User $user): void
    {
        abort_unless((int) $cart->user_id === (int) $user->id, 403, 'No tienes acceso a este carrito.');
        abort_unless($cart->status === CartStatus::ACTIVE->value, 422, 'El carrito no está activo.');
    }
}
