<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('precio_empresa_id')->unique();
            $table->string('nombre', 30);
            $table->char('id_interno', 1)->nullable();
            $table->boolean('act_automatica')->default(false);
            $table->unsignedInteger('precio_empresa_act_autc')->nullable();
            $table->decimal('porcentaje', 9, 6)->default(0);
            $table->boolean('usar_tabla_factores')->default(false);
            $table->decimal('factor_redondeo', 18, 6)->default(0);
            $table->boolean('agregar_precios')->default(false);
            $table->smallInteger('posicion')->default(0);
            $table->string('usuario_creador', 31)->nullable();
            $table->timestamp('fecha_hora_creacion')->nullable();
            $table->string('usuario_aut_creacion', 31)->nullable();
            $table->string('usuario_ult_modif', 31)->nullable();
            $table->timestamp('fecha_hora_ult_modif')->nullable();
            $table->string('usuario_aut_modif', 31)->nullable();
            $table->timestamps();

            $table->index('nombre');
            $table->index('id_interno');
            $table->index('posicion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_empresa');
    }
};
