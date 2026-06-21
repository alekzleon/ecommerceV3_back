<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variant_attributes', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('product_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_system')->default(false)->after('slug')->index();
        });

        $attributes = [
            'Color',
            'Talla',
            'Medida',
            'Presentación',
            'Capacidad',
            'Voltaje',
            'Material',
            'Empaque',
            'Modelo',
        ];

        foreach ($attributes as $index => $name) {
            DB::table('variant_attributes')->updateOrInsert(
                [
                    'product_id' => null,
                    'slug' => Str::slug($name),
                ],
                [
                    'user_id' => null,
                    'name' => $name,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('variant_attributes', function (Blueprint $table) {
            $table->dropColumn('is_system');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
