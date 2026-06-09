<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('user_addresses', 'dir_cli_id')) {
                $table->unsignedBigInteger('dir_cli_id')->nullable()->after('id');
                $table->unsignedBigInteger('cliente_id')->nullable()->after('user_id');
                $table->string('nombre_consig', 200)->nullable()->after('alias');
                $table->string('calle', 430)->nullable()->after('nombre_consig');
                $table->string('nombre_calle', 100)->nullable()->after('calle');
                $table->string('num_exterior', 10)->nullable()->after('nombre_calle');
                $table->string('num_interior', 10)->nullable()->after('num_exterior');
                $table->string('colonia', 100)->nullable()->after('num_interior');
                $table->string('colonia_clave_fiscal', 4)->nullable()->after('colonia');
                $table->string('poblacion', 100)->nullable()->after('colonia_clave_fiscal');
                $table->string('poblacion_clave_fisc', 3)->nullable()->after('poblacion');
                $table->string('referencia', 100)->nullable()->after('poblacion_clave_fisc');
                $table->unsignedBigInteger('ciudad_id')->nullable()->after('referencia');
                $table->unsignedBigInteger('estado_id')->nullable()->after('ciudad_id');
                $table->string('codigo_postal', 10)->nullable()->after('estado_id');
                $table->unsignedBigInteger('pais_id')->nullable()->after('codigo_postal');
                $table->string('telefono1', 35)->nullable()->after('pais_id');
                $table->string('telefono2', 35)->nullable()->after('telefono1');
                $table->string('fax', 35)->nullable()->after('telefono2');
                $table->string('email', 200)->nullable()->after('fax');
                $table->string('rfc_curp', 18)->nullable()->after('email');
                $table->char('tipo_persona', 1)->nullable()->after('rfc_curp');
                $table->char('clave_regimen_fiscal', 3)->nullable()->after('tipo_persona');
                $table->string('tax_id', 40)->nullable()->after('clave_regimen_fiscal');
                $table->string('contacto', 50)->nullable()->after('tax_id');
                $table->unsignedBigInteger('via_embarque_id')->nullable()->after('contacto');
                $table->char('es_dir_ppal', 1)->default('N')->after('via_embarque_id');
                $table->char('usar_para_envios', 1)->default('S')->after('es_dir_ppal');
                $table->char('usar_para_facturar', 1)->default('S')->after('usar_para_envios');
                $table->string('gln', 20)->nullable()->after('usar_para_facturar');

                $table->unique('dir_cli_id');
                $table->index('cliente_id');
                $table->index(['cliente_id', 'es_dir_ppal']);
                $table->index(['cliente_id', 'usar_para_envios']);
                $table->index('codigo_postal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('user_addresses', 'dir_cli_id')) {
                $table->dropUnique(['dir_cli_id']);
                $table->dropIndex(['cliente_id']);
                $table->dropIndex(['cliente_id', 'es_dir_ppal']);
                $table->dropIndex(['cliente_id', 'usar_para_envios']);
                $table->dropIndex(['codigo_postal']);

                $table->dropColumn([
                    'dir_cli_id',
                    'cliente_id',
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
                ]);
            }
        });
    }
};
