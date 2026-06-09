<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'sku_snapshot',
        'clave_articulo_id_snapshot',
        'clave_articulo_snapshot',
        'rol_clave_art_id_snapshot',
        'contenido_empaque_snapshot',
        'name_snapshot',
        'brand_snapshot',
        'image_snapshot',
        'quantity',
        'unit_price',
        'discount',
        'line_total',
        'promotion_id',
        'promotion_name_snapshot',
        'promotion_snapshot',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rol_clave_art_id_snapshot' => 'integer',
        'contenido_empaque_snapshot' => 'decimal:5',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'promotion_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function doctoVeDetalle(): HasOne
    {
        return $this->hasOne(DoctoVeDetalle::class);
    }
}
