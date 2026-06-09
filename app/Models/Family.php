<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'linea_articulo_id',
        'category_id',
        'grupo_linea_id',
        'name',
        'slug',
        'cuenta_almacen',
        'cuenta_costo_venta',
        'cuenta_ventas',
        'cuenta_dscto_ventas',
        'cuenta_devol_ventas',
        'cuenta_compras',
        'cuenta_devol_compras',
        'aplicar_factor_venta',
        'factor_venta',
        'es_predet',
        'oculto',
        'is_active',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
    ];

    protected $casts = [
        'linea_articulo_id' => 'integer',
        'grupo_linea_id' => 'integer',
        'factor_venta' => 'decimal:5',
        'is_active' => 'boolean',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Family $family) {
            if (blank($family->slug) && filled($family->name)) {
                $family->slug = static::generateUniqueSlug($family->name, (int) $family->category_id);
            }
        });

        static::updating(function (Family $family) {
            if ($family->isDirty('name') || $family->isDirty('category_id')) {
                $originalName = $family->getOriginal('name');
                $originalSlug = $family->getOriginal('slug');

                if (
                    blank($family->slug) ||
                    $family->slug === Str::slug($originalName) ||
                    $family->slug === $originalSlug
                ) {
                    $family->slug = static::generateUniqueSlug(
                        $family->name,
                        (int) $family->category_id,
                        $family->id
                    );
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function generateUniqueSlug(string $name, int $categoryId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if (blank($baseSlug)) {
            $baseSlug = 'familia';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->where('category_id', $categoryId)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
