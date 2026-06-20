<?php

namespace App\Http\Resources\Account;

use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'family_id' => $this->family_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name,
                    'slug' => $this->category?->slug,
                ];
            }),
            'family' => $this->whenLoaded('family', function () {
                return [
                    'id' => $this->family?->id,
                    'category_id' => $this->family?->category_id,
                    'name' => $this->family?->name,
                    'slug' => $this->family?->slug,
                ];
            }),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'default_price' => $this->default_price !== null ? (float) $this->default_price : null,
            'base_default_price' => $this->default_price !== null ? (float) $this->default_price : null,
            'price_info' => [
                'precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'requested_precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'is_default_price_list' => true,
                'source' => 'products.default_price',
            ],
            'sku' => $this->sku,
            'stock' => $this->stock !== null ? (float) $this->stock : null,
            'is_active' => (bool) $this->is_active,
            'brand' => $this->brand,
            'keyword' => $this->keyword,
            'added_to_wishlist_at' => $this->pivot?->created_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
