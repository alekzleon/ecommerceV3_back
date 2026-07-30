<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('sales_channel', 40)->default('online_store')->after('source')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('sales_channel', 40)->default('online_store')->after('orden_compra')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sales_channel');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('sales_channel');
        });
    }
};
