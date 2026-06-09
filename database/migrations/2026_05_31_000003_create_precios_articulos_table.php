<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('precio_articulo_id')->unique();
            $table->string('articulo_id', 100);
            $table->unsignedInteger('precio_empresa_id');
            $table->decimal('precio', 18, 6)->default(0);
            $table->unsignedInteger('moneda_id');
            $table->decimal('margen', 9, 6)->default(0);
            $table->decimal('markup', 10, 6)->default(0);
            $table->timestamp('fecha_hora_ult_modif')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('articulo_id');
            $table->index('precio_empresa_id');
            $table->index('moneda_id');
            $table->index(['product_id', 'precio_empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_articulos');
    }
};
