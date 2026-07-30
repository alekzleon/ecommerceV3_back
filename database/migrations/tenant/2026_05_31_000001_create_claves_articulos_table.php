<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('clave_articulo_id', 100)->unique();
            $table->string('clave_articulo', 255);
            $table->string('articulo_id', 100);
            $table->unsignedInteger('rol_clave_art_id')->nullable();
            $table->decimal('contenido_empaque', 12, 5)->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('articulo_id');
            $table->index('rol_clave_art_id');
            $table->index(['product_id', 'rol_clave_art_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_articulos');
    }
};
