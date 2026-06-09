<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipo_impto_id')->unique();
            $table->string('nombre', 30);
            $table->char('tipo', 1);
            $table->char('grava_otros_imptos', 1)->nullable()->default('N');
            $table->boolean('aplica_solo_sobre_impte_imp')->default(false);
            $table->char('id_interno', 1)->nullable();
            $table->char('es_predet', 1)->nullable()->default('N');
            $table->string('usuario_creador', 31)->nullable();
            $table->timestamp('fecha_hora_creacion')->nullable();
            $table->string('usuario_aut_creacion', 31)->nullable();
            $table->string('usuario_ult_modif', 31)->nullable();
            $table->timestamp('fecha_hora_ult_modif')->nullable();
            $table->string('usuario_aut_modif', 31)->nullable();
            $table->timestamps();

            $table->index('nombre');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_impuestos');
    }
};
