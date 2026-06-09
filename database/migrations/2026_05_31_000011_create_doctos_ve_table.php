<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctos_ve', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('docto_ve_id')->nullable()->unique();
            $table->char('tipo_docto', 1)->default('r');
            $table->char('subtipo_docto', 1)->default('N');
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->string('folio', 40)->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('clave_cliente', 50)->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('dir_cli_id')->nullable();
            $table->unsignedBigInteger('dir_consig_id')->nullable();
            $table->unsignedBigInteger('almacen_id')->nullable();
            $table->unsignedBigInteger('lugar_expedicion_id')->nullable();
            $table->unsignedBigInteger('moneda_id')->default(1);
            $table->decimal('tipo_cambio', 18, 6)->default(1);
            $table->char('tipo_dscto', 1)->default('P');
            $table->decimal('dscto_pctje', 9, 6)->default(0);
            $table->decimal('dscto_importe', 12, 2)->default(0);
            $table->char('estatus', 1)->default('N');
            $table->char('aplicado', 1)->default('S');
            $table->date('fecha_vigencia_entrega')->nullable();
            $table->string('orden_compra', 60)->nullable();
            $table->date('fecha_orden_compra')->nullable();
            $table->string('folio_recibo_mercancia', 60)->nullable();
            $table->date('fecha_recibo_mercancia')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('importe_neto', 12, 2)->default(0);
            $table->decimal('fletes', 12, 2)->default(0);
            $table->decimal('otros_cargos', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total_retenciones', 12, 2)->default(0);
            $table->decimal('total_anticipos', 12, 2)->default(0);
            $table->decimal('peso_embarque', 12, 3)->default(0);
            $table->char('forma_emitida', 1)->default('S');
            $table->char('contabilizado', 1)->default('N');
            $table->char('acreditar_cxc', 1)->default('N');
            $table->string('sistema_origen', 5)->default('VE');
            $table->unsignedBigInteger('cond_pago_id')->nullable();
            $table->date('fecha_dscto_ppag')->nullable();
            $table->decimal('pctje_dscto_ppag', 9, 6)->default(0);
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->decimal('pctje_comis', 9, 6)->default(0);
            $table->unsignedBigInteger('via_embarque_id')->nullable();
            $table->decimal('importe_cobro', 12, 2)->default(0);
            $table->string('descripcion_cobro')->nullable();
            $table->unsignedBigInteger('impuesto_sustituido_id')->nullable();
            $table->unsignedBigInteger('impuesto_sustituto_id')->nullable();
            $table->string('usuario_creador', 31)->nullable();
            $table->char('es_cfd', 1)->default('N');
            $table->string('modalidad_facturacion')->nullable();
            $table->char('enviado', 1)->default('N');
            $table->timestamp('fecha_hora_envio')->nullable();
            $table->string('email_envio')->nullable();
            $table->char('cfd_envio_especial', 1)->default('N');
            $table->string('uso_cfdi')->nullable();
            $table->string('metodo_pago_sat')->nullable();
            $table->char('cfdi_certificado', 1)->default('N');
            $table->unsignedBigInteger('cfdi_fact_devuelta_id')->nullable();
            $table->timestamp('fecha_hora_creacion')->nullable();
            $table->string('usuario_ult_modif', 31)->nullable();
            $table->string('usuario_aut_creacion', 31)->nullable();
            $table->timestamp('fecha_hora_ult_modif')->nullable();
            $table->char('cargar_sun', 1)->default('N');
            $table->string('usuario_aut_modif', 31)->nullable();
            $table->string('usuario_cancelacion', 31)->nullable();
            $table->timestamp('fecha_hora_cancelacion')->nullable();
            $table->string('usuario_aut_cancelacion', 31)->nullable();
            $table->char('ptl', 1)->default('N');
            $table->string('sync_status', 30)->default('pending');
            $table->boolean('sincronizado')->default(false);
            $table->timestamp('exported_at')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('folio');
            $table->index('cliente_id');
            $table->index('sync_status');
            $table->index('sincronizado');
            $table->index(['estatus', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctos_ve');
    }
};
