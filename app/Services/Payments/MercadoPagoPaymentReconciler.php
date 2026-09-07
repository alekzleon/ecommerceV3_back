<?php

namespace App\Services\Payments;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MercadoPagoPaymentReconciler
{
    public function __construct(
        private MercadoPagoOAuthService $oauth,
        private MercadoPagoPaymentStatusMapper $statuses
    ) {
    }

    public function reconcile(Tenant $tenant, string $providerPaymentId): array
    {
        return $tenant->run(function () use ($tenant, $providerPaymentId) {
            $accessToken = $this->oauth->getValidAccessToken($tenant);

            try {
                $response = Http::withToken($accessToken)
                    ->connectTimeout((int) config('services.mercadopago.connect_timeout', 10))
                    ->timeout((int) config('services.mercadopago.timeout', 20))
                    ->get("https://api.mercadopago.com/v1/payments/{$providerPaymentId}");
            } catch (ConnectionException) {
                throw new HttpException(503, 'No fue posible consultar el pago en Mercado Pago.');
            }

            if (! $response->successful()) {
                throw new HttpException($response->serverError() ? 503 : 422, 'No fue posible validar el pago en Mercado Pago.');
            }

            $providerPayment = $response->json();
            $externalReference = data_get($providerPayment, 'external_reference');

            if (! is_array($providerPayment) || blank($externalReference)) {
                throw new HttpException(422, 'La respuesta de pago de Mercado Pago no es válida.');
            }

            $result = DB::transaction(function () use ($providerPaymentId, $providerPayment, $externalReference) {
                $payment = Payment::query()
                    ->where('provider', 'mercadopago')
                    ->where('external_reference', $externalReference)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    return ['result' => 'ignored', 'order' => null, 'transitioned_to_paid' => false];
                }

                $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $providerStatus = strtolower((string) data_get($providerPayment, 'status'));
                $status = $this->statuses->map($providerStatus);

                if ($payment->provider_payment_id && $payment->provider_payment_id !== $providerPaymentId) {
                    throw new HttpException(422, 'El pago no corresponde a la referencia registrada.');
                }

                if (filled($payment->provider_reference)
                    && filled(data_get($providerPayment, 'preference_id'))
                    && ! hash_equals((string) $payment->provider_reference, (string) data_get($providerPayment, 'preference_id'))) {
                    throw new HttpException(422, 'La preferencia de Mercado Pago no coincide.');
                }

                if ($status === Order::PAYMENT_PAID) {
                    $this->assertApprovedPaymentMatches($order, $payment, $providerPayment);
                }

                if ($order->payment_status === Order::PAYMENT_PAID && $status !== Order::PAYMENT_PAID) {
                    return ['result' => 'ignored_outdated', 'order' => $order, 'transitioned_to_paid' => false];
                }

                $confirmedPayload = array_filter([
                    'payment_id' => $providerPaymentId,
                    'provider_status' => $providerStatus,
                    'status_detail' => data_get($providerPayment, 'status_detail'),
                    'date_approved' => data_get($providerPayment, 'date_approved'),
                    'payment_method_id' => data_get($providerPayment, 'payment_method_id'),
                    'payment_type_id' => data_get($providerPayment, 'payment_type_id'),
                ], fn ($value) => filled($value));
                $payment->forceFill([
                    'provider_payment_id' => $providerPaymentId,
                    'provider_status' => $providerStatus,
                    'status' => $status,
                    'amount' => (float) data_get($providerPayment, 'transaction_amount', $payment->amount),
                    'currency' => strtoupper((string) data_get($providerPayment, 'currency_id', $payment->currency)),
                    'paid_at' => $status === Order::PAYMENT_PAID ? data_get($providerPayment, 'date_approved', now()) : $payment->paid_at,
                    'provider_payload' => array_merge($payment->provider_payload ?? [], $confirmedPayload),
                ])->save();

                $transitionedToPaid = $status === Order::PAYMENT_PAID && $order->payment_status !== Order::PAYMENT_PAID;
                if ($transitionedToPaid) {
                    $order->forceFill([
                        'status' => Order::STATUS_PAID,
                        'payment_status' => Order::PAYMENT_PAID,
                        'payment_method' => 'mercadopago',
                        'paid_at' => data_get($providerPayment, 'date_approved', now()),
                    ])->save();
                }

                return ['result' => 'processed', 'order' => $order->fresh(), 'transitioned_to_paid' => $transitionedToPaid];
            });

            if ($result['transitioned_to_paid']) {
                OrderPaid::dispatch($result['order'], 'mercadopago');
            }

            Log::info('Mercado Pago payment webhook processed.', [
                'tenant_id' => $tenant->id,
                'order_id' => $result['order']?->id,
                'payment_id' => $providerPaymentId,
                'provider_status' => data_get($providerPayment, 'status'),
                'result' => $result['result'],
            ]);

            return $result;
        });
    }

    public function reconcileByExternalReference(Tenant $tenant, string $externalReference): ?array
    {
        $providerPaymentId = $tenant->run(function () use ($tenant, $externalReference) {
            $accessToken = $this->oauth->getValidAccessToken($tenant);

            try {
                $response = Http::withToken($accessToken)
                    ->connectTimeout((int) config('services.mercadopago.connect_timeout', 10))
                    ->timeout((int) config('services.mercadopago.timeout', 20))
                    ->get('https://api.mercadopago.com/v1/payments/search', [
                        'external_reference' => $externalReference,
                        'sort' => 'date_created',
                        'criteria' => 'desc',
                    ]);
            } catch (ConnectionException) {
                throw new HttpException(503, 'No fue posible verificar el pago en Mercado Pago.');
            }

            if (! $response->successful()) {
                throw new HttpException(503, 'No fue posible verificar el pago en Mercado Pago.');
            }

            return data_get($response->json(), 'results.0.id');
        });

        return filled($providerPaymentId)
            ? $this->reconcile($tenant, (string) $providerPaymentId)
            : null;
    }

    private function assertApprovedPaymentMatches(Order $order, Payment $payment, array $providerPayment): void
    {
        abort_unless(
            $this->amountInCents(data_get($providerPayment, 'transaction_amount')) === $this->amountInCents($order->total),
            422,
            'El monto confirmado no coincide con el pedido.'
        );
        abort_unless(
            strtoupper((string) data_get($providerPayment, 'currency_id')) === strtoupper((string) $order->currency),
            422,
            'La moneda confirmada no coincide con el pedido.'
        );
        abort_unless(
            hash_equals((string) $payment->external_reference, (string) data_get($providerPayment, 'external_reference')),
            422,
            'La referencia externa no coincide con el pedido.'
        );
    }

    private function amountInCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
