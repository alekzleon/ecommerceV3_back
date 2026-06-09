<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVariantAttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'values' => $this->whenLoaded('values', function () {
                return AdminVariantAttributeValueResource::collection($this->values);
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
