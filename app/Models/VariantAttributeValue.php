<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class VariantAttributeValue extends Model
{
    protected $fillable = [
        'variant_attribute_id',
        'value',
        'slug',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (VariantAttributeValue $attributeValue) {
            if (blank($attributeValue->slug) && filled($attributeValue->value)) {
                $attributeValue->slug = static::generateUniqueSlug(
                    $attributeValue->value,
                    (int) $attributeValue->variant_attribute_id
                );
            }
        });

        static::updating(function (VariantAttributeValue $attributeValue) {
            if ($attributeValue->isDirty('value') && blank($attributeValue->slug)) {
                $attributeValue->slug = static::generateUniqueSlug(
                    $attributeValue->value,
                    (int) $attributeValue->variant_attribute_id,
                    $attributeValue->id
                );
            }
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(VariantAttribute::class, 'variant_attribute_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attribute_value')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('value');
    }

    protected static function generateUniqueSlug(string $value, int $attributeId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'valor';
        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('variant_attribute_id', $attributeId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
