<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('type', 50);
            $table->boolean('is_active')->default(true);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('requires_login')->default(true);
            $table->boolean('is_general')->default(true);
            $table->boolean('is_combinable')->default(false);

            $table->unsignedInteger('priority')->nullable();

            $table->string('image_path')->nullable();

            $table->boolean('applies_to_specific_customers')->default(false);

            $table->boolean('has_limit_per_user')->default(false);
            $table->unsignedInteger('limit_per_user')->nullable();

            $table->boolean('has_global_limit')->default(false);
            $table->unsignedInteger('global_limit')->nullable();

            $table->unsignedInteger('usage_count')->default(0);

            $table->json('config')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['type']);
            $table->index(['is_general']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};