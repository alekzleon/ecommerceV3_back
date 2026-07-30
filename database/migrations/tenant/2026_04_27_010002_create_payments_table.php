<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('stripe');
            $table->string('status', 40)->default('pending');
            $table->string('payment_method', 40)->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('MXN');
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->unique(['provider', 'stripe_session_id']);
            $table->unique(['provider', 'stripe_payment_intent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
