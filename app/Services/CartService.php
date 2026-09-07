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
use App\Models\VariantAttributeValue;
use App\Services\ProductPriceService;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function getActiveGuestCart(string $guestToken): ?Cart
    {
        return Cart::query()
            ->forGuest($guestToken)
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
            'sales_channel' => 'online_store',
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

    public function getOrCreateGuestCart(?string $guestToken = null): Cart
    {
        $guestToken = $this->normalizeGuestToken($guestToken) ?: $this->newGuestToken();
        $cart = Cart::query()
            ->forGuest($guestToken)
            ->latest('id')
            ->first();

        if ($cart?->status === CartStatus::ACTIVE->value) {
            return $cart;
        }

        // A guest token belongs to one cart for its whole lifecycle. A converted
        // cart therefore receives a new token for the guest's next purchase.
        if ($cart) {
            $guestToken = $this->newGuestToken();
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $cart = Cart::create([
                    'user_id' => null,
                    'guest_token' => $guestToken,
                    'status' => CartStatus::ACTIVE->value,
                    'currency' => 'MXN',
                    'source' => 'guest',
                    'sales_channel' => 'online_store',
                    'items_count' => 0,
                    'subtotal_snapshot' => 0,
                    'discount_snapshot' => 0,
                    'tax_snapshot' => 0,
                    'total_snapshot' => 0,
                    'last_activity_at' => now(),
                    'metadata' => [
                        'checkout_mode' => 'guest',
                    ],
                ]);

                break;
            } catch (UniqueConstraintViolationException) {
                $cart = $this->getActiveGuestCart($guestToken);

                if ($cart) {
                    return $cart;
                }

                $guestToken = $this->newGuestToken();
            }
        }

        abort_unless(isset($cart), 503, 'No fue posible preparar el carrito invitado.');

        $this->registerEvent(
            cart: $cart,
            user: null,
            eventType: 'guest_cart_created',
            eventData: ['message' => 'Carrito invitado creado automáticamente.']
        );

        return $cart->load([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
    }

    public function addItem(User $user, Product $product, float $quantity = 1, array $attributeValueIds = []): Cart
    {
        return $this->addItemToCart(
            cart: $this->getOrCreateActiveCart($user),
            user: $user,
            product: $product,
            quantity: $quantity,
            attributeValueIds: $attributeValueIds
        );
    }

    public function addGuestItem(?string $guestToken, Product $product, float $quantity = 1, array $attributeValueIds = []): Cart
    {
        return $this->addItemToCart(
            cart: $this->getOrCreateGuestCart($guestToken),
            user: null,
            product: $product,
            quantity: $quantity,
            attributeValueIds: $attributeValueIds
        );
    }

    protected function addItemToCart(Cart $cart, ?User $user, Product $product, float $quantity = 1, array $attributeValueIds = []): Cart
    {
        return DB::transaction(function () use ($cart, $user, $product, $quantity, $attributeValueIds) {
            $cart = Cart::query()
                ->with(['user', 'items.product.category', 'items.product.family'])
                ->whereKey($cart->id)
                ->lockForUpdate()
                ->firstOrFail();
            $quantity = round((float) $quantity, 2);

            if ($quantity <= 0) {
                abort(422, 'La cantidad debe ser mayor a cero.');
            }

            $selectedAttributes = $this->selectedAttributesForProduct($product, $attributeValueIds);
            $selectionKey = $this->selectedAttributesKey($selectedAttributes);

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('status', CartItemStatus::ACTIVE->value)
                ->get()
                ->first(fn (CartItem $item) => data_get($item->metadata, 'selected_attributes_key', '') === $selectionKey);

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

            $requestedProductQuantity = $this->requestedProductQuantity($cart, $product, $item);
            $this->abortIfInsufficientStock($product, $requestedProductQuantity);

            $this->fillItemSnapshot($item, $product, $user, selectedAttributes: $selectedAttributes);

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
                    'selected_attributes' => $selectedAttributes,
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
                $requestedProductQuantity = $this->requestedProductQuantity($cart, $item->product, $item, $quantity);
                $this->abortIfInsufficientStock($item->product, $requestedProductQuantity);
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

    public function updateGuestItemQuantity(string $guestToken, CartItem $item, float $quantity): Cart
    {
        return DB::transaction(function () use ($guestToken, $item, $quantity) {
            $cart = $item->cart()->with([
                'user',
                'items.product.category',
                'items.product.family',
            ])->firstOrFail();

            $this->ensureGuestCartOwnership($cart, $guestToken);

            $quantity = round((float) $quantity, 2);

            if ($quantity <= 0) {
                return $this->removeGuestItem($guestToken, $item);
            }

            if ($item->product) {
                $requestedProductQuantity = $this->requestedProductQuantity($cart, $item->product, $item, $quantity);
                $this->abortIfInsufficientStock($item->product, $requestedProductQuantity);
            }

            $item->quantity = $quantity;
            $item->line_subtotal_snapshot = round((float) $item->price_snapshot * (float) $item->quantity, 2);
            $item->save();

            $this->recalculateCart($cart);

            $this->registerEvent(
                cart: $cart,
                user: null,
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

    public function removeGuestItem(string $guestToken, CartItem $item): Cart
    {
        return DB::transaction(function () use ($guestToken, $item) {
            $cart = $item->cart()->with([
                'user',
                'items.product.category',
                'items.product.family',
            ])->firstOrFail();

            $this->ensureGuestCartOwnership($cart, $guestToken);

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
                user: null,
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

    public function clearGuestCart(string $guestToken): Cart
    {
        return DB::transaction(function () use ($guestToken) {
            $cart = $this->getOrCreateGuestCart($guestToken);

            $items = CartItem::query()
                ->where('cart_id', $cart->id)
                ->get();

            foreach ($items as $item) {
                $this->registerEvent(
                    cart: $cart,
                    user: null,
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
                user: null,
                eventType: 'cart_cleared',
                eventData: ['message' => 'El carrito invitado fue vaciado.']
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
            return $this->applyCouponToCart($cart, $code, $user);
        });
    }

    public function applyGuestCoupon(string $guestToken, string $code): Cart
    {
        return DB::transaction(function () use ($guestToken, $code) {
            $cart = $this->getOrCreateGuestCart($guestToken);
            return $this->applyCouponToCart($cart, $code, null);
        });
    }

    public function clearCoupon(User $user): Cart
    {
        return DB::transaction(function () use ($user) {
            $cart = $this->getOrCreateActiveCart($user);
            $metadata = $cart->metadata ?? [];
            unset($metadata['coupon'], $metadata['coupons']);

            $cart->forceFill([
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ])->save();

            return $this->recalculateCart($cart);
        });
    }

    public function clearGuestCoupon(string $guestToken): Cart
    {
        return DB::transaction(function () use ($guestToken) {
            $cart = $this->getOrCreateGuestCart($guestToken);
            $metadata = $cart->metadata ?? [];
            unset($metadata['coupon'], $metadata['coupons']);

            $cart->forceFill([
                'metadata' => $metadata,
                'last_activity_at' => now(),
            ])->save();

            return $this->recalculateCart($cart);
        });
    }

    protected function applyCouponToCart(Cart $cart, string $code, ?User $user): Cart
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        abort_unless($coupon, 422, 'El cupón no existe.');

        $validationMessage = $this->couponValidationMessage($coupon, $user);
        abort_if($validationMessage, 422, $validationMessage);

        $metadata = $cart->metadata ?? [];
        $appliedCoupons = $this->normalizedAppliedCoupons($metadata);

        abort_if(
            $appliedCoupons->contains(fn ($appliedCoupon) => (int) data_get($appliedCoupon, 'id') === (int) $coupon->id),
            422,
            'El cupón ya está aplicado.'
        );

        abort_if(
            $appliedCoupons->isNotEmpty() && (!$coupon->is_combinable || $appliedCoupons->contains(fn ($appliedCoupon) => ! (bool) data_get($appliedCoupon, 'is_combinable'))),
            422,
            'Este cupón no se puede combinar con otros cupones.'
        );

        $appliedCoupons->push($this->couponPayload($coupon, 0, true, 'Cupón aplicado correctamente.'));
        $metadata['coupons'] = $appliedCoupons->values()->all();
        $metadata['coupon'] = $metadata['coupons'][0] ?? null;

        $cart->forceFill([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ])->save();

        return $this->recalculateCart($cart);
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
        $firstPurchaseDiscount = $cart->user
            ? $this->loyaltyService->firstPurchaseDiscount($cart->user, $firstPurchaseBase)
            : [
                'enabled' => false,
                'eligible' => false,
                'percentage' => 0,
                'amount' => 0.0,
            ];
        $taxBreakdown = $this->calculateTaxes($cart);
        $tax = round((float) $taxBreakdown['total'], 2);
        $metadata = $cart->metadata ?? [];
        $couponResult = $this->calculateCouponsDiscount(
            cart: $cart,
            baseAmount: max(0, round($subtotal - $itemDiscount - $firstPurchaseDiscount['amount'], 2)),
            metadata: $metadata
        );
        $preCashbackTotal = max(0, round($subtotal - $itemDiscount - $firstPurchaseDiscount['amount'] - $couponResult['discount_amount'] + $tax, 2));
        $cashbackRequested = round((float) data_get($metadata, 'loyalty.cashback.applied_amount', 0), 2);
        $cashbackApplied = $cart->user
            ? min($cashbackRequested, $this->loyaltyService->maxRedeemable($cart->user, $preCashbackTotal))
            : 0.0;
        $cashbackEarn = $cart->user
            ? $this->loyaltyService->cashbackEarn(max(0, round($preCashbackTotal - $cashbackApplied, 2)))
            : [
                'enabled' => false,
                'percentage' => 0,
                'amount' => 0.0,
            ];
        $discount = round($itemDiscount + $firstPurchaseDiscount['amount'] + $couponResult['discount_amount'] + $cashbackApplied, 2);
        $total = max(0, round($preCashbackTotal - $cashbackApplied, 2));

        $metadata['taxes'] = $taxBreakdown;

        if ($couponResult['coupons'] !== []) {
            $metadata['coupons'] = $couponResult['coupons'];
            $metadata['coupon'] = $couponResult['summary'];
        } else {
            unset($metadata['coupon'], $metadata['coupons']);
        }
        $metadata['loyalty'] = [
            'first_purchase_discount' => $firstPurchaseDiscount,
            'cashback' => [
                'available_balance' => $cart->user ? $this->loyaltyService->availableCashback($cart->user) : 0.0,
                'max_redeemable' => $cart->user ? $this->loyaltyService->maxRedeemable($cart->user, $preCashbackTotal) : 0.0,
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
        ?User $user,
        string $eventType,
        ?CartItem $cartItem = null,
        ?int $cartItemId = null,
        ?array $eventData = null
    ): CartEvent {
        return CartEvent::create([
            'cart_id' => $cart->id,
            'cart_item_id' => $cartItem?->id ?? $cartItemId,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'created_at' => now(),
        ]);
    }

    protected function fillItemSnapshot(
        CartItem $item,
        Product $product,
        ?User $user = null,
        ?array $pricePayload = null,
        ?array $selectedAttributes = null
    ): void {
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

        if ($selectedAttributes !== null) {
            $metadata['selected_attributes'] = $selectedAttributes;
            $metadata['selected_attribute_value_ids'] = collect($selectedAttributes)->pluck('value_id')->values()->all();
            $metadata['selected_attributes_key'] = $this->selectedAttributesKey($selectedAttributes);
        }

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

    protected function requestedProductQuantity(
        Cart $cart,
        Product $product,
        ?CartItem $currentItem = null,
        ?float $currentQuantity = null
    ): float {
        $currentQuantity ??= $currentItem ? (float) $currentItem->quantity : 0;

        $otherQuantity = $cart->items()
            ->where('product_id', $product->id)
            ->where('status', CartItemStatus::ACTIVE->value)
            ->when($currentItem?->exists, fn ($query) => $query->whereKeyNot($currentItem->id))
            ->sum('quantity');

        return round((float) $otherQuantity + (float) $currentQuantity, 2);
    }

    protected function selectedAttributesForProduct(Product $product, array $attributeValueIds): array
    {
        $ids = collect($attributeValueIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return VariantAttributeValue::query()
            ->with('attribute')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('attribute', fn ($query) => $query
                ->where('product_id', $product->id)
                ->where('is_active', true))
            ->get()
            ->sortBy(fn (VariantAttributeValue $value) => [
                (int) $value->attribute?->sort_order,
                (int) $value->sort_order,
                (int) $value->id,
            ])
            ->map(fn (VariantAttributeValue $value) => [
                'attribute_id' => $value->variant_attribute_id,
                'attribute' => $value->attribute?->name,
                'attribute_slug' => $value->attribute?->slug,
                'value_id' => $value->id,
                'value' => $value->value,
                'value_slug' => $value->slug,
                'metadata' => $value->metadata,
            ])
            ->values()
            ->all();
    }

    protected function selectedAttributesKey(array $selectedAttributes): string
    {
        return collect($selectedAttributes)
            ->pluck('value_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->implode('|');
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

    protected function calculateCouponsDiscount(Cart $cart, float $baseAmount, array $metadata): array
    {
        $appliedCoupons = $this->normalizedAppliedCoupons($metadata);

        if ($appliedCoupons->isEmpty()) {
            return [
                'discount_amount' => 0,
                'coupons' => [],
                'summary' => null,
            ];
        }

        $remainingBase = $baseAmount;
        $discountTotal = 0.0;
        $coupons = [];

        foreach ($appliedCoupons as $appliedCoupon) {
            $coupon = Coupon::query()
                ->when(data_get($appliedCoupon, 'id'), fn ($query, $id) => $query->whereKey($id))
                ->when(! data_get($appliedCoupon, 'id') && data_get($appliedCoupon, 'code'), fn ($query) => $query->where('code', strtoupper((string) data_get($appliedCoupon, 'code'))))
                ->first();

            if (!$coupon) {
                $coupons[] = [
                    ...$appliedCoupon,
                    'discount_amount' => 0,
                    'is_valid' => false,
                    'message' => 'El cupón ya no existe.',
                ];
                continue;
            }

            $validationMessage = $this->couponValidationMessage($coupon, $cart->user);

            if ($validationMessage) {
                $coupons[] = $this->couponPayload($coupon, 0, false, $validationMessage);
                continue;
            }

            $discountAmount = $coupon->discount_type === Coupon::DISCOUNT_TYPE_PERCENTAGE
                ? round($remainingBase * ((float) $coupon->discount_value / 100), 2)
                : round((float) $coupon->discount_value, 2);

            $discountAmount = min($discountAmount, $remainingBase);
            $remainingBase = max(0, round($remainingBase - $discountAmount, 2));
            $discountTotal = round($discountTotal + $discountAmount, 2);
            $coupons[] = $this->couponPayload($coupon, $discountAmount, true, 'Cupón aplicado correctamente.');
        }

        $summary = $coupons[0] ?? null;

        if ($summary) {
            $summary['discount_amount'] = $discountTotal;
            $summary['codes'] = collect($coupons)->pluck('code')->filter()->values()->all();
            $summary['count'] = count($coupons);
        }

        return [
            'discount_amount' => $discountTotal,
            'coupons' => $coupons,
            'summary' => $summary,
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

    protected function couponValidationMessage(Coupon $coupon, ?User $user): ?string
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

        if ($user && $coupon->per_user_usage_limit !== null) {
            $userUsageCount = $coupon->redemptions()->where('user_id', $user->id)->count();

            if ($userUsageCount >= $coupon->per_user_usage_limit) {
                return 'Ya alcanzaste el límite de usos de este cupón.';
            }
        }

        if (!$coupon->is_general && (! $user || ! $coupon->users()->whereKey($user->id)->exists())) {
            return 'Este cupón no está asignado a tu cuenta.';
        }

        return null;
    }

    protected function normalizedAppliedCoupons(array $metadata)
    {
        $coupons = collect(data_get($metadata, 'coupons', []));

        if ($coupons->isEmpty() && data_get($metadata, 'coupon')) {
            $coupons = collect([data_get($metadata, 'coupon')]);
        }

        return $coupons
            ->filter(fn ($coupon) => data_get($coupon, 'id') || data_get($coupon, 'code'))
            ->unique(fn ($coupon) => data_get($coupon, 'id') ?: strtoupper((string) data_get($coupon, 'code')))
            ->values();
    }

    protected function couponPayload(Coupon $coupon, float $discountAmount = 0, bool $isValid = true, ?string $message = null): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'is_combinable' => (bool) $coupon->is_combinable,
            'discount_amount' => round($discountAmount, 2),
            'is_valid' => $isValid,
            'message' => $message,
        ];
    }

    protected function ensureCartOwnership(Cart $cart, User $user): void
    {
        abort_unless((int) $cart->user_id === (int) $user->id, 403, 'No tienes acceso a este carrito.');
        abort_unless($cart->status === CartStatus::ACTIVE->value, 422, 'El carrito no está activo.');
    }

    protected function ensureGuestCartOwnership(Cart $cart, string $guestToken): void
    {
        abort_unless(hash_equals((string) $cart->guest_token, $this->normalizeGuestToken($guestToken)), 403, 'No tienes acceso a este carrito.');
        abort_unless($cart->status === CartStatus::ACTIVE->value, 422, 'El carrito no está activo.');
    }

    public function normalizeGuestToken(?string $guestToken): ?string
    {
        $guestToken = trim((string) $guestToken);

        if ($guestToken === '' || strlen($guestToken) > 80 || ! preg_match('/^[A-Za-z0-9]+$/', $guestToken)) {
            return null;
        }

        return $guestToken;
    }

    protected function newGuestToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Cart::query()->where('guest_token', $token)->exists());

        return $token;
    }
}
