<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('alias', 100);
            $table->string('contact_name', 150)->nullable();

            $table->string('street', 150);
            $table->string('external_number', 50)->nullable();
            $table->string('internal_number', 50)->nullable();

            $table->string('neighborhood', 150)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('state', 150)->nullable();

            $table->text('references')->nullable();
            $table->string('phone', 30)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index('user_id');
            $table->index('is_default');
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};