<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctoVe extends Model
{
    protected $table = 'doctos_ve';

    protected $fillable = [
        'order_id',
        'docto_ve_id',
        'tipo_docto',
        'subtipo_docto',
        'sucursal_id',
        'folio',
        'folio_microsip',
        'fecha',
        'hora',
        'clave_cliente',
        'cliente_id',
        'dir_cli_id',
        'dir_consig_id',
        'almacen_id',
        'lugar_expedicion_id',
        'moneda_id',
        'tipo_cambio',
        'tipo_dscto',
        'dscto_pctje',
        'dscto_importe',
        'estatus',
        'aplicado',
        'fecha_vigencia_entrega',
        'orden_compra',
        'fecha_orden_compra',
        'folio_recibo_mercancia',
        'fecha_recibo_mercancia',
        'descripcion',
        'importe_neto',
        'fletes',
        'otros_cargos',
        'total_impuestos',
        'total_retenciones',
        'total_anticipos',
        'peso_embarque',
        'forma_emitida',
        'contabilizado',
        'acreditar_cxc',
        'sistema_origen',
        'cond_pago_id',
        'fecha_dscto_ppag',
        'pctje_dscto_ppag',
        'vendedor_id',
        'pctje_comis',
        'via_embarque_id',
        'importe_cobro',
        'descripcion_cobro',
        'impuesto_sustituido_id',
        'impuesto_sustituto_id',
        'usuario_creador',
        'es_cfd',
        'modalidad_facturacion',
        'enviado',
        'fecha_hora_envio',
        'email_envio',
        'cfd_envio_especial',
        'uso_cfdi',
        'metodo_pago_sat',
        'cfdi_certificado',
        'cfdi_fact_devuelta_id',
        'fecha_hora_creacion',
        'usuario_ult_modif',
        'usuario_aut_creacion',
        'fecha_hora_ult_modif',
        'cargar_sun',
        'usuario_aut_modif',
        'usuario_cancelacion',
        'fecha_hora_cancelacion',
        'usuario_aut_cancelacion',
        'ptl',
        'sync_status',
        'sincronizado',
        'exported_at',
        'validation_errors',
        'metadata',
    ];

    protected $casts = [
        'docto_ve_id' => 'integer',
        'sucursal_id' => 'integer',
        'fecha' => 'date',
        'cliente_id' => 'integer',
        'dir_cli_id' => 'integer',
        'dir_consig_id' => 'integer',
        'almacen_id' => 'integer',
        'lugar_expedicion_id' => 'integer',
        'moneda_id' => 'integer',
        'tipo_cambio' => 'decimal:6',
        'dscto_pctje' => 'decimal:6',
        'dscto_importe' => 'decimal:2',
        'fecha_vigencia_entrega' => 'date',
        'fecha_orden_compra' => 'date',
        'fecha_recibo_mercancia' => 'date',
        'importe_neto' => 'decimal:2',
        'fletes' => 'decimal:2',
        'otros_cargos' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'total_retenciones' => 'decimal:2',
        'total_anticipos' => 'decimal:2',
        'peso_embarque' => 'decimal:3',
        'cond_pago_id' => 'integer',
        'fecha_dscto_ppag' => 'date',
        'pctje_dscto_ppag' => 'decimal:6',
        'vendedor_id' => 'integer',
        'pctje_comis' => 'decimal:6',
        'via_embarque_id' => 'integer',
        'importe_cobro' => 'decimal:2',
        'impuesto_sustituido_id' => 'integer',
        'impuesto_sustituto_id' => 'integer',
        'fecha_hora_envio' => 'datetime',
        'cfdi_fact_devuelta_id' => 'integer',
        'fecha_hora_creacion' => 'datetime',
        'fecha_hora_ult_modif' => 'datetime',
        'fecha_hora_cancelacion' => 'datetime',
        'sincronizado' => 'boolean',
        'exported_at' => 'datetime',
        'validation_errors' => 'array',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DoctoVeDetalle::class, 'docto_ve_local_id');
    }
}
