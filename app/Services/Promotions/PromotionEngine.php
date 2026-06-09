<?php

namespace App\Services\Promotions;

use App\Enums\PromotionType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Collection;

class PromotionEngine
{
    protected const GIFT_ACCOUNTING_UNIT_PRICE = 0.10;

    public function applyToCart(Cart $cart, User $user): void
    {
        $cart->loadMissing([
            'items.product.category',
            'items.product.family',
        ]);

        $items = $cart->items;

        if ($items->isEmpty()) {
            return;
        }

        $this->resetItemsPromotionData($items);

        $productIds = $items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $promotions = Promotion::query()
            ->with(['products', 'giftItems'])
            ->usable($user)
            ->where(function ($query) use ($productIds) {
                $query->whereHas('products', function ($subQuery) use ($productIds) {
                    $subQuery->whereIn('products.id', $productIds);
                })
                    ->orWhereIn('type', [
                        PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                        PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                    ]);
            })
            ->get();

        if ($promotions->isEmpty()) {
            $this->persistItems($items);
            return;
        }

        foreach ($items as $item) {
            $bestMatch = $this->resolveBestPromotionForItem(
                item: $item,
                cartItems: $items,
                promotions: $promotions
            );

            if (! $bestMatch) {
                continue;
            }

            $discountAmount = round((float) $bestMatch['discount_amount'], 2);
            $baseUnitPrice = round((float) $item->base_unit_price_snapshot, 2);
            $quantity = max(1, (float) $item->quantity);
            $baseLineSubtotal = round($baseUnitPrice * $quantity, 2);
            $finalLineSubtotal = max(0, round($baseLineSubtotal - $discountAmount, 2));
            $finalUnitPrice = round($finalLineSubtotal / $quantity, 2);

            $item->promotion_id = $bestMatch['promotion']->id;
            $item->promotion_type = $bestMatch['promotion']->type->value;
            $item->promotion_name_snapshot = $bestMatch['promotion']->name;
            $item->promotion_snapshot = $bestMatch['promotion_snapshot'];
            $item->discount_snapshot = round($baseUnitPrice - $finalUnitPrice, 2);
            $item->line_discount_snapshot = $discountAmount;
            $item->final_unit_price_snapshot = $finalUnitPrice;
            $item->line_subtotal_snapshot = $finalLineSubtotal;
        }

        $this->persistItems($items);
    }

    protected function resetItemsPromotionData(Collection $items): void
    {
        foreach ($items as $item) {
            $baseUnitPrice = (float) ($item->price_snapshot ?: 0);
            $quantity = (float) ($item->quantity ?: 0);

            $item->base_unit_price_snapshot = round($baseUnitPrice, 2);
            $item->final_unit_price_snapshot = round($baseUnitPrice, 2);
            $item->discount_snapshot = 0;
            $item->line_discount_snapshot = 0;
            $item->promotion_id = null;
            $item->promotion_type = null;
            $item->promotion_name_snapshot = null;
            $item->promotion_snapshot = null;
            $item->line_subtotal_snapshot = round($baseUnitPrice * $quantity, 2);
        }
    }

    protected function persistItems(Collection $items): void
    {
        foreach ($items as $item) {
            $item->save();
        }
    }

    protected function resolveBestPromotionForItem(
        CartItem $item,
        Collection $cartItems,
        Collection $promotions
    ): ?array {
        $matches = [];

        foreach ($promotions as $promotion) {
            $result = $this->calculatePromotionForItem(
                promotion: $promotion,
                item: $item,
                cartItems: $cartItems
            );

            if (! $result) {
                continue;
            }

            if (($result['discount_amount'] ?? 0) <= 0 && !($result['applies_as_benefit'] ?? false)) {
                continue;
            }

            $matches[] = $result;
        }

        if (empty($matches)) {
            return null;
        }

        usort($matches, function (array $a, array $b) {
            return ($b['discount_amount'] <=> $a['discount_amount']);
        });

        return $matches[0];
    }

