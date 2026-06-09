<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_product_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'product_variant_id']);
            $table->index(['product_variant_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product_variant');
    }
};
