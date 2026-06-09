<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Estas notas son para mí:
     * - Este resource debe regresar tanto los datos base del producto en carrito
     *   como los snapshots calculados después de promociones.
     * - El frontend debe poder mostrar precio original, precio final,
     *   ahorro por producto y promo aplicada sin tener que calcular nada.
     */
    public function toArray(Request $request): array
    {
        $giftUnits = (int) data_get($this->promotion_snapshot, 'gift_units', 0);
        $giftItemUnits = (int) data_get($this->promotion_snapshot, 'gift_item_units', 0);
        $giftUnitAccountingPrice = data_get($this->promotion_snapshot, 'gift_unit_accounting_price');
        $giftLineTotal = data_get($this->promotion_snapshot, 'gift_line_total');

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,

            // snapshots base
            'sku' => $this->sku_snapshot,
            'name' => $this->name_snapshot,
            'brand' => $this->brand_snapshot,
            'image' => $this->image_snapshot,
            'category' => $this->category_snapshot,
            'family' => $this->family_snapshot,
            'status' => $this->status,

            // cantidades
            'quantity' => (float) $this->quantity,

            // precios legacy / base
            'price' => (float) $this->price_snapshot,
            'price_info' => [
                'precio_empresa_id' => data_get($this->metadata, 'price.precio_empresa_id'),
                'requested_precio_empresa_id' => data_get($this->metadata, 'price.requested_precio_empresa_id'),
                'is_default_price_list' => (bool) data_get($this->metadata, 'price.is_default_price_list', true),
                'source' => data_get($this->metadata, 'price.source'),
            ],

            // snapshots calculados para promociones
            'base_unit_price' => (float) $this->base_unit_price_snapshot,
            'final_unit_price' => (float) $this->final_unit_price_snapshot,
            'discount_per_unit' => (float) $this->discount_snapshot,
            'line_discount' => (float) $this->line_discount_snapshot,
            'line_subtotal' => (float) $this->line_subtotal_snapshot,
            'taxable_base' => (float) data_get($this->metadata, 'tax.taxable_base', 0),
            'tax' => (float) data_get($this->metadata, 'tax.tax_amount', 0),
            'taxes' => data_get($this->metadata, 'tax.taxes', []),
            'gift_units' => $giftUnits,
            'gift_item_units' => $giftItemUnits,
            'gift_items' => data_get($this->promotion_snapshot, 'gift_items', []),
            'gift_unit_accounting_price' => $giftUnitAccountingPrice !== null
                ? (float) $giftUnitAccountingPrice
                : null,
            'gift_line_total' => $giftLineTotal !== null
                ? (float) $giftLineTotal
                : null,

            // datos de promoción aplicada
            'promotion_id' => $this->promotion_id,
            'promotion_type' => $this->promotion_type,
            'promotion_name_snapshot' => $this->promotion_name_snapshot,
            'promotion_snapshot' => $this->promotion_snapshot,

            'available_promotions' => app(\App\Services\Promotions\PromotionEngine::class)
                ->getAvailablePromotionsForCartItem($this->resource, $request->user()),

            // estructura cómoda para frontend
            'promotion' => $this->promotion_id ? [
                'id' => (int) $this->promotion_id,
                'type' => $this->promotion_type,
                'name' => $this->promotion_name_snapshot,
                'snapshot' => $this->promotion_snapshot,
            ] : null,
        ];
    }
}
