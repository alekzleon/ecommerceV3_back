<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GiftItem extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'image_disk',
        'image_path',
        'estimated_value',
        'unit_label',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'image_url',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'gift_item_promotion')->withTimestamps();
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? Storage::disk($this->image_disk ?: 'public')->url($this->image_path)
                : null
        );
    }
}
