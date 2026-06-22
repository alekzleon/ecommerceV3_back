<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'linea_articulo_id' => $this->linea_articulo_id,
            'category_id' => $this->category_id,
            'grupo_linea_id' => $this->grupo_linea_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'products_count' => (int) ($this->products_count ?? 0),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'grupo_linea_id' => $this->category?->grupo_linea_id,
                'code' => $this->category?->code,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
                'image_url' => $this->category?->image_url,
                'is_active' => (bool) $this->category?->is_active,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
