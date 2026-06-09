<?php

namespace App\Http\Resources\GiftItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'unit_label' => $this->unit_label,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
