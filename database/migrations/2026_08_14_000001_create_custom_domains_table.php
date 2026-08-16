<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('hostname', 255)->unique();
            $table->string('cloudflare_hostname_id')->nullable()->unique();
            $table->string('app_status')->default('pending_dns');
            $table->string('hostname_status')->default('pending_dns');
            $table->string('ssl_status')->nullable();
            $table->string('cname_target')->nullable();
            $table->json('verification_errors')->nullable();
            $table->json('cloudflare_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