    protected function calculatePromotionForItem(
        Promotion $promotion,
        CartItem $item,
        Collection $cartItems
    ): ?array {
        return match ($promotion->type) {
            PromotionType::DIRECT_PERCENTAGE => $this->calculateDirectPercentage($promotion, $item),
            PromotionType::STRIKETHROUGH_PRICE => $this->calculateStrikethroughPrice($promotion, $item),
            PromotionType::BUNDLE_PAY_X_TAKE_Y => $this->calculateBundlePayXTakeY($promotion, $item),
            PromotionType::BUY_X_GET_DISCOUNT => $this->calculateBuyXGetDiscount($promotion, $item),
            PromotionType::BUY_X_GET_Y => $this->calculateBuyXGetY($promotion, $item, $cartItems),
            PromotionType::BUY_SKU_GET_GIFT_ITEM => $this->calculateBuySkuGetGiftItem($promotion, $item),
            PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM => $this->calculateBrandAmountChooseGiftItem($promotion, $item, $cartItems),
            PromotionType::BRAND_AMOUNT_GET_PRODUCT => $this->calculateBrandAmountGetProduct($promotion, $item, $cartItems),
            default => null,
        };
    }

    protected function calculateDirectPercentage(Promotion $promotion, CartItem $item): ?array
    {
        if (! $this->promotionTargetsItem($promotion, $item)) {
            return null;
        }

        $percentage = (float) data_get($promotion->config, 'discount_percentage', 0);

        if ($percentage <= 0) {
            return null;
        }

        $quantity = $this->integerQuantity($item);
        $baseUnitPrice = (float) $item->base_unit_price_snapshot;
        $discountAmount = round(($baseUnitPrice * $quantity) * ($percentage / 100), 2);

        return [
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'discount_percentage' => $percentage,
                'buy_quantity' => null,
                'pay_quantity' => null,
                'target_product_id' => null,
            ],
        ];
    }

    protected function calculateStrikethroughPrice(Promotion $promotion, CartItem $item): ?array
    {
        if (! $this->promotionTargetsItem($promotion, $item)) {
            return null;
        }

        $promotionalPrice = (float) data_get($promotion->config, 'promotional_price', 0);
        $quantity = $this->integerQuantity($item);
        $baseUnitPrice = (float) $item->base_unit_price_snapshot;

        if ($promotionalPrice <= 0 || $promotionalPrice >= $baseUnitPrice) {
            return null;
        }

        $discountPerUnit = $baseUnitPrice - $promotionalPrice;
        $discountAmount = round($discountPerUnit * $quantity, 2);

        return [
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'promotional_price' => $promotionalPrice,
                'original_price' => $baseUnitPrice,
                'show_strikethrough' => true,
            ],
        ];
    }

    protected function calculateBundlePayXTakeY(Promotion $promotion, CartItem $item): ?array
    {
        if (! $this->promotionTargetsItem($promotion, $item)) {
            return null;
        }

        $buyQuantity = (int) data_get($promotion->config, 'buy_quantity', 0);
        $payQuantity = (int) data_get($promotion->config, 'pay_quantity', 0);

        if ($buyQuantity <= 0 || $payQuantity < 0 || $payQuantity >= $buyQuantity) {
            return null;
        }

        $quantity = $this->integerQuantity($item);

        if ($quantity < $buyQuantity) {
            return null;
        }

        $groups = intdiv($quantity, $buyQuantity);
        $freeUnitsPerGroup = $buyQuantity - $payQuantity;
        $freeUnits = $groups * $freeUnitsPerGroup;

        if ($freeUnits <= 0) {
            return null;
        }

        $baseUnitPrice = (float) $item->base_unit_price_snapshot;
        $giftLineTotal = $this->giftAccountingLineTotal($freeUnits);
        $theoreticalFreeDiscount = round($freeUnits * $baseUnitPrice, 2);
        $discountAmount = max(0, round($theoreticalFreeDiscount - $giftLineTotal, 2));

        return [
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'buy_quantity' => $buyQuantity,
                'pay_quantity' => $payQuantity,
                'free_units' => $freeUnits,
                'gift_units' => $freeUnits,
                'gift_unit_accounting_price' => self::GIFT_ACCOUNTING_UNIT_PRICE,
                'gift_line_total' => $giftLineTotal,
                'theoretical_free_discount' => $theoreticalFreeDiscount,
                'accounting_note' => 'Las unidades de regalo se facturan a $0.10 por unidad.',
            ],
        ];
    }

    protected function calculateBuyXGetDiscount(Promotion $promotion, CartItem $item): ?array
    {
        if (! $this->promotionTargetsItem($promotion, $item)) {
            return null;
        }

        $buyQuantity = (int) data_get($promotion->config, 'buy_quantity', 0);
        $percentage = (float) data_get($promotion->config, 'discount_percentage', 0);

        if ($buyQuantity <= 0 || $percentage <= 0) {
            return null;
        }

        $quantity = $this->integerQuantity($item);

        if ($quantity < $buyQuantity) {
            return null;
        }

        $groups = intdiv($quantity, $buyQuantity);
        $eligibleUnits = $groups * $buyQuantity;

        if ($eligibleUnits <= 0) {
            return null;
        }

        $baseUnitPrice = (float) $item->base_unit_price_snapshot;
        $discountAmount = round(($baseUnitPrice * $eligibleUnits) * ($percentage / 100), 2);

        return [
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'buy_quantity' => $buyQuantity,
                'discount_percentage' => $percentage,
                'eligible_units' => $eligibleUnits,
            ],
        ];
    }

    protected function calculateBuyXGetY(
        Promotion $promotion,
        CartItem $item,
        Collection $cartItems
    ): ?array {
        $targetProductId = (int) data_get($promotion->config, 'target_product_id', 0);
        $buyQuantity = (int) data_get($promotion->config, 'buy_quantity', 0);
        $targetQuantity = (int) data_get($promotion->config, 'target_quantity', 1);
        $discountPercentage = (float) data_get($promotion->config, 'discount_percentage', 100);

        if ($targetProductId <= 0 || $buyQuantity <= 0 || $targetQuantity <= 0 || $discountPercentage <= 0) {
            return null;
        }

        if ((int) $item->product_id !== $targetProductId) {
            return null;
        }

        $triggerProductIds = $promotion->products->pluck('id')->unique()->values();

        if ($triggerProductIds->isEmpty()) {
            return null;
        }

        $triggerQty = (int) floor(
            (float) $cartItems
                ->whereIn('product_id', $triggerProductIds->all())
                ->sum('quantity')
        );

        if ($triggerQty < $buyQuantity) {
            return null;
        }

        $groups = intdiv($triggerQty, $buyQuantity);
        $eligibleTargetUnits = $groups * $targetQuantity;
        $actualTargetUnits = $this->integerQuantity($item);
        $discountedUnits = min($eligibleTargetUnits, $actualTargetUnits);

        if ($discountedUnits <= 0) {
            return null;
        }

        $baseUnitPrice = (float) $item->base_unit_price_snapshot;
        $giftLineTotal = $this->giftAccountingLineTotal($discountedUnits);
        $theoreticalFreeDiscount = round($baseUnitPrice * $discountedUnits, 2);
        $discountAmount = max(0, round($theoreticalFreeDiscount - $giftLineTotal, 2));

        return [
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'buy_quantity' => $buyQuantity,
                'target_product_id' => $targetProductId,
                'target_quantity' => $targetQuantity,
                'discount_percentage' => $discountPercentage,
                'discounted_units' => $discountedUnits,
                'gift_units' => $discountedUnits,
                'gift_unit_accounting_price' => self::GIFT_ACCOUNTING_UNIT_PRICE,
                'gift_line_total' => $giftLineTotal,
                'theoretical_free_discount' => $theoreticalFreeDiscount,
                'accounting_note' => 'Las unidades de regalo se facturan a $0.10 por unidad.',
                'trigger_product_ids' => $triggerProductIds->all(),
            ],
        ];
    }

    protected function promotionTargetsItem(Promotion $promotion, CartItem $item): bool
    {
        return $promotion->products->contains('id', $item->product_id);
    }

    protected function calculateBuySkuGetGiftItem(Promotion $promotion, CartItem $item): ?array
    {
        if (! $this->promotionTargetsItem($promotion, $item)) {
            return null;
        }

        $buyQuantity = (int) data_get($promotion->config, 'buy_quantity', 1);
        $giftQuantity = (int) data_get($promotion->config, 'gift_quantity', 1);
        $quantity = $this->integerQuantity($item);

        if ($buyQuantity <= 0 || $giftQuantity <= 0 || $quantity < $buyQuantity || $promotion->giftItems->isEmpty()) {
            return null;
        }

        $groups = intdiv($quantity, $buyQuantity);
        $giftUnits = $groups * $giftQuantity;

        return [
            'promotion' => $promotion,
            'discount_amount' => 0,
            'applies_as_benefit' => true,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'buy_quantity' => $buyQuantity,
                'gift_quantity' => $giftQuantity,
                'gift_item_units' => $giftUnits,
                'gift_source' => 'gift_items',
                'gift_items' => $this->formatGiftItems($promotion),
                'trigger_product_ids' => $promotion->products->pluck('id')->unique()->values()->all(),
                'accounting_note' => 'El regalo no modifica el subtotal del carrito; se mostrará como beneficio de la promoción.',
            ],
        ];
    }

    protected function calculateBrandAmountChooseGiftItem(
        Promotion $promotion,
        CartItem $item,
        Collection $cartItems
    ): ?array {
        $brand = trim((string) data_get($promotion->config, 'brand', ''));
        $minimumAmount = (float) data_get($promotion->config, 'minimum_amount', 0);
        $giftQuantity = (int) data_get($promotion->config, 'gift_quantity', 1);

        if ($brand === '' || $minimumAmount <= 0 || $giftQuantity <= 0 || $promotion->giftItems->isEmpty()) {
            return null;
        }

        if (! $this->itemMatchesBrand($item, $brand)) {
            return null;
        }

        if (! $this->isFirstBrandItem($item, $cartItems, $brand)) {
            return null;
        }

        $brandSubtotal = $this->brandSubtotal($cartItems, $brand);

        if ($brandSubtotal < $minimumAmount) {
            return null;
        }

        $selectedGiftItem = $this->selectedGiftItemForPromotion($cartItems, $promotion);

        return [
            'promotion' => $promotion,
            'discount_amount' => 0,
            'applies_as_benefit' => true,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'brand' => $brand,
                'minimum_amount' => $minimumAmount,
                'brand_subtotal' => $brandSubtotal,
                'gift_quantity' => $giftQuantity,
                'gift_item_units' => $giftQuantity,
                'gift_source' => 'gift_items',
                'selection_required' => (bool) data_get($promotion->config, 'selection_required', true),
                'gift_items' => $this->formatGiftItems($promotion),
                'selected_gift_item_id' => $selectedGiftItem['id'] ?? null,
                'selected_gift_item' => $selectedGiftItem,
                'accounting_note' => 'El cliente debe seleccionar un regalo disponible para esta promoción.',
            ],
        ];
    }

    protected function calculateBrandAmountGetProduct(
        Promotion $promotion,
        CartItem $item,
        Collection $cartItems
    ): ?array {
        $brand = trim((string) data_get($promotion->config, 'brand', ''));
        $minimumAmount = (float) data_get($promotion->config, 'minimum_amount', 0);
        $targetProductId = (int) data_get($promotion->config, 'target_product_id', 0);
        $targetQuantity = (int) data_get($promotion->config, 'target_quantity', 1);

        if ($brand === '' || $minimumAmount <= 0 || $targetProductId <= 0 || $targetQuantity <= 0) {
            return null;
        }

        $brandSubtotal = $this->brandSubtotal($cartItems, $brand);

        if ($brandSubtotal < $minimumAmount) {
            return null;
        }

        if ((int) $item->product_id === $targetProductId) {
            $discountedUnits = min($targetQuantity, $this->integerQuantity($item));

            if ($discountedUnits <= 0) {
                return null;
            }

            $baseUnitPrice = (float) $item->base_unit_price_snapshot;
            $giftLineTotal = $this->giftAccountingLineTotal($discountedUnits);
            $theoreticalFreeDiscount = round($baseUnitPrice * $discountedUnits, 2);
            $discountAmount = max(0, round($theoreticalFreeDiscount - $giftLineTotal, 2));

            return [
                'promotion' => $promotion,
                'discount_amount' => $discountAmount,
                'promotion_snapshot' => [
                    'label' => $promotion->type->label(),
                    'type' => $promotion->type->value,
                    'brand' => $brand,
                    'minimum_amount' => $minimumAmount,
                    'brand_subtotal' => $brandSubtotal,
                    'target_product_id' => $targetProductId,
                    'target_quantity' => $targetQuantity,
                    'discounted_units' => $discountedUnits,
                    'gift_units' => $discountedUnits,
                    'gift_source' => 'products',
                    'gift_unit_accounting_price' => self::GIFT_ACCOUNTING_UNIT_PRICE,
                    'gift_line_total' => $giftLineTotal,
                    'theoretical_free_discount' => $theoreticalFreeDiscount,
                    'accounting_note' => 'El SKU de regalo se factura a $0.10 por unidad.',
                ],
            ];
        }

        if (! $this->itemMatchesBrand($item, $brand) || ! $this->isFirstBrandItem($item, $cartItems, $brand)) {
            return null;
        }

        return [
            'promotion' => $promotion,
            'discount_amount' => 0,
            'applies_as_benefit' => true,
            'promotion_snapshot' => [
                'label' => $promotion->type->label(),
                'type' => $promotion->type->value,
                'brand' => $brand,
                'minimum_amount' => $minimumAmount,
                'brand_subtotal' => $brandSubtotal,
                'target_product_id' => $targetProductId,
                'target_quantity' => $targetQuantity,
                'gift_source' => 'products',
                'requires_target_product_in_cart' => true,
                'accounting_note' => 'Agrega el SKU asignado al carrito para aplicar el precio de regalo.',
            ],
        ];
    }

    protected function brandSubtotal(Collection $cartItems, string $brand): float
    {
        return round((float) $cartItems
            ->filter(fn (CartItem $item) => $this->itemMatchesBrand($item, $brand))
            ->sum(fn (CartItem $item) => (float) $item->base_unit_price_snapshot * (float) $item->quantity), 2);
    }

    protected function itemMatchesBrand(CartItem $item, string $brand): bool
    {
        return mb_strtolower(trim((string) $item->brand_snapshot)) === mb_strtolower(trim($brand));
    }

    protected function isFirstBrandItem(CartItem $item, Collection $cartItems, string $brand): bool
    {
        $firstBrandItem = $cartItems
            ->filter(fn (CartItem $cartItem) => $this->itemMatchesBrand($cartItem, $brand))
            ->sortBy('id')
            ->first();

        return $firstBrandItem && (int) $firstBrandItem->id === (int) $item->id;
    }

    protected function formatGiftItems(Promotion $promotion): array
    {
        return $promotion->giftItems
            ->map(fn ($giftItem) => [
                'id' => $giftItem->id,
                'name' => $giftItem->name,
                'code' => $giftItem->code,
                'description' => $giftItem->description,
                'image_url' => $giftItem->image_url,
                'estimated_value' => $giftItem->estimated_value !== null ? (float) $giftItem->estimated_value : null,
                'unit_label' => $giftItem->unit_label,
            ])
            ->values()
            ->all();
    }

    protected function selectedGiftItemForPromotion(Collection $cartItems, Promotion $promotion): ?array
    {
        $cart = $cartItems->first()?->cart;
        $selectedGiftItemId = (int) data_get($cart?->metadata, 'selected_gift_items.' . $promotion->id, 0);

        if ($selectedGiftItemId <= 0) {
            return null;
        }

        $giftItem = $promotion->giftItems->firstWhere('id', $selectedGiftItemId);

        if (! $giftItem) {
            return null;
        }

        return [
            'id' => $giftItem->id,
            'name' => $giftItem->name,
            'code' => $giftItem->code,
            'description' => $giftItem->description,
            'image_url' => $giftItem->image_url,
            'estimated_value' => $giftItem->estimated_value !== null ? (float) $giftItem->estimated_value : null,
            'unit_label' => $giftItem->unit_label,
        ];
    }

    protected function integerQuantity(CartItem $item): int
    {
        return max(0, (int) floor((float) $item->quantity));
    }

    protected function giftAccountingLineTotal(int $giftUnits): float
    {
        return round(max(0, $giftUnits) * self::GIFT_ACCOUNTING_UNIT_PRICE, 2);
    }

    public function getAvailablePromotionsForCartItem(CartItem $item, ?User $user = null): array
    {
        $item->loadMissing('product');

        if (! $item->product_id) {
            return [];
        }

        $promotions = Promotion::query()
            ->with(['products:id', 'giftItems:id,name,code,estimated_value,unit_label'])
            ->usable($user)
            ->whereHas('products', function ($query) use ($item) {
                $query->where('products.id', $item->product_id);
            })
            ->orderBy('priority')
            ->get();

        if ($promotions->isEmpty()) {
            return [];
        }

        $available = [];
        $quantity = $this->integerQuantity($item);

        foreach ($promotions as $promotion) {
            $config = $promotion->config ?? [];

            switch ($promotion->type) {
                case PromotionType::BUNDLE_PAY_X_TAKE_Y:
                    $buyQuantity = (int) ($config['buy_quantity'] ?? 0);
                    $payQuantity = (int) ($config['pay_quantity'] ?? 0);

                    if ($buyQuantity <= 0 || $payQuantity < 0 || $payQuantity >= $buyQuantity) {
                        continue 2;
                    }

                    $missing = max($buyQuantity - $quantity, 0);
                    $isEligibleNow = $quantity >= $buyQuantity;

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "Lleva {$buyQuantity} y paga {$payQuantity}",
                        'progress_message' => $isEligibleNow
                            ? 'Ya cumples esta promoción.'
                            : "Agrega {$missing} pieza(s) más para activar esta promoción.",
                        'missing_quantity' => $missing,
                        'is_eligible_now' => $isEligibleNow,
                    ];
                    break;

                case PromotionType::BUY_X_GET_DISCOUNT:
                    $buyQuantity = (int) ($config['buy_quantity'] ?? 0);
                    $discountPercentage = (float) ($config['discount_percentage'] ?? 0);

                    if ($buyQuantity <= 0 || $discountPercentage <= 0) {
                        continue 2;
                    }

                    $missing = max($buyQuantity - $quantity, 0);
                    $isEligibleNow = $quantity >= $buyQuantity;

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "Compra {$buyQuantity} y obtén {$discountPercentage}% de descuento",
                        'progress_message' => $isEligibleNow
                            ? 'Ya cumples esta promoción.'
                            : "Agrega {$missing} pieza(s) más para activar esta promoción.",
                        'missing_quantity' => $missing,
                        'is_eligible_now' => $isEligibleNow,
                    ];
                    break;

                case PromotionType::DIRECT_PERCENTAGE:
                    $discountPercentage = (float) ($config['discount_percentage'] ?? 0);

                    if ($discountPercentage <= 0) {
                        continue 2;
                    }

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "{$discountPercentage}% de descuento disponible para este producto",
                        'progress_message' => 'Esta promoción aplica directamente a este producto.',
                        'missing_quantity' => 0,
                        'is_eligible_now' => true,
                    ];
                    break;

                case PromotionType::STRIKETHROUGH_PRICE:
                    $promotionalPrice = (float) ($config['promotional_price'] ?? 0);

                    if ($promotionalPrice <= 0) {
                        continue 2;
                    }

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "Precio promocional disponible: $" . number_format($promotionalPrice, 2),
                        'progress_message' => 'Esta promoción aplica directamente a este producto.',
                        'missing_quantity' => 0,
                        'is_eligible_now' => true,
                    ];
                    break;

                case PromotionType::BUY_X_GET_Y:
                    $buyQuantity = (int) ($config['buy_quantity'] ?? 0);
                    $targetQuantity = (int) ($config['target_quantity'] ?? 1);
                    $discountPercentage = (float) ($config['discount_percentage'] ?? 100);

                    if ($buyQuantity <= 0 || $targetQuantity <= 0 || $discountPercentage <= 0) {
                        continue 2;
                    }

                    $missing = max($buyQuantity - $quantity, 0);
                    $isEligibleNow = $quantity >= $buyQuantity;

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "Compra {$buyQuantity} y recibe {$targetQuantity} con {$discountPercentage}% de descuento",
                        'progress_message' => $isEligibleNow
                            ? 'Ya cumples esta promoción.'
                            : "Agrega {$missing} pieza(s) más para activar esta promoción.",
                        'missing_quantity' => $missing,
                        'is_eligible_now' => $isEligibleNow,
                    ];
                    break;

                case PromotionType::BUY_SKU_GET_GIFT_ITEM:
                    $buyQuantity = (int) ($config['buy_quantity'] ?? 1);
                    $giftQuantity = (int) ($config['gift_quantity'] ?? 1);

                    if ($buyQuantity <= 0 || $giftQuantity <= 0 || $promotion->giftItems->isEmpty()) {
                        continue 2;
                    }

                    $missing = max($buyQuantity - $quantity, 0);
                    $isEligibleNow = $quantity >= $buyQuantity;

                    $available[] = [
                        'id' => $promotion->id,
                        'type' => $promotion->type->value,
                        'name' => $promotion->name,
                        'message' => "Compra {$buyQuantity} y recibe regalo exclusivo",
                        'progress_message' => $isEligibleNow
                            ? 'Ya cumples esta promoción.'
                            : "Agrega {$missing} pieza(s) más para activar esta promoción.",
                        'missing_quantity' => $missing,
                        'is_eligible_now' => $isEligibleNow,
                        'gift_quantity' => $giftQuantity,
                        'gift_items' => $this->formatGiftItems($promotion),
                    ];
                    break;
            }
        }

        return $available;
    }
}
