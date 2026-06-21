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
        'user_id',
        'name',
        'slug',
        'is_system',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (VariantAttribute $attribute) {
            if (blank($attribute->slug) && filled($attribute->name)) {
                $attribute->slug = static::generateUniqueSlug($attribute->name, $attribute->product_id ? (int) $attribute->product_id : null);
            }
        });

        static::updating(function (VariantAttribute $attribute) {
            if ($attribute->isDirty('name') && blank($attribute->slug)) {
                $attribute->slug = static::generateUniqueSlug(
                    $attribute->name,
                    $attribute->product_id ? (int) $attribute->product_id : null,
                    $attribute->id
                );
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function scopeCatalog(Builder $query): Builder
    {
        return $query->whereNull('product_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected static function generateUniqueSlug(string $name, ?int $productId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'atributo';
        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->when(
                    $productId,
                    fn ($query) => $query->where('product_id', $productId),
                    fn ($query) => $query->whereNull('product_id')
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
