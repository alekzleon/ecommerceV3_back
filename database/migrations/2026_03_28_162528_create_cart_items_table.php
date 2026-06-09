<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\CartItemStatus;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku_snapshot', 120)->nullable();
            $table->string('name_snapshot');
            $table->string('brand_snapshot')->nullable();
            $table->string('image_snapshot')->nullable();
            $table->string('category_snapshot')->nullable();
            $table->string('family_snapshot')->nullable();

            $table->decimal('price_snapshot', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_subtotal_snapshot', 12, 2)->default(0);

            $table->string('status', 30)->default(CartItemStatus::ACTIVE->value);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('cart_id');
            $table->index('product_id');
            $table->index('status');
            $table->index(['cart_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
