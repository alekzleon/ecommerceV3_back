<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_payment_id')->nullable()->after('provider_reference');
            $table->string('provider_status', 60)->nullable()->after('provider_payment_id');
            $table->unique(['provider', 'provider_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_payment_id']);
            $table->dropColumn(['provider_payment_id', 'provider_status']);
        });
    }
};
