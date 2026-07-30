<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->unsignedBigInteger('linea_articulo_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('grupo_linea_id')->nullable()->after('category_id');
            $table->string('cuenta_almacen', 30)->nullable()->after('slug');
            $table->string('cuenta_costo_venta', 30)->nullable()->after('cuenta_almacen');
            $table->string('cuenta_ventas', 30)->nullable()->after('cuenta_costo_venta');
            $table->string('cuenta_dscto_ventas', 30)->nullable()->after('cuenta_ventas');
            $table->string('cuenta_devol_ventas', 30)->nullable()->after('cuenta_dscto_ventas');
            $table->string('cuenta_compras', 30)->nullable()->after('cuenta_devol_ventas');
            $table->string('cuenta_devol_compras', 30)->nullable()->after('cuenta_compras');
            $table->char('aplicar_factor_venta', 1)->default('N')->after('cuenta_devol_compras');
            $table->decimal('factor_venta', 18, 5)->default(0)->after('aplicar_factor_venta');
            $table->char('es_predet', 1)->nullable()->default('N')->after('factor_venta');
            $table->char('oculto', 1)->default('N')->after('es_predet');
            $table->string('usuario_creador', 31)->nullable()->after('is_active');
            $table->timestamp('fecha_hora_creacion')->nullable()->after('usuario_creador');
            $table->string('usuario_aut_creacion', 31)->nullable()->after('fecha_hora_creacion');
            $table->string('usuario_ult_modif', 31)->nullable()->after('usuario_aut_creacion');
            $table->timestamp('fecha_hora_ult_modif')->nullable()->after('usuario_ult_modif');
            $table->string('usuario_aut_modif', 31)->nullable()->after('fecha_hora_ult_modif');

            $table->index('grupo_linea_id');
            $table->index('oculto');
            $table->index('es_predet');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropIndex(['grupo_linea_id']);
            $table->dropIndex(['oculto']);
            $table->dropIndex(['es_predet']);
            $table->dropUnique(['linea_articulo_id']);
            $table->dropColumn([
                'linea_articulo_id',
                'grupo_linea_id',
                'cuenta_almacen',
                'cuenta_costo_venta',
                'cuenta_ventas',
                'cuenta_dscto_ventas',
                'cuenta_devol_ventas',
                'cuenta_compras',
                'cuenta_devol_compras',
                'aplicar_factor_venta',
                'factor_venta',
                'es_predet',
                'oculto',
                'usuario_creador',
                'fecha_hora_creacion',
                'usuario_aut_creacion',
                'usuario_ult_modif',
                'fecha_hora_ult_modif',
                'usuario_aut_modif',
            ]);
        });
    }
};
