<?php

namespace App\Console\Commands;

use App\Enums\PaymentConnectionStatus;
use App\Exceptions\Payments\MercadoPagoOAuthException;
use App\Models\TenantPaymentConnection;
use App\Services\Payments\MercadoPagoOAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshMercadoPagoTokens extends Command
{
    protected $signature = 'payments:refresh-tokens';

    protected $description = 'Renueva preventivamente los tokens próximos a expirar de Mercado Pago.';

    public function handle(MercadoPagoOAuthService $oauth): int
    {
        $metrics = [
            'processed' => 0,
            'refreshed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'reauthorization_required' => 0,
        ];

        TenantPaymentConnection::query()
            ->where('provider', TenantPaymentConnection::PROVIDER_MERCADOPAGO)
            ->where('status', PaymentConnectionStatus::Connected->value)
            ->orderBy('id')
            ->each(function (TenantPaymentConnection $connection) use ($oauth, &$metrics) {
                $metrics['processed']++;

                if (! $oauth->shouldRefresh($connection)) {
                    $metrics['skipped']++;

                    return;
                }

                try {
                    $refreshed = $oauth->refreshDueConnection($connection);

                    if ($refreshed->expires_at?->gt(now()->addDays((int) config('payment_providers.mercadopago.refresh_before_days', 7)))) {
                        $metrics['refreshed']++;
                        Log::info('Mercado Pago token refreshed.', [
                            'tenant_id' => $refreshed->tenant_id,
                            'provider' => $refreshed->provider,
                            'expires_at' => $refreshed->expires_at?->toIso8601String(),
                        ]);
                    } else {
                        $metrics['skipped']++;
                    }
                } catch (MercadoPagoOAuthException $exception) {
                    if ($exception->reason() === 'reauthorization_required') {
                        $metrics['reauthorization_required']++;
                    } else {
                        $metrics['failed']++;
                    }

                    Log::warning('Mercado Pago token refresh failed.', [
                        'tenant_id' => $connection->tenant_id,
                        'provider' => $connection->provider,
                        'reason' => $exception->reason(),
                    ]);
                } catch (\Throwable $exception) {
                    $metrics['failed']++;
                    Log::error('Mercado Pago token refresh failed unexpectedly.', [
                        'tenant_id' => $connection->tenant_id,
                        'provider' => $connection->provider,
                        'exception' => $exception::class,
                    ]);
                }
            });

        foreach ($metrics as $name => $value) {
            $this->line(ucfirst(str_replace('_', ' ', $name)).": {$value}");
        }

        return self::SUCCESS;
    }
}
