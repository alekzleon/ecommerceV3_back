<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioArticulo extends Model
{
    protected $table = 'precios_articulos';

    protected $fillable = [
        'product_id',
        'precio_articulo_id',
        'articulo_id',
        'precio_empresa_id',
        'precio',
        'moneda_id',
        'margen',
        'markup',
        'fecha_hora_ult_modif',
    ];

    protected $casts = [
        'precio_articulo_id' => 'integer',
        'precio_empresa_id' => 'integer',
        'precio' => 'decimal:6',
        'moneda_id' => 'integer',
        'margen' => 'decimal:6',
        'markup' => 'decimal:6',
        'fecha_hora_ult_modif' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
