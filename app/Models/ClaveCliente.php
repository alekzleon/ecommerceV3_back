<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaveCliente extends Model
{
    protected $table = 'claves_clientes';

    protected $fillable = [
        'clave_cliente_id',
        'clave_cliente',
        'cliente_id',
        'rol_clave_cli_id',
    ];

    protected $casts = [
        'clave_cliente_id' => 'integer',
        'cliente_id' => 'integer',
        'rol_clave_cli_id' => 'integer',
    ];
}
