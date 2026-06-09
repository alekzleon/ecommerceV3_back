<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variant_attributes', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->dropUnique('variant_attributes_slug_unique');
            $table->unique(['product_id', 'slug'], 'variant_attributes_product_slug_unique');
            $table->index(['product_id', 'sort_order'], 'variant_attributes_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('variant_attributes', function (Blueprint $table) {
            $table->dropIndex('variant_attributes_product_sort_index');
            $table->dropUnique('variant_attributes_product_slug_unique');
            $table->unique('slug', 'variant_attributes_slug_unique');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
