<?php

namespace App\Models;

use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'is_active',
        'starts_at',
        'ends_at',
        'requires_login',
        'is_general',
        'is_combinable',
        'priority',
        'image_path',
        'applies_to_specific_customers',
        'has_limit_per_user',
        'limit_per_user',
        'has_global_limit',
        'global_limit',
        'usage_count',
        'config',
        'created_by',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'is_active' => 'boolean',
        'requires_login' => 'boolean',
        'is_general' => 'boolean',
        'is_combinable' => 'boolean',
        'applies_to_specific_customers' => 'boolean',
        'has_limit_per_user' => 'boolean',
        'has_global_limit' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'config' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')->withTimestamps();
    }

    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'promotion_product_variant')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promotion_user')->withTimestamps();
    }

    public function giftItems(): BelongsToMany
    {
        return $this->belongsToMany(GiftItem::class, 'gift_item_promotion')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentWindow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeAvailableForUser(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('is_general', true);

            if ($user) {
                $q->orWhereHas('users', function (Builder $subQuery) use ($user) {
                    $subQuery->where('users.id', $user->id);
                });
            }
        });
    }

    public function scopeUsable(Builder $query, ?User $user): Builder
    {
        return $query
            ->active()
            ->currentWindow()
            ->availableForUser($user);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
