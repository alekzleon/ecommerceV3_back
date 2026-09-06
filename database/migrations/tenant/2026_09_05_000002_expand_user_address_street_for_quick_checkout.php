<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_addresses MODIFY street TEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_addresses MODIFY street VARCHAR(150) NOT NULL');
    }
};
