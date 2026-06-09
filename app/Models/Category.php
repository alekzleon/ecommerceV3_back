<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'grupo_linea_id',
        'code',
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
        'grupo_linea_id' => 'integer',
        'factor_venta' => 'decimal:5',
        'is_active' => 'boolean',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (blank($category->slug) && filled($category->name)) {
                $category->slug = static::generateUniqueSlug($category->name);
            }
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name')) {
                $originalName = $category->getOriginal('name');
                $originalSlug = $category->getOriginal('slug');

                if (
                    blank($category->slug) ||
                    $category->slug === Str::slug($originalName) ||
                    $category->slug === $originalSlug
                ) {
                    $category->slug = static::generateUniqueSlug($category->name, $category->id);
                }
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if (blank($baseSlug)) {
            $baseSlug = 'categoria';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
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
