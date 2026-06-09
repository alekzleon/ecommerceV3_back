<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_microsip', 100)->nullable()->after('email');
            $table->string('status', 50)->default('activo')->after('id_microsip');
            $table->text('default_shipping_address')->nullable()->after('status');

            $table->index('id_microsip');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['id_microsip']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'id_microsip',
                'status',
                'default_shipping_address',
            ]);
        });
    }
};
