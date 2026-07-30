<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'base_unit_price_snapshot')) {
                $table->decimal('base_unit_price_snapshot', 12, 2)
                    ->default(0)
                    ->after('price_snapshot');
            }

            if (!Schema::hasColumn('cart_items', 'final_unit_price_snapshot')) {
                $table->decimal('final_unit_price_snapshot', 12, 2)
                    ->default(0)
                    ->after('base_unit_price_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $columns = collect([
                'final_unit_price_snapshot',
                'base_unit_price_snapshot',
            ])->filter(fn ($column) => Schema::hasColumn('cart_items', $column))->all();

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
