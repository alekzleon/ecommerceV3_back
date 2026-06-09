<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioCliCli extends Model
{
    protected $table = 'precios_cli_cli';

    protected $fillable = [
        'precio_cli_cli_id',
        'politica_precios_cli_id',
        'clave_cliente',
        'cliente_id',
        'precio_empresa_id',
        'politica_dscto_art_cli_id',
    ];

    protected $casts = [
        'precio_cli_cli_id' => 'integer',
        'politica_precios_cli_id' => 'integer',
        'cliente_id' => 'integer',
        'precio_empresa_id' => 'integer',
        'politica_dscto_art_cli_id' => 'integer',
    ];
}
