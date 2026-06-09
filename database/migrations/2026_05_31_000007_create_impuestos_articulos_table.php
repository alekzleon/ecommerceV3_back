<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuestos_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('impuesto_art_id')->unique();
            $table->string('articulo_id', 100);
            $table->unsignedBigInteger('impuesto_id');
            $table->decimal('unidades_impuesto', 18, 5)->default(0);
            $table->char('tipo_seleccion', 1)->default('T');
            $table->unsignedBigInteger('conjunto_sucursales_id')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('articulo_id');
            $table->index('impuesto_id');
            $table->index(['product_id', 'impuesto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuestos_articulos');
    }
};
