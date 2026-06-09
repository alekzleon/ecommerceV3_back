<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'dir_cli_id',
        'cliente_id',
        'alias',
        'nombre_consig',
        'calle',
        'nombre_calle',
        'num_exterior',
        'num_interior',
        'colonia',
        'colonia_clave_fiscal',
        'poblacion',
        'poblacion_clave_fisc',
        'referencia',
        'ciudad_id',
        'estado_id',
        'codigo_postal',
        'pais_id',
        'telefono1',
        'telefono2',
        'fax',
        'email',
        'rfc_curp',
        'tipo_persona',
        'clave_regimen_fiscal',
        'tax_id',
        'contacto',
        'via_embarque_id',
        'es_dir_ppal',
        'usar_para_envios',
        'usar_para_facturar',
        'gln',
        'contact_name',
        'street',
        'address_line_2',
        'external_number',
        'internal_number',
        'neighborhood',
        'zip_code',
        'city',
        'state',
        'references',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'dir_cli_id' => 'integer',
        'cliente_id' => 'integer',
        'ciudad_id' => 'integer',
        'estado_id' => 'integer',
        'pais_id' => 'integer',
        'via_embarque_id' => 'integer',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street,
            $this->address_line_2,
            $this->external_number,
            $this->internal_number ? 'Int. ' . $this->internal_number : null,
            $this->neighborhood,
            $this->zip_code,
            $this->city,
            $this->state,
        ])->filter()->implode(', ');
    }
}
