<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('folio_microsip', 40)->nullable()->unique()->after('orden_compra');
        });

        Schema::table('doctos_ve', function (Blueprint $table) {
            $table->string('folio_microsip', 40)->nullable()->unique()->after('folio');
        });

        DB::table('orders')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function ($order) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['folio_microsip' => 'W' . $order->id]);
            });

        DB::table('doctos_ve')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function ($doctoVe) {
                DB::table('doctos_ve')
                    ->where('id', $doctoVe->id)
                    ->update([
                        'folio_microsip' => 'W' . $doctoVe->id,
                        'folio' => 'W' . $doctoVe->id,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('doctos_ve', function (Blueprint $table) {
            $table->dropColumn('folio_microsip');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('folio_microsip');
        });
    }
};
