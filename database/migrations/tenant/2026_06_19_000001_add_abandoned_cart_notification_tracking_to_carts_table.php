<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('abandoned_notified_at')->nullable()->after('abandoned_at');
            $table->timestamp('abandoned_email_sent_at')->nullable()->after('abandoned_notified_at');
            $table->timestamp('abandoned_whatsapp_sent_at')->nullable()->after('abandoned_email_sent_at');
            $table->timestamp('recovered_at')->nullable()->after('abandoned_whatsapp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn([
                'abandoned_notified_at',
                'abandoned_email_sent_at',
                'abandoned_whatsapp_sent_at',
                'recovered_at',
            ]);
        });
    }
};
