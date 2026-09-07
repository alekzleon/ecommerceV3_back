<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('plan_key', 80)->default('*');
            $table->string('fee_type', 20)->default('none');
            $table->decimal('fee_value', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'plan_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_fee_settings');
    }
};
