<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $trialDays = (int) config('plans.plans.free.trial_days', 14);

        DB::table('tenants')
            ->where('plan_key', 'free')
            ->where('subscription_status', 'active')
            ->whereNull('subscription_ends_at')
            ->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function ($tenant) use ($trialDays) {
                $createdAt = $tenant->created_at
                    ? Carbon::parse($tenant->created_at)
                    : now();

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update([
                        'subscription_ends_at' => $createdAt->addDays($trialDays),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('tenants')
            ->where('plan_key', 'free')
            ->where('subscription_status', 'active')
            ->update([
                'subscription_ends_at' => null,
                'updated_at' => now(),
            ]);
    }
};
