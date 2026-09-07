<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentProviderNotSupportedException;
use App\Models\Tenant;
use App\Models\TenantPaymentConnection;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use App\Services\Payments\Gateways\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    public function make(Tenant $tenant, string $provider): PaymentGatewayInterface
    {
        $provider = $this->normalizeProvider($provider);
        $connection = $tenant->paymentConnections()
            ->where('provider', $provider)
            ->first();

        return match ($provider) {
            TenantPaymentConnection::PROVIDER_MERCADOPAGO => new MercadoPagoGateway(
                tenant: $tenant,
                connection: $connection,
                config: config('services.mercadopago', [])
            ),
            default => throw new PaymentProviderNotSupportedException("El proveedor de pago [{$provider}] no está soportado."),
        };
    }

    protected function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
