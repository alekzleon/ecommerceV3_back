<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('clave_articulo_id_snapshot', 100)->nullable()->after('sku_snapshot');
            $table->string('clave_articulo_snapshot', 255)->nullable()->after('clave_articulo_id_snapshot');
            $table->unsignedInteger('rol_clave_art_id_snapshot')->nullable()->after('clave_articulo_snapshot');
            $table->decimal('contenido_empaque_snapshot', 12, 5)->nullable()->after('rol_clave_art_id_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'clave_articulo_id_snapshot',
                'clave_articulo_snapshot',
                'rol_clave_art_id_snapshot',
                'contenido_empaque_snapshot',
            ]);
        });
    }
};
