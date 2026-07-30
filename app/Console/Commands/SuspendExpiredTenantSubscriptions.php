<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class SuspendExpiredTenantSubscriptions extends Command
{
    protected $signature = 'tenants:suspend-expired-subscriptions';

    protected $description = 'Suspende tiendas con suscripcion vencida.';

    public function handle(): int
    {
        $count = 0;

        Tenant::query()
            ->where('subscription_status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<=', now())
            ->chunkById(100, function ($tenants) use (&$count) {
                foreach ($tenants as $tenant) {
                    $tenant->suspendSubscription(Tenant::STATUS_SUSPENDED);
                    $count++;
                }
            });

        $this->info("Tiendas suspendidas: {$count}");

        return self::SUCCESS;
    }
}
