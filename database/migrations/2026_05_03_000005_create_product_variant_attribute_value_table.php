<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_attribute_value', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('variant_attribute_value_id');
            $table->timestamps();

            $table->foreign('product_variant_id', 'pvav_variant_fk')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();
            $table->foreign('variant_attribute_value_id', 'pvav_value_fk')
                ->references('id')
                ->on('variant_attribute_values')
                ->cascadeOnDelete();
            $table->unique(['product_variant_id', 'variant_attribute_value_id'], 'product_variant_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_value');
    }
};
