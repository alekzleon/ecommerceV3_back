<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'discount_snapshot')) {
                $table->decimal('discount_snapshot', 12, 2)->default(0)->after('line_subtotal_snapshot');
            }

            if (!Schema::hasColumn('cart_items', 'line_discount_snapshot')) {
                $table->decimal('line_discount_snapshot', 12, 2)->default(0)->after('discount_snapshot');
            }

            if (!Schema::hasColumn('cart_items', 'promotion_id')) {
                $table->foreignId('promotion_id')
                    ->nullable()
                    ->after('line_discount_snapshot')
                    ->constrained('promotions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('cart_items', 'promotion_type')) {
                $table->string('promotion_type', 50)->nullable()->after('promotion_id');
            }

            if (!Schema::hasColumn('cart_items', 'promotion_name_snapshot')) {
                $table->string('promotion_name_snapshot')->nullable()->after('promotion_type');
            }

            if (!Schema::hasColumn('cart_items', 'promotion_snapshot')) {
                $table->json('promotion_snapshot')->nullable()->after('promotion_name_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'promotion_id')) {
                $table->dropConstrainedForeignId('promotion_id');
            }

            $columns = collect([
                'promotion_type',
                'promotion_name_snapshot',
                'promotion_snapshot',
                'line_discount_snapshot',
                'discount_snapshot',
            ])->filter(fn ($column) => Schema::hasColumn('cart_items', $column))->all();

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
