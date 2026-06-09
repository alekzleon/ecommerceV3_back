<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_item_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'gift_item_id']);
            $table->index('promotion_id');
            $table->index('gift_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_item_promotion');
    }
};
