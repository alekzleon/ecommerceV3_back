<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clave_cliente_id')->unique();
            $table->string('clave_cliente', 20);
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('rol_clave_cli_id');
            $table->timestamps();

            $table->index('clave_cliente');
            $table->index('cliente_id');
            $table->index('rol_clave_cli_id');
            $table->index(['cliente_id', 'rol_clave_cli_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_clientes');
    }
};
