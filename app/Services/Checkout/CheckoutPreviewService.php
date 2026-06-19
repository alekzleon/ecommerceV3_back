<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\UserAddress;
use Illuminate\Support\Collection;

class CheckoutPreviewService
{
    public function build(Cart $cart, ?int $dirCliId = null): array
    {
        $cart->loadMissing([
            'user.defaultAddress',
            'user.addresses',
            'items.product.category',
            'items.product.family',
        ]);

        $items = $cart->items->values();
        $shipping = $this->buildShipping($cart, $dirCliId);
        $blockers = $this->blockers($cart, $items, $shipping['selected_address']);
        $checkoutItems = $items->map(fn (CartItem $item, int $index) => $this->buildItem($item, $index + 1))->values();

        return [
            'cart_id' => $cart->id,
            'status' => $cart->status,
            'currency' => $cart->currency,
            'can_checkout' => empty($blockers),
            'blockers' => $blockers,
            'customer' => [
                'id' => $cart->user?->id,
                'name' => $cart->user?->name,
                'username' => $cart->user?->username,
                'email' => $cart->user?->email,
            ],
            'shipping' => $shipping,
            'items_count' => (float) $cart->items_count,
            'items' => $checkoutItems,
            'promotions_applied' => $this->buildPromotionsApplied($items),
            'coupon' => data_get($cart->metadata, 'coupon'),
            'loyalty' => data_get($cart->metadata, 'loyalty', [
                'first_purchase_discount' => null,
                'cashback' => null,
            ]),
            'invoice_preview' => [
                'document_type' => 'checkout_preview',
                'currency' => $cart->currency,
                'lines' => $checkoutItems,
                'totals' => $this->buildTotals($cart, $checkoutItems),
                'notes' => $this->buildInvoiceNotes($checkoutItems),
            ],
            'totals' => $this->buildTotals($cart, $checkoutItems),
        ];
    }

    protected function blockers(Cart $cart, Collection $items, ?array $selectedAddress = null): array
    {
        $blockers = [];

        if ($items->isEmpty()) {
            $blockers[] = [
                'code' => 'empty_cart',
                'message' => 'Tu carrito está vacío.',
            ];
        }

        if (! $selectedAddress) {
            $blockers[] = [
                'code' => 'missing_shipping_address',
                'message' => 'Agrega o selecciona una dirección de envío.',
            ];
        } elseif (! data_get($selectedAddress, 'dir_cli_id')) {
            $blockers[] = [
                'code' => 'missing_dir_cli_id',
                'message' => 'La dirección seleccionada no tiene DIR_CLI_ID de Microsip.',
            ];
        }

        foreach ($items as $item) {
            if ((float) $item->quantity <= 0) {
                $blockers[] = [
                    'code' => 'invalid_quantity',
                    'cart_item_id' => $item->id,
                    'message' => "La cantidad de {$item->name_snapshot} no es válida.",
                ];
            }

            if ((float) $item->base_unit_price_snapshot <= 0) {
                $blockers[] = [
                    'code' => 'invalid_price',
                    'cart_item_id' => $item->id,
                    'message' => "El producto {$item->name_snapshot} no tiene precio válido.",
                ];
            }

            $stock = $this->stockPayload($item);

            if (! $stock['is_valid']) {
                $blockers[] = [
                    'code' => 'insufficient_stock',
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'available_stock' => $stock['available_stock'],
                    'requested_quantity' => $stock['requested_quantity'],
                    'message' => $stock['message'] ?? "El producto {$item->name_snapshot} no tiene inventario suficiente.",
                ];
            }
        }

        return $blockers;
    }

    protected function buildShipping(Cart $cart, ?int $dirCliId = null): array
    {
        $addresses = $cart->user?->addresses
            ? $cart->user->addresses->sortByDesc('is_default')->sortByDesc('id')->values()
            : collect();

        $address = $dirCliId
            ? $addresses->firstWhere('dir_cli_id', $dirCliId)
            : $cart->user?->defaultAddress;

        if ($dirCliId) {
            abort_unless($address, 422, 'La dirección seleccionada no existe o no pertenece al cliente.');
        }

        return [
            'requires_address' => true,
            'has_selected_address' => (bool) $address,
            'selected_address' => $address ? $this->addressPayload($address) : null,
            'addresses' => $addresses->map(fn (UserAddress $userAddress) => $this->addressPayload($userAddress))->values(),
            'can_choose_address' => $addresses->count() > 1,
            'message' => $address
                ? 'Dirección de envío seleccionada.'
                : 'Agrega o selecciona una dirección de envío para continuar.',
        ];
    }

