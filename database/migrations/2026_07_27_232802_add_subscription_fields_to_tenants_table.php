<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_key', 50)->default('free')->after('id');
            $table->string('subscription_status', 50)->default('active')->after('plan_key');
            $table->timestamp('subscription_started_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->timestamp('suspended_at')->nullable()->after('subscription_ends_at');
            $table->string('payment_provider', 50)->nullable()->after('suspended_at');
            $table->string('provider_customer_id')->nullable()->after('payment_provider');
            $table->string('provider_subscription_id')->nullable()->after('provider_customer_id');
            $table->timestamp('last_payment_at')->nullable()->after('provider_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'plan_key',
                'subscription_status',
                'subscription_started_at',
                'subscription_ends_at',
                'suspended_at',
                'payment_provider',
                'provider_customer_id',
                'provider_subscription_id',
                'last_payment_at',
            ]);
        });
    }
};
