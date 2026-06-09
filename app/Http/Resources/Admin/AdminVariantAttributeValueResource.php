<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVariantAttributeValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_attribute_id' => $this->variant_attribute_id,
            'attribute' => $this->whenLoaded('attribute', function () {
                return [
                    'id' => $this->attribute?->id,
                    'name' => $this->attribute?->name,
                    'slug' => $this->attribute?->slug,
                ];
            }),
            'value' => $this->value,
            'slug' => $this->slug,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
