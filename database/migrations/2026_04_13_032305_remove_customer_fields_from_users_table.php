<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Si existen índices por separado, primero los quitamos
            if (Schema::hasColumn('users', 'id_microsip')) {
                try {
                    $table->dropIndex(['id_microsip']);
                } catch (\Throwable $e) {
                    // Ignorar si el índice no existe o tiene otro nombre
                }
            }

            if (Schema::hasColumn('users', 'status')) {
                try {
                    $table->dropIndex(['status']);
                } catch (\Throwable $e) {
                    // Ignorar si el índice no existe o tiene otro nombre
                }
            }

            $columnsToDrop = [];

            if (Schema::hasColumn('users', 'id_microsip')) {
                $columnsToDrop[] = 'id_microsip';
            }

            if (Schema::hasColumn('users', 'status')) {
                $columnsToDrop[] = 'status';
            }

            if (Schema::hasColumn('users', 'default_shipping_address')) {
                $columnsToDrop[] = 'default_shipping_address';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'id_microsip')) {
                $table->string('id_microsip', 100)->nullable()->after('email');
                $table->index('id_microsip');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 50)->default('activo')->after('id_microsip');
                $table->index('status');
            }

            if (!Schema::hasColumn('users', 'default_shipping_address')) {
                $table->text('default_shipping_address')->nullable()->after('status');
            }
        });
    }
};