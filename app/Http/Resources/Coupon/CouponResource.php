<?php

namespace App\Http\Resources\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'is_active' => (bool) $this->is_active,
            'is_general' => (bool) $this->is_general,
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'usage_limit' => $this->usage_limit,
            'usage_count' => (int) $this->usage_count,
            'remaining_uses' => $this->usage_limit === null ? null : max(0, (int) $this->usage_limit - (int) $this->usage_count),
            'metadata' => $this->metadata,
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ])->values(), []),
            'user_ids' => $this->whenLoaded('users', fn () => $this->users->pluck('id')->values(), []),
            'users_count' => (int) ($this->users_count ?? $this->users()->count()),
            'redemptions_count' => (int) ($this->redemptions_count ?? $this->redemptions()->count()),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