    protected function addressPayload(UserAddress $address): array
    {
        return [
            'id' => $address->id,
            'dir_cli_id' => $address->dir_cli_id,
            'cliente_id' => $address->cliente_id,
            'alias' => $address->alias,
            'street' => $address->street,
            'address_line_2' => $address->address_line_2,
            'zip_code' => $address->zip_code,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
            'delivery_note' => $address->references,
            'contact_name' => $address->contact_name,
            'phone' => $address->phone,
            'is_default' => (bool) $address->is_default,
            'es_dir_ppal' => $address->es_dir_ppal,
            'usar_para_envios' => $address->usar_para_envios,
            'usar_para_facturar' => $address->usar_para_facturar,
            'full_address' => $address->full_address,
        ];
    }

    protected function buildItem(CartItem $item, int $lineNumber): array
    {
        $quantity = (float) $item->quantity;
        $baseUnitPrice = round((float) $item->base_unit_price_snapshot, 2);
        $giftUnits = (float) data_get($item->promotion_snapshot, 'gift_units', 0);
        $giftItemUnits = (float) data_get($item->promotion_snapshot, 'gift_item_units', 0);
        $giftUnits = min($giftUnits, $quantity);
        $regularUnits = max(0, round($quantity - $giftUnits, 2));
        $giftUnitAccountingPrice = data_get($item->promotion_snapshot, 'gift_unit_accounting_price');
        $giftUnitAccountingPrice = $giftUnitAccountingPrice !== null ? round((float) $giftUnitAccountingPrice, 2) : null;
        $giftLineTotal = data_get($item->promotion_snapshot, 'gift_line_total');
        $giftLineTotal = $giftLineTotal !== null ? round((float) $giftLineTotal, 2) : 0.0;
        $regularLineTotal = round($regularUnits * $baseUnitPrice, 2);
        $lineBaseSubtotal = round($quantity * $baseUnitPrice, 2);
        $lineDiscount = round((float) $item->line_discount_snapshot, 2);
        $lineTotal = round((float) $item->line_subtotal_snapshot, 2);

        return [
            'line_number' => $lineNumber,
            'cart_item_id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->sku_snapshot,
            'name' => $item->name_snapshot,
            'brand' => $item->brand_snapshot,
            'image' => $item->image_snapshot,
            'category' => $item->category_snapshot,
            'family' => $item->family_snapshot,
            'quantity' => $quantity,
            'unit_price' => $baseUnitPrice,
            'price_info' => [
                'precio_empresa_id' => data_get($item->metadata, 'price.precio_empresa_id'),
                'requested_precio_empresa_id' => data_get($item->metadata, 'price.requested_precio_empresa_id'),
                'is_default_price_list' => (bool) data_get($item->metadata, 'price.is_default_price_list', true),
                'source' => data_get($item->metadata, 'price.source'),
            ],
            'regular_units' => $regularUnits,
            'regular_line_total' => $regularLineTotal,
            'gift_units' => $giftUnits,
            'gift_item_units' => $giftItemUnits,
            'gift_items' => data_get($item->promotion_snapshot, 'gift_items', []),
            'gift_unit_accounting_price' => $giftUnitAccountingPrice,
            'gift_line_total' => $giftLineTotal,
            'base_subtotal' => $lineBaseSubtotal,
            'discount' => $lineDiscount,
            'taxable_base' => (float) data_get($item->metadata, 'tax.taxable_base', 0),
            'tax' => (float) data_get($item->metadata, 'tax.tax_amount', 0),
            'taxes' => data_get($item->metadata, 'tax.taxes', []),
            'stock' => $this->stockPayload($item),
            'total' => $lineTotal,
            'promotion' => $item->promotion_id ? [
                'id' => (int) $item->promotion_id,
                'type' => $item->promotion_type,
                'name' => $item->promotion_name_snapshot,
                'snapshot' => $item->promotion_snapshot,
            ] : null,
            'accounting' => [
                'requires_gift_minimum_price' => $giftUnits > 0,
                'gift_unit_price' => $giftUnitAccountingPrice,
                'gift_line_total' => $giftLineTotal,
                'note' => $giftUnits > 0
                    ? 'Las unidades de regalo se facturan a $0.10 por unidad.'
                    : null,
            ],
            'breakdown' => [
                'regular' => [
                    'quantity' => $regularUnits,
                    'unit_price' => $baseUnitPrice,
                    'total' => $regularLineTotal,
                ],
                'gift' => [
                    'quantity' => $giftUnits,
                    'unit_price' => $giftUnitAccountingPrice,
                    'total' => $giftLineTotal,
                ],
            ],
        ];
    }

