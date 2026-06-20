<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'family_id',
        'microsip_id',
        'es_almacenable',
        'es_juego',
        'estatus',
        'causa_susp',
        'fecha_susp',
        'imprimir_comp',
        'permitir_agregar_comp',
        'linea_articulo_id',
        'unidad_venta',
        'unidad_compra',
        'contenido_unidad_compra',
        'peso_unitario',
        'es_peso_variable',
        'seguimiento',
        'dias_garantia',
        'es_importado',
        'es_siempre_importado',
        'pctje_arancel',
        'notas_compras',
        'imprimir_notas_compras',
        'notas_ventas',
        'imprimir_notas_ventas',
        'es_precio_variable',
        'cuenta_almacen',
        'cuenta_costo_venta',
        'cuenta_ventas',
        'cuenta_dscto_ventas',
        'cuenta_devol_ventas',
        'cuenta_compras',
        'cuenta_devol_compras',
        'aplicar_factor_venta',
        'factor_venta',
        'red_precio_con_impto',
        'factor_red_precio_con_impto',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
        'name',
        'slug',
        'description',
        'image_path',
        'default_price',
        'stock',
        'sku',
        'short_description',
        'is_active',
        'brand',
        'keyword',
        'processed',
    ];

    protected $casts = [
        'fecha_susp' => 'date',
        'linea_articulo_id' => 'integer',
        'contenido_unidad_compra' => 'decimal:5',
        'peso_unitario' => 'decimal:5',
        'dias_garantia' => 'integer',
        'pctje_arancel' => 'decimal:6',
        'factor_venta' => 'decimal:5',
        'factor_red_precio_con_impto' => 'decimal:6',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
        'default_price' => 'decimal:2',
        'stock' => 'decimal:2',
        'is_active' => 'boolean',
        'processed' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->slug) && filled($product->name)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('name')) {
                $originalName = $product->getOriginal('name');
                $originalSlug = $product->getOriginal('slug');

                if (
                    blank($product->slug) ||
                    $product->slug === Str::slug($originalName) ||
                    $product->slug === $originalSlug
                ) {
                    $product->slug = static::generateUniqueSlug($product->name, $product->id);
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withTimestamps();
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(ProductGalleryItem::class)->ordered();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->ordered();
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class)->ordered();
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->active()->ordered();
    }

    public function activeVariantAttributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class)->active()->ordered();
    }

    public function activeGalleryItems(): HasMany
    {
        return $this->hasMany(ProductGalleryItem::class)->active()->ordered();
    }

    public function clavesArticulos(): HasMany
    {
        return $this->hasMany(ClaveArticulo::class);
    }

    public function preciosArticulos(): HasMany
    {
        return $this->hasMany(PrecioArticulo::class);
    }

    public function impuestosArticulos(): HasMany
    {
        return $this->hasMany(ImpuestoArticulo::class);
    }

    public function microsipOrderKey(): HasOne
    {
        return $this->hasOne(ClaveArticulo::class)->where('rol_clave_art_id', 17);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites')
            ->withTimestamps();
    }

    public function wishlists(): BelongsToMany
    {
        return $this->belongsToMany(Wishlist::class, 'wishlist_products')
            ->withTimestamps();
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? asset('storage/' . ltrim($this->image_path, '/'))
                : null
        );
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if (blank($baseSlug)) {
            $baseSlug = 'producto';
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
