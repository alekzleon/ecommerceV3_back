<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_stripe_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('stripe_account_id')->nullable()->unique();
            $table->string('account_type', 40)->default('standard');
            $table->string('connect_status', 40)->default('not_connected');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->string('disabled_reason')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('default_currency', 10)->nullable();
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_eventually_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('onboarding_started_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id', 'tenant_stripe_accounts_tenant_id_unique');
            $table->index(['connect_status', 'charges_enabled', 'payouts_enabled'], 'tenant_stripe_accounts_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stripe_accounts');
    }
};
