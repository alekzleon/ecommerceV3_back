<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaveArticulo extends Model
{
    protected $table = 'claves_articulos';

    protected $fillable = [
        'product_id',
        'clave_articulo_id',
        'clave_articulo',
        'articulo_id',
        'rol_clave_art_id',
        'contenido_empaque',
    ];

    protected $casts = [
        'rol_clave_art_id' => 'integer',
        'contenido_empaque' => 'decimal:5',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
