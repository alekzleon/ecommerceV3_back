<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->char('es_almacenable', 1)->nullable()->after('microsip_id');
            $table->char('es_juego', 1)->nullable()->after('es_almacenable');
            $table->char('estatus', 1)->nullable()->index()->after('es_juego');
            $table->string('causa_susp')->nullable()->after('estatus');
            $table->date('fecha_susp')->nullable()->after('causa_susp');
            $table->char('imprimir_comp', 1)->nullable()->after('fecha_susp');
            $table->char('permitir_agregar_comp', 1)->nullable()->after('imprimir_comp');
            $table->unsignedBigInteger('linea_articulo_id')->nullable()->index()->after('permitir_agregar_comp');
            $table->string('unidad_venta', 100)->nullable()->after('linea_articulo_id');
            $table->string('unidad_compra', 100)->nullable()->after('unidad_venta');
            $table->decimal('contenido_unidad_compra', 12, 5)->nullable()->after('unidad_compra');
            $table->decimal('peso_unitario', 12, 5)->nullable()->after('contenido_unidad_compra');
            $table->char('es_peso_variable', 1)->nullable()->after('peso_unitario');
            $table->char('seguimiento', 1)->nullable()->after('es_peso_variable');
            $table->unsignedInteger('dias_garantia')->nullable()->after('seguimiento');
            $table->char('es_importado', 1)->nullable()->after('dias_garantia');
            $table->char('es_siempre_importado', 1)->nullable()->after('es_importado');
            $table->decimal('pctje_arancel', 12, 6)->nullable()->after('es_siempre_importado');
            $table->text('notas_compras')->nullable()->after('pctje_arancel');
            $table->char('imprimir_notas_compras', 1)->nullable()->after('notas_compras');
            $table->text('notas_ventas')->nullable()->after('imprimir_notas_compras');
            $table->char('imprimir_notas_ventas', 1)->nullable()->after('notas_ventas');
            $table->char('es_precio_variable', 1)->nullable()->after('imprimir_notas_ventas');
            $table->string('cuenta_almacen', 100)->nullable()->after('es_precio_variable');
            $table->string('cuenta_costo_venta', 100)->nullable()->after('cuenta_almacen');
            $table->string('cuenta_ventas', 100)->nullable()->after('cuenta_costo_venta');
            $table->string('cuenta_dscto_ventas', 100)->nullable()->after('cuenta_ventas');
            $table->string('cuenta_devol_ventas', 100)->nullable()->after('cuenta_dscto_ventas');
            $table->string('cuenta_compras', 100)->nullable()->after('cuenta_devol_ventas');
            $table->string('cuenta_devol_compras', 100)->nullable()->after('cuenta_compras');
            $table->char('aplicar_factor_venta', 1)->nullable()->after('cuenta_devol_compras');
            $table->decimal('factor_venta', 12, 5)->nullable()->after('aplicar_factor_venta');
            $table->char('red_precio_con_impto', 1)->nullable()->after('factor_venta');
            $table->decimal('factor_red_precio_con_impto', 12, 6)->nullable()->after('red_precio_con_impto');
            $table->string('usuario_creador', 100)->nullable()->after('factor_red_precio_con_impto');
            $table->dateTime('fecha_hora_creacion')->nullable()->after('usuario_creador');
            $table->string('usuario_aut_creacion', 100)->nullable()->after('fecha_hora_creacion');
            $table->string('usuario_ult_modif', 100)->nullable()->after('usuario_aut_creacion');
            $table->dateTime('fecha_hora_ult_modif')->nullable()->after('usuario_ult_modif');
            $table->string('usuario_aut_modif', 100)->nullable()->after('fecha_hora_ult_modif');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['estatus']);
            $table->dropIndex(['linea_articulo_id']);
            $table->dropColumn([
                'es_almacenable',
                'es_juego',
                'estatus',
                'causa_susp',
                'fecha_susp',
                'imprimir_comp',
                'permitir_agregar_comp',
                'linea_articulo_id',
                'unidad_venta',
                'unidad_compra',
                'contenido_unidad_compra',
                'peso_unitario',
                'es_peso_variable',
                'seguimiento',
                'dias_garantia',
                'es_importado',
                'es_siempre_importado',
                'pctje_arancel',
                'notas_compras',
                'imprimir_notas_compras',
                'notas_ventas',
                'imprimir_notas_ventas',
                'es_precio_variable',
                'cuenta_almacen',
                'cuenta_costo_venta',
                'cuenta_ventas',
                'cuenta_dscto_ventas',
                'cuenta_devol_ventas',
                'cuenta_compras',
                'cuenta_devol_compras',
                'aplicar_factor_venta',
                'factor_venta',
                'red_precio_con_impto',
                'factor_red_precio_con_impto',
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
