<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_cli_cli', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('precio_cli_cli_id')->unique();
            $table->unsignedBigInteger('politica_precios_cli_id')->nullable();
            $table->string('clave_cliente', 20)->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('precio_empresa_id');
            $table->unsignedBigInteger('politica_dscto_art_cli_id');
            $table->timestamps();

            $table->index('politica_precios_cli_id');
            $table->index('clave_cliente');
            $table->index('cliente_id');
            $table->index('precio_empresa_id');
            $table->index('politica_dscto_art_cli_id');
            $table->index(['cliente_id', 'precio_empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_cli_cli');
    }
};
