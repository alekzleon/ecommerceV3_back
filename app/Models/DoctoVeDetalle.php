<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctoVeDetalle extends Model
{
    protected $table = 'doctos_ve_detalles';

    protected $fillable = [
        'docto_ve_local_id',
        'docto_ve_id',
        'order_item_id',
        'product_id',
        'docto_ve_det_id',
        'clave_articulo',
        'articulo_id',
        'unidades',
        'unidades_compro',
        'unidades_surt_de',
        'unidades_a_surtir',
        'precio_unitario',
        'pctje_dscto',
        'dscto_art',
        'pctje_dscto_cli',
        'dscto_extra',
        'pctje_dscto_vol',
        'pctje_dscto_prom',
        'precio_total_neto',
        'precio_modificado',
        'pctje_comis',
        'rol',
        'notas',
        'tercero_co_id',
        'posicion',
        'metadata',
    ];

    protected $casts = [
        'docto_ve_id' => 'integer',
        'docto_ve_det_id' => 'integer',
        'articulo_id' => 'integer',
        'unidades' => 'decimal:5',
        'unidades_compro' => 'decimal:5',
        'unidades_surt_de' => 'decimal:5',
        'unidades_a_surtir' => 'decimal:5',
        'precio_unitario' => 'decimal:6',
        'pctje_dscto' => 'decimal:6',
        'dscto_art' => 'decimal:2',
        'pctje_dscto_cli' => 'decimal:6',
        'dscto_extra' => 'decimal:2',
        'pctje_dscto_vol' => 'decimal:6',
        'pctje_dscto_prom' => 'decimal:6',
        'precio_total_neto' => 'decimal:2',
        'pctje_comis' => 'decimal:6',
        'tercero_co_id' => 'integer',
        'posicion' => 'integer',
        'metadata' => 'array',
    ];

    public function doctoVe(): BelongsTo
    {
        return $this->belongsTo(DoctoVe::class, 'docto_ve_local_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
