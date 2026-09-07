<?php

use App\Enums\PaymentConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_connections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider', 40);
            $table->string('provider_account_id')->nullable();
            $table->longText('credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 40)->default(PaymentConnectionStatus::Disconnected->value);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider']);
            $table->index(['provider', 'status']);
            $table->index('provider_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_connections');
    }
};
