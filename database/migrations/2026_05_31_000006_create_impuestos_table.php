<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('impuesto_id')->unique();
            $table->unsignedBigInteger('tipo_impto_id');
            $table->string('nombre', 30);
            $table->char('tipo_calc', 1)->default('P');
            $table->decimal('pctje_impuesto', 9, 6)->default(0);
            $table->decimal('importe_unitario', 18, 6)->default(0);
            $table->string('unidad_impto', 20)->nullable();
            $table->char('es_predet', 1)->nullable()->default('N');
            $table->char('oculto', 1)->default('N');
            $table->boolean('causa_flujo_efectivo')->default(false);
            $table->string('cuenta_pend_en_ventas', 30)->nullable();
            $table->string('cuenta_en_ventas', 30)->nullable();
            $table->string('cuenta_pend_en_compras', 30)->nullable();
            $table->string('cuenta_en_compras', 30)->nullable();
            $table->char('tipo_iva', 1)->nullable();
            $table->string('usuario_creador', 31)->nullable();
            $table->timestamp('fecha_hora_creacion')->nullable();
            $table->string('usuario_aut_creacion', 31)->nullable();
            $table->string('usuario_ult_modif', 31)->nullable();
            $table->timestamp('fecha_hora_ult_modif')->nullable();
            $table->string('usuario_aut_modif', 31)->nullable();
            $table->timestamps();

            $table->index('tipo_impto_id');
            $table->index('nombre');
            $table->index('tipo_calc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuestos');
    }
};
