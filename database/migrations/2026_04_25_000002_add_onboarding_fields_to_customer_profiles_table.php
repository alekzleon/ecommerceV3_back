<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('commercial_name', 255)->nullable()->after('user_id');
            $table->string('whatsapp', 30)->nullable()->after('commercial_name');
            $table->string('onboarding_status', 50)->default('invited')->after('status');

            $table->index('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropIndex(['onboarding_status']);
            $table->dropColumn(['commercial_name', 'whatsapp', 'onboarding_status']);
        });
    }
};
