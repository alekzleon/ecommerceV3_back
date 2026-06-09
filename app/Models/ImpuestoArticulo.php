<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpuestoArticulo extends Model
{
    protected $table = 'impuestos_articulos';

    protected $fillable = [
        'product_id',
        'impuesto_art_id',
        'articulo_id',
        'impuesto_id',
        'unidades_impuesto',
        'tipo_seleccion',
        'conjunto_sucursales_id',
    ];

    protected $casts = [
        'impuesto_art_id' => 'integer',
        'impuesto_id' => 'integer',
        'unidades_impuesto' => 'decimal:5',
        'conjunto_sucursales_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id', 'impuesto_id');
    }
}
