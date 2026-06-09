<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioEmpresa extends Model
{
    protected $table = 'precios_empresa';

    protected $fillable = [
        'precio_empresa_id',
        'nombre',
        'id_interno',
        'act_automatica',
        'precio_empresa_act_autc',
        'porcentaje',
        'usar_tabla_factores',
        'factor_redondeo',
        'agregar_precios',
        'posicion',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
    ];

    protected $casts = [
        'precio_empresa_id' => 'integer',
        'act_automatica' => 'boolean',
        'precio_empresa_act_autc' => 'integer',
        'porcentaje' => 'decimal:6',
        'usar_tabla_factores' => 'boolean',
        'factor_redondeo' => 'decimal:6',
        'agregar_precios' => 'boolean',
        'posicion' => 'integer',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
    ];
}
