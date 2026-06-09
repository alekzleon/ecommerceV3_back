<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE customer_profiles
            MODIFY credit_limit decimal(12, 2) NULL,
            MODIFY credit_days int unsigned NULL,
            MODIFY discount_percent decimal(5, 2) NULL
        ');

        DB::statement('
            ALTER TABLE customer_pfr_profiles
            MODIFY price_list varchar(50) NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE customer_profiles
            MODIFY credit_limit decimal(12, 2) NOT NULL DEFAULT 0,
            MODIFY credit_days int unsigned NOT NULL DEFAULT 0,
            MODIFY discount_percent decimal(5, 2) NOT NULL DEFAULT 0
        ');

        DB::statement('
            ALTER TABLE customer_pfr_profiles
            MODIFY price_list varchar(50) NOT NULL DEFAULT "Lista 3"
        ');
    }
};
