<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('id_microsip', 100)->nullable();
            $table->string('status', 50)->default('activo');

            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->unsignedInteger('credit_days')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->unsignedBigInteger('assigned_seller_id')->nullable();
            $table->string('route', 100)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index('id_microsip');
            $table->index('status');
            $table->index('assigned_seller_id');
            $table->index('route');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};