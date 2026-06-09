<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_pfr_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('commercial_name');
            $table->string('purchasing_contact_name');
            $table->string('quote_email')->nullable();
            $table->string('business_phone', 30);
            $table->string('secondary_contact_name')->nullable();
            $table->string('secondary_phone', 30)->nullable();
            $table->string('business_activity');
            $table->string('payment_method', 50);
            $table->string('price_list', 50)->default('Lista 3');
            $table->boolean('requires_invoice');

            $table->string('fiscal_name')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('fiscal_street')->nullable();
            $table->string('fiscal_external_number', 50)->nullable();
            $table->string('fiscal_internal_number', 50)->nullable();
            $table->string('fiscal_zip_code', 20)->nullable();
            $table->string('fiscal_neighborhood')->nullable();
            $table->string('fiscal_city')->nullable();
            $table->string('fiscal_state')->nullable();
            $table->string('xml_email')->nullable();
            $table->string('cfdi_use')->nullable();
            $table->string('tax_certificate_disk', 50)->nullable();
            $table->string('tax_certificate_path')->nullable();
            $table->string('tax_certificate_original_name')->nullable();
            $table->string('tax_certificate_mime', 100)->nullable();
            $table->unsignedBigInteger('tax_certificate_size')->nullable();

            $table->boolean('delivery_same_as_fiscal')->default(true);
            $table->text('delivery_address')->nullable();
            $table->string('delivery_schedule')->nullable();
            $table->text('delivery_observations')->nullable();
            $table->text('distintivo_h')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index('rfc');
            $table->index('requires_invoice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_pfr_profiles');
    }
};
