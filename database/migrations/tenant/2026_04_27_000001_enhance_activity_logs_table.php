<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('actor_type')->nullable()->after('user_id');
            $table->string('summary')->nullable()->after('action');
            $table->json('metadata')->nullable()->after('new_values');
            $table->text('user_agent')->nullable()->after('ip');

            $table->index(['module', 'action']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['module', 'action']);
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropIndex(['user_id', 'created_at']);

            $table->dropColumn([
                'actor_type',
                'summary',
                'metadata',
                'user_agent',
            ]);
        });
    }
};
