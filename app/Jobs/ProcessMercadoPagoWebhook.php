<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Payments\MercadoPagoPaymentReconciler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProcessMercadoPagoWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;
    public array $backoff = [30, 120, 600];

    public function __construct(public string $tenantId, public string $paymentId)
    {
    }

    public function handle(MercadoPagoPaymentReconciler $reconciler): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant) {
            return;
        }

        try {
            $reconciler->reconcile($tenant, $this->paymentId);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() >= 500) {
                throw $exception;
            }

            Log::warning('Mercado Pago webhook ignored after reconciliation validation.', [
                'tenant_id' => $this->tenantId,
                'payment_id' => $this->paymentId,
                'status' => $exception->getStatusCode(),
            ]);
        }
    }
}
