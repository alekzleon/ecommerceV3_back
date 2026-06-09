<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['promotion_id', 'user_id']);
            $table->index(['user_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_user');
    }
};