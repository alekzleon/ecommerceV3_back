<?php

use App\Enums\CartStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 30)->default(CartStatus::ACTIVE->value);
            $table->string('currency', 10)->default('MXN');

            $table->unsignedInteger('items_count')->default(0);

            $table->decimal('subtotal_snapshot', 12, 2)->default(0);
            $table->decimal('discount_snapshot', 12, 2)->default(0);
            $table->decimal('tax_snapshot', 12, 2)->default(0);
            $table->decimal('total_snapshot', 12, 2)->default(0);

            $table->string('source', 30)->nullable();

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();

            $table->unsignedBigInteger('order_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('last_activity_at');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
