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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'scope' => $this->product_id
                ? 'product'
                : ($this->is_system ? 'system' : 'custom'),
            'is_system' => (bool) $this->is_system,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'values' => $this->whenLoaded('values', function () {
                return AdminVariantAttributeValueResource::collection($this->values);
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
