<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('payment_method');
            $table->string('external_reference')->nullable()->after('provider_reference');
            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_reference']);
            $table->dropIndex(['order_id', 'provider', 'status']);
            $table->dropColumn(['provider_reference', 'external_reference']);
        });
    }
};
