<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        DB::statement('ALTER TABLE cart_items MODIFY quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cart_items MODIFY quantity INT UNSIGNED NOT NULL DEFAULT 1');
    }
};
