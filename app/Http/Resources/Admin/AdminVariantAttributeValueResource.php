<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'color_image' => $this->colorImage(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function colorImage(): ?array
    {
        $path = data_get($this->metadata, 'image_path');
        $disk = data_get($this->metadata, 'image_disk', 'public') ?: 'public';

        if (! $path) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
        ];
    }
}
