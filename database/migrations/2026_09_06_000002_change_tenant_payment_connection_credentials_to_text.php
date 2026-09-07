<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_payment_connections MODIFY credentials LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenant_payment_connections MODIFY credentials JSON NULL');
    }
};
