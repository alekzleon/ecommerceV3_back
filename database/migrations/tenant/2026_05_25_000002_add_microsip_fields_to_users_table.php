<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsip_id', 100)->nullable()->index()->after('email');
            $table->string('contacto1')->nullable()->after('microsip_id');
            $table->string('contacto2')->nullable()->after('contacto1');
            $table->char('estatus', 1)->nullable()->index()->after('contacto2');
            $table->string('causa_susp')->nullable()->after('estatus');
            $table->date('fecha_susp')->nullable()->after('causa_susp');
            $table->char('cobrar_impuestos', 1)->nullable()->after('fecha_susp');
            $table->char('retiene_impuestos', 1)->nullable()->after('cobrar_impuestos');
            $table->char('sujeto_ieps', 1)->nullable()->after('retiene_impuestos');
            $table->char('generar_intereses', 1)->nullable()->after('sujeto_ieps');
            $table->char('emitir_edocta', 1)->nullable()->after('generar_intereses');
            $table->boolean('diferir_cfdi_cobros')->nullable()->after('emitir_edocta');
            $table->decimal('limite_credito', 12, 2)->nullable()->after('diferir_cfdi_cobros');
            $table->unsignedBigInteger('moneda_id')->nullable()->after('limite_credito');
            $table->unsignedBigInteger('cond_pago_id')->nullable()->after('moneda_id');
            $table->unsignedBigInteger('tipo_cliente_id')->nullable()->after('cond_pago_id');
            $table->unsignedBigInteger('zona_cliente_id')->nullable()->after('tipo_cliente_id');
            $table->unsignedBigInteger('cobrador_id')->nullable()->after('zona_cliente_id');
            $table->unsignedBigInteger('vendedor_id')->nullable()->after('cobrador_id');
            $table->text('notas')->nullable()->after('vendedor_id');
            $table->string('cuenta_cxc', 100)->nullable()->after('notas');
            $table->string('cuenta_anticipos', 100)->nullable()->after('cuenta_cxc');
            $table->string('formatos_email')->nullable()->after('cuenta_anticipos');
            $table->string('receptor_cfd', 100)->nullable()->after('formatos_email');
            $table->string('num_prov_cliente', 100)->nullable()->after('receptor_cfd');
            $table->text('campos_addenda')->nullable()->after('num_prov_cliente');
            $table->string('usuario_creador', 100)->nullable()->after('campos_addenda');
            $table->dateTime('fecha_hora_creacion')->nullable()->after('usuario_creador');
            $table->string('usuario_aut_creacion', 100)->nullable()->after('fecha_hora_creacion');
            $table->string('usuario_ult_modif', 100)->nullable()->after('usuario_aut_creacion');
            $table->dateTime('fecha_hora_ult_modif')->nullable()->after('usuario_ult_modif');
            $table->string('usuario_aut_modif', 100)->nullable()->after('fecha_hora_ult_modif');
            $table->string('cfdiw_usuario')->nullable()->after('usuario_aut_modif');
            $table->string('cfdiw_password')->nullable()->after('cfdiw_usuario');
            $table->char('cfdiw_estatus', 1)->nullable()->after('cfdiw_password');
            $table->string('cdfiw_formato_cfd_ve')->nullable()->after('cfdiw_estatus');
            $table->string('cdfiw_formato_cfdi_ve')->nullable()->after('cdfiw_formato_cfd_ve');
            $table->string('cdfiw_formato_dev_cfd_ve')->nullable()->after('cdfiw_formato_cfdi_ve');
            $table->string('cdfiw_formato_dev_cfdi_ve')->nullable()->after('cdfiw_formato_dev_cfd_ve');
            $table->string('cdfiw_formato_cfd_pv')->nullable()->after('cdfiw_formato_dev_cfdi_ve');
            $table->string('cdfiw_formato_cfdi_pv')->nullable()->after('cdfiw_formato_cfd_pv');
            $table->string('cdfiw_formato_dev_cfd_pv')->nullable()->after('cdfiw_formato_cfdi_pv');
            $table->string('cdfiw_formato_dev_cfdi_pv')->nullable()->after('cdfiw_formato_dev_cfd_pv');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['microsip_id']);
            $table->dropIndex(['estatus']);
            $table->dropColumn([
                'microsip_id',
                'contacto1',
                'contacto2',
                'estatus',
                'causa_susp',
                'fecha_susp',
                'cobrar_impuestos',
                'retiene_impuestos',
                'sujeto_ieps',
                'generar_intereses',
                'emitir_edocta',
                'diferir_cfdi_cobros',
                'limite_credito',
                'moneda_id',
                'cond_pago_id',
                'tipo_cliente_id',
                'zona_cliente_id',
                'cobrador_id',
                'vendedor_id',
                'notas',
                'cuenta_cxc',
                'cuenta_anticipos',
                'formatos_email',
                'receptor_cfd',
                'num_prov_cliente',
                'campos_addenda',
                'usuario_creador',
                'fecha_hora_creacion',
                'usuario_aut_creacion',
                'usuario_ult_modif',
                'fecha_hora_ult_modif',
                'usuario_aut_modif',
                'cfdiw_usuario',
                'cfdiw_password',
                'cfdiw_estatus',
                'cdfiw_formato_cfd_ve',
                'cdfiw_formato_cfdi_ve',
                'cdfiw_formato_dev_cfd_ve',
                'cdfiw_formato_dev_cfdi_ve',
                'cdfiw_formato_cfd_pv',
                'cdfiw_formato_cfdi_pv',
                'cdfiw_formato_dev_cfd_pv',
                'cdfiw_formato_dev_cfdi_pv',
            ]);
        });
    }
};
