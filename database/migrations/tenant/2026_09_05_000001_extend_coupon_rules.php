<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_combinable')->default(false)->after('is_general');
            $table->unsignedInteger('per_user_usage_limit')->nullable()->after('usage_limit');
            $table->foreignId('trigger_coupon_id')->nullable()->after('usage_count')->constrained('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trigger_coupon_id');
            $table->dropColumn(['is_combinable', 'per_user_usage_limit']);
        });
    }
};
