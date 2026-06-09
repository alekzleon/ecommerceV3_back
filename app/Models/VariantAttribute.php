<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VariantAttribute extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (VariantAttribute $attribute) {
            if (blank($attribute->slug) && filled($attribute->name)) {
                $attribute->slug = static::generateUniqueSlug($attribute->name, (int) $attribute->product_id);
            }
        });

        static::updating(function (VariantAttribute $attribute) {
            if ($attribute->isDirty('name') && blank($attribute->slug)) {
                $attribute->slug = static::generateUniqueSlug(
                    $attribute->name,
                    (int) $attribute->product_id,
                    $attribute->id
                );
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class)->ordered();
    }

    public function activeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class)->active()->ordered();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected static function generateUniqueSlug(string $name, int $productId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'atributo';
        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('product_id', $productId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
