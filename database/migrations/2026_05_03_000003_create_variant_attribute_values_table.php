<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_attribute_id')
                ->constrained('variant_attributes')
                ->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['variant_attribute_id', 'slug']);
            $table->index(['variant_attribute_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
    }
};
