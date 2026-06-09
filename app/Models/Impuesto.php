<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impuesto extends Model
{
    protected $table = 'impuestos';

    protected $fillable = [
        'impuesto_id',
        'tipo_impto_id',
        'nombre',
        'tipo_calc',
        'pctje_impuesto',
        'importe_unitario',
        'unidad_impto',
        'es_predet',
        'oculto',
        'causa_flujo_efectivo',
        'cuenta_pend_en_ventas',
        'cuenta_en_ventas',
        'cuenta_pend_en_compras',
        'cuenta_en_compras',
        'tipo_iva',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
    ];

    protected $casts = [
        'impuesto_id' => 'integer',
        'tipo_impto_id' => 'integer',
        'pctje_impuesto' => 'decimal:6',
        'importe_unitario' => 'decimal:6',
        'causa_flujo_efectivo' => 'boolean',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
    ];
}
