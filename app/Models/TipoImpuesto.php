<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoImpuesto extends Model
{
    protected $table = 'tipos_impuestos';

    protected $fillable = [
        'tipo_impto_id',
        'nombre',
        'tipo',
        'grava_otros_imptos',
        'aplica_solo_sobre_impte_imp',
        'id_interno',
        'es_predet',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
    ];

    protected $casts = [
        'tipo_impto_id' => 'integer',
        'aplica_solo_sobre_impte_imp' => 'boolean',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
    ];
}
