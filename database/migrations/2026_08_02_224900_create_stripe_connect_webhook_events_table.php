<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_connect_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('stripe_account_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('type');
            $table->string('status', 40)->default('processing');
            $table->timestamp('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['type', 'status'], 'stripe_connect_events_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_webhook_events');
    }
};
