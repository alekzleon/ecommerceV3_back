<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grupo_linea_id' => $this->grupo_linea_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,
            'products_count' => (int) ($this->products_count ?? 0),
            'families_count' => (int) ($this->families_count ?? 0),
            'families' => $this->whenLoaded('families', fn () => $this->families
                ->map(fn ($family) => [
                    'id' => $family->id,
                    'linea_articulo_id' => $family->linea_articulo_id,
                    'name' => $family->name,
                    'slug' => $family->slug,
                    'is_active' => (bool) $family->is_active,
                ])
                ->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
