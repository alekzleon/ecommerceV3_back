<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AdminPromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),

            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,

            'requires_login' => $this->requires_login,
            'is_general' => $this->is_general,

            'priority' => $this->priority,

            'config' => $this->config,

            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'brand' => $product->brand,
                    ];
                });
            }, []),

            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values(), []),
            'products_count' => (int) ($this->products_count ?? $this->products()->count()),

            'gift_items' => $this->whenLoaded('giftItems', function () {
                return $this->giftItems->map(function ($giftItem) {
                    return [
                        'id' => $giftItem->id,
                        'name' => $giftItem->name,
                        'code' => $giftItem->code,
                        'estimated_value' => $giftItem->estimated_value !== null ? (float) $giftItem->estimated_value : null,
                        'unit_label' => $giftItem->unit_label,
                        'is_active' => (bool) $giftItem->is_active,
                    ];
                });
            }, []),

            'gift_item_ids' => $this->whenLoaded('giftItems', fn () => $this->giftItems->pluck('id')->values(), []),
            'gift_items_count' => (int) ($this->gift_items_count ?? $this->giftItems()->count()),

            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ];
                });
            }, []),

            'user_ids' => $this->whenLoaded('users', fn () => $this->users->pluck('id')->values(), []),
            'users_count' => (int) ($this->users_count ?? $this->users()->count()),

            'created_at' => $this->created_at,
        ];
    }
}