    protected function stockPayload(CartItem $item): array
    {
        $stock = $item->product?->stock;
        $requestedQuantity = (float) $item->quantity;

        if ($stock === null) {
            return [
                'is_tracked' => false,
                'is_valid' => true,
                'available_stock' => null,
                'requested_quantity' => $requestedQuantity,
                'message' => null,
            ];
        }

        $availableStock = (float) $stock;
        $isValid = $availableStock > 0 && $requestedQuantity <= $availableStock;

        return [
            'is_tracked' => true,
            'is_valid' => $isValid,
            'available_stock' => $availableStock,
            'requested_quantity' => $requestedQuantity,
            'message' => $isValid
                ? ($availableStock < 5 ? 'Hay pocas piezas disponibles.' : null)
                : ($availableStock <= 0 ? 'Producto sin inventario disponible.' : "Solo hay {$availableStock} pieza(s) disponibles."),
        ];
    }

    protected function buildPromotionsApplied(Collection $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            if (! $item->promotion_id) {
                continue;
            }

            $promotionId = (int) $item->promotion_id;

            if (! isset($grouped[$promotionId])) {
                $grouped[$promotionId] = [
                    'id' => $promotionId,
                    'type' => $item->promotion_type,
                    'name' => $item->promotion_name_snapshot,
                    'total_discount' => 0,
                        'items_count' => 0,
                        'gift_units' => 0,
                        'gift_item_units' => 0,
                        'gift_line_total' => 0,
                        'items' => [],
                    'snapshot' => $item->promotion_snapshot,
                ];
            }

            $giftUnits = (float) data_get($item->promotion_snapshot, 'gift_units', 0);
            $giftItemUnits = (float) data_get($item->promotion_snapshot, 'gift_item_units', 0);
            $giftLineTotal = (float) data_get($item->promotion_snapshot, 'gift_line_total', 0);

            $grouped[$promotionId]['total_discount'] += (float) $item->line_discount_snapshot;
            $grouped[$promotionId]['items_count'] += 1;
            $grouped[$promotionId]['gift_units'] += $giftUnits;
            $grouped[$promotionId]['gift_item_units'] += $giftItemUnits;
            $grouped[$promotionId]['gift_line_total'] += $giftLineTotal;
            $grouped[$promotionId]['items'][] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name_snapshot,
                'sku' => $item->sku_snapshot,
                'quantity' => (float) $item->quantity,
                'line_discount' => round((float) $item->line_discount_snapshot, 2),
                'gift_units' => $giftUnits,
                'gift_item_units' => $giftItemUnits,
                'gift_line_total' => round($giftLineTotal, 2),
            ];
        }

        return array_values(array_map(function (array $promotion) {
            $promotion['total_discount'] = round((float) $promotion['total_discount'], 2);
            $promotion['gift_units'] = round((float) $promotion['gift_units'], 2);
            $promotion['gift_item_units'] = round((float) $promotion['gift_item_units'], 2);
            $promotion['gift_line_total'] = round((float) $promotion['gift_line_total'], 2);

            return $promotion;
        }, $grouped));
    }

    protected function buildTotals(Cart $cart, Collection $checkoutItems): array
    {
        $giftLineTotal = round((float) $checkoutItems->sum('gift_line_total'), 2);

        return [
            'items_count' => (float) $cart->items_count,
            'subtotal' => round((float) $cart->subtotal_snapshot, 2),
            'discount' => round((float) $cart->discount_snapshot, 2),
            'tax' => round((float) $cart->tax_snapshot, 2),
            'tax_breakdown' => data_get($cart->metadata, 'taxes', [
                'total' => 0.0,
                'items' => [],
            ]),
            'shipping' => 0.0,
            'gift_accounting_total' => $giftLineTotal,
            'loyalty' => data_get($cart->metadata, 'loyalty', [
                'first_purchase_discount' => null,
                'cashback' => null,
            ]),
            'coupon' => data_get($cart->metadata, 'coupon'),
            'total' => round((float) $cart->total_snapshot, 2),
            'amount_due' => round((float) $cart->total_snapshot, 2),
        ];
    }

    protected function buildInvoiceNotes(Collection $checkoutItems): array
    {
        $hasGiftItems = $checkoutItems->contains(fn (array $item) => (float) $item['gift_units'] > 0);

        return $hasGiftItems
            ? ['Las unidades de regalo se facturan a $0.10 por unidad por regla contable.']
            : [];
    }
}
