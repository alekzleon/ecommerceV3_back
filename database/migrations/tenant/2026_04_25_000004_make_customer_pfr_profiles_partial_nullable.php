<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE customer_pfr_profiles
            MODIFY commercial_name varchar(255) NULL,
            MODIFY purchasing_contact_name varchar(255) NULL,
            MODIFY business_phone varchar(30) NULL,
            MODIFY business_activity varchar(255) NULL,
            MODIFY payment_method varchar(50) NULL,
            MODIFY requires_invoice tinyint(1) NULL,
            MODIFY delivery_same_as_fiscal tinyint(1) NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE customer_pfr_profiles
            MODIFY commercial_name varchar(255) NOT NULL,
            MODIFY purchasing_contact_name varchar(255) NOT NULL,
            MODIFY business_phone varchar(30) NOT NULL,
            MODIFY business_activity varchar(255) NOT NULL,
            MODIFY payment_method varchar(50) NOT NULL,
            MODIFY requires_invoice tinyint(1) NOT NULL,
            MODIFY delivery_same_as_fiscal tinyint(1) NOT NULL DEFAULT 1
        ');
    }
};
