<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'price_number' => $this->price !== null ? (float) $this->price : null,
            'compare_price' => $this->compare_price,
            'compare_price_number' => $this->compare_price !== null ? (float) $this->compare_price : null,
            'stock' => $this->stock,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'applies_promotions' => (bool) $this->applies_promotions,
            'metadata' => $this->metadata,
            'attribute_value_ids' => $this->whenLoaded(
                'attributeValues',
                fn () => $this->attributeValues->pluck('id')->values()
            ),
            'attribute_values' => $this->whenLoaded('attributeValues', function () {
                return AdminVariantAttributeValueResource::collection($this->attributeValues);
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
