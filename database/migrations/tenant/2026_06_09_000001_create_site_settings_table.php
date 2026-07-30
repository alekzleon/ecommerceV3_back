<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_title')->nullable();
            $table->string('logo_disk', 50)->default('public');
            $table->string('logo_path')->nullable();
            $table->string('favicon_disk', 50)->default('public');
            $table->string('favicon_path')->nullable();
            $table->json('contact_numbers')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->json('social_links')->nullable();
            $table->string('forms_recipient_email')->nullable();
            $table->json('meta')->nullable();
            $table->text('google_analytics_pixel')->nullable();
            $table->text('meta_pixel')->nullable();
            $table->string('og_image_disk', 50)->default('public');
            $table->string('og_image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
