<?php

namespace App\Services\Payments;

use App\Models\StripeConnectWebhookEvent;
use App\Models\TenantStripeAccount;
use Illuminate\Support\Facades\DB;

class StripeConnectWebhookService
{
    public function __construct(
        protected StripeConnectService $stripeConnectService
    ) {
    }

    public function handle(string $payload, ?string $signatureHeader): array
    {
        $this->validateSignature($payload, $signatureHeader);

        $event = json_decode($payload, true);

        abort_unless(is_array($event), 400, 'Payload inválido.');

        return DB::transaction(function () use ($event) {
            $stripeEventId = (string) data_get($event, 'id');
            $type = (string) data_get($event, 'type');
            $stripeAccountId = $this->stripeAccountId($event);

            abort_if(blank($stripeEventId) || blank($type), 400, 'Evento inválido.');
            abort_if(blank($stripeAccountId), 400, 'El evento Connect no incluye account.');

            $connectAccount = TenantStripeAccount::query()
                ->where('stripe_account_id', $stripeAccountId)
                ->lockForUpdate()
                ->first();

            $webhookEvent = StripeConnectWebhookEvent::query()
                ->where('stripe_event_id', $stripeEventId)
                ->lockForUpdate()
                ->first();

            if ($webhookEvent && $webhookEvent->status === 'processed') {
                return [
                    'ok' => true,
                    'duplicate' => true,
                    'event_id' => $stripeEventId,
                    'type' => $type,
                    'stripe_account_id' => $stripeAccountId,
                    'tenant_id' => $webhookEvent->tenant_id,
                ];
            }

            $webhookEvent ??= StripeConnectWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'stripe_account_id' => $stripeAccountId,
                'tenant_id' => $connectAccount?->tenant_id,
                'type' => $type,
                'status' => 'processing',
                'payload' => $event,
            ]);

            $handled = false;

            if ($connectAccount && $type === 'account.updated') {
                $this->stripeConnectService->updateFromStripeAccountObject(
                    $connectAccount,
                    data_get($event, 'data.object', [])
                );
                $handled = true;
            }

            $webhookEvent->forceFill([
                'stripe_account_id' => $stripeAccountId,
                'tenant_id' => $connectAccount?->tenant_id,
                'status' => $connectAccount
                    ? ($handled ? 'processed' : 'received')
                    : 'ignored',
                'processed_at' => now(),
                'payload' => $event,
            ])->save();

            return [
                'ok' => true,
                'duplicate' => false,
                'event_id' => $stripeEventId,
                'type' => $type,
                'stripe_account_id' => $stripeAccountId,
                'tenant_id' => $connectAccount?->tenant_id,
                'status' => $webhookEvent->status,
            ];
        });
    }

    protected function stripeAccountId(array $event): ?string
    {
        return data_get($event, 'account')
            ?: data_get($event, 'data.object.account')
            ?: (data_get($event, 'type') === 'account.updated' ? data_get($event, 'data.object.id') : null);
    }

    protected function validateSignature(string $payload, ?string $signatureHeader): void
    {
        $secret = config('services.stripe.connect.webhook_secret');

        abort_if(blank($secret), 500, 'Stripe Connect webhook no está configurado.');
        abort_if(blank($signatureHeader), 400, 'Falta la firma de Stripe.');

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signatures = collect(explode(',', $signatureHeader))
            ->filter(fn (string $part) => str_starts_with($part, 'v1='))
            ->map(fn (string $part) => substr($part, 3));

        abort_if(blank($timestamp) || $signatures->isEmpty(), 400, 'Firma de Stripe inválida.');
        abort_if(abs(time() - (int) $timestamp) > 300, 400, 'Firma de Stripe expirada.');

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, (string) $secret);
        $valid = $signatures->contains(fn (string $signature) => hash_equals($expected, $signature));

        abort_unless($valid, 400, 'Firma de Stripe inválida.');
    }
}
