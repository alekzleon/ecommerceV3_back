<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctos_ve_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docto_ve_local_id')->constrained('doctos_ve')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('docto_ve_det_id')->nullable()->unique();
            $table->unsignedBigInteger('docto_ve_id')->nullable();
            $table->string('clave_articulo', 20)->nullable();
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->decimal('unidades', 18, 5)->default(0);
            $table->decimal('unidades_compro', 18, 5)->default(0);
            $table->decimal('unidades_surt_de', 18, 5)->default(0);
            $table->decimal('unidades_a_surtir', 18, 5)->default(0);
            $table->decimal('precio_unitario', 18, 6)->default(0);
            $table->decimal('pctje_dscto', 9, 6)->default(0);
            $table->decimal('dscto_art', 15, 2)->default(0);
            $table->decimal('pctje_dscto_cli', 9, 6)->default(0);
            $table->decimal('dscto_extra', 15, 2)->default(0);
            $table->decimal('pctje_dscto_vol', 9, 6)->default(0);
            $table->decimal('pctje_dscto_prom', 9, 6)->default(0);
            $table->decimal('precio_total_neto', 15, 2)->default(0);
            $table->char('precio_modificado', 1)->default('N');
            $table->decimal('pctje_comis', 9, 6)->default(0);
            $table->char('rol', 1)->default('N');
            $table->text('notas')->nullable();
            $table->unsignedBigInteger('tercero_co_id')->nullable();
            $table->unsignedInteger('posicion')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('docto_ve_local_id');
            $table->index('docto_ve_id');
            $table->index('product_id');
            $table->index('articulo_id');
            $table->index('clave_articulo');
            $table->index('tercero_co_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctos_ve_detalles');
    }
};
