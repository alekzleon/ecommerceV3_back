<?php

namespace App\Services\Payments;

use App\Models\Tenant;
use App\Models\TenantStripeAccount;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeConnectService
{
    public function createOrRetrieveAccount(Tenant $tenant, ?User $user = null): TenantStripeAccount
    {
        $connectAccount = $tenant->stripeAccount ?: $tenant->stripeAccount()->create([
            'tenant_id' => $tenant->id,
            'account_type' => config('services.stripe.connect.account_type', 'standard'),
            'connect_status' => TenantStripeAccount::STATUS_NOT_CONNECTED,
        ]);

        if (filled($connectAccount->stripe_account_id)) {
            return $this->syncAccount($connectAccount);
        }

        $secretKey = $this->secretKey();
        $payload = [
            'type' => config('services.stripe.connect.account_type', 'standard'),
            'email' => $user?->email ?: data_get($tenant->data, 'owner_email'),
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'platform' => 'cloudishop',
            ],
            'business_profile' => [
                'name' => data_get($tenant->data, 'store_name', $tenant->id),
                'url' => $this->tenantUrl($tenant),
            ],
        ];

        try {
            $account = Http::asForm()
                ->withToken($secretKey)
                ->timeout(20)
                ->post('https://api.stripe.com/v1/accounts', $this->flatten($payload))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible crear la cuenta Connect de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        return $this->fillFromStripeAccount($connectAccount, $account, [
            'stripe_account_id' => data_get($account, 'id'),
            'connect_status' => TenantStripeAccount::STATUS_ONBOARDING_PENDING,
        ]);
    }

    public function createOnboardingLink(TenantStripeAccount $connectAccount, string $returnUrl, string $refreshUrl): array
    {
        abort_if(blank($connectAccount->stripe_account_id), 422, 'La tienda no tiene cuenta Stripe Connect.');

        try {
            $link = Http::asForm()
                ->withToken($this->secretKey())
                ->timeout(20)
                ->post('https://api.stripe.com/v1/account_links', [
                    'account' => $connectAccount->stripe_account_id,
                    'return_url' => $returnUrl,
                    'refresh_url' => $refreshUrl,
                    'type' => 'account_onboarding',
                    'collection_options[fields]' => 'eventually_due',
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible generar el enlace de onboarding de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $connectAccount->forceFill([
            'connect_status' => $connectAccount->connect_status === TenantStripeAccount::STATUS_ENABLED
                ? TenantStripeAccount::STATUS_ENABLED
                : TenantStripeAccount::STATUS_ONBOARDING_PENDING,
            'onboarding_started_at' => $connectAccount->onboarding_started_at ?: now(),
        ])->save();

        return [
            'url' => data_get($link, 'url'),
            'expires_at' => data_get($link, 'expires_at'),
        ];
    }

    public function syncAccount(TenantStripeAccount $connectAccount): TenantStripeAccount
    {
        abort_if(blank($connectAccount->stripe_account_id), 422, 'La tienda no tiene cuenta Stripe Connect.');

        try {
            $account = Http::withToken($this->secretKey())
                ->timeout(20)
                ->get("https://api.stripe.com/v1/accounts/{$connectAccount->stripe_account_id}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible consultar la cuenta Connect de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        return $this->fillFromStripeAccount($connectAccount, $account);
    }

    public function updateFromStripeAccountObject(TenantStripeAccount $connectAccount, array $account): TenantStripeAccount
    {
        abort_if(blank($account), 400, 'Payload de cuenta Stripe inválido.');

        return $this->fillFromStripeAccount($connectAccount, $account);
    }

    public function payload(?TenantStripeAccount $connectAccount): array
    {
        if (! $connectAccount) {
            return [
                'connected' => false,
                'ready_for_charges' => false,
                'status' => TenantStripeAccount::STATUS_NOT_CONNECTED,
                'account' => null,
                'requirements' => [
                    'currently_due' => [],
                    'eventually_due' => [],
                'past_due' => [],
                'disabled_reason' => null,
                'disabled_reason_label' => null,
                'blocking' => [],
                'items' => [],
                ],
                'capabilities' => [],
            ];
        }

        $currentlyDue = $connectAccount->requirements_currently_due ?? [];
        $pastDue = $connectAccount->requirements_past_due ?? [];
        $eventuallyDue = $connectAccount->requirements_eventually_due ?? [];
        $blocking = collect($pastDue)->merge($currentlyDue)->unique()->values()->all();

        return [
            'connected' => filled($connectAccount->stripe_account_id),
            'ready_for_charges' => $connectAccount->isReadyForCharges(),
            'status' => $connectAccount->connect_status,
            'can_continue_onboarding' => filled($connectAccount->stripe_account_id) && ! $connectAccount->isReadyForCharges(),
            'account' => [
                'stripe_account_id' => $connectAccount->stripe_account_id,
                'account_type' => $connectAccount->account_type,
                'charges_enabled' => $connectAccount->charges_enabled,
                'payouts_enabled' => $connectAccount->payouts_enabled,
                'details_submitted' => $connectAccount->details_submitted,
                'country' => $connectAccount->country,
                'default_currency' => $connectAccount->default_currency,
                'last_synced_at' => $connectAccount->last_synced_at?->toISOString(),
                'onboarding_started_at' => $connectAccount->onboarding_started_at?->toISOString(),
                'onboarding_completed_at' => $connectAccount->onboarding_completed_at?->toISOString(),
            ],
            'requirements' => [
                'currently_due' => $connectAccount->requirements_currently_due ?? [],
                'eventually_due' => $connectAccount->requirements_eventually_due ?? [],
                'past_due' => $connectAccount->requirements_past_due ?? [],
                'disabled_reason' => $connectAccount->disabled_reason,
                'disabled_reason_label' => $this->disabledReasonLabel($connectAccount->disabled_reason),
                'blocking' => $blocking,
                'items' => $this->requirementItems($currentlyDue, $eventuallyDue, $pastDue),
            ],
            'capabilities' => data_get($connectAccount->provider_payload, 'capabilities', []),
        ];
    }

    protected function requirementItems(array $currentlyDue, array $eventuallyDue, array $pastDue): array
    {
        return collect($pastDue)
            ->map(fn (string $field) => $this->requirementItem($field, 'past_due'))
            ->merge(collect($currentlyDue)->map(fn (string $field) => $this->requirementItem($field, 'currently_due')))
            ->merge(collect($eventuallyDue)->map(fn (string $field) => $this->requirementItem($field, 'eventually_due')))
            ->unique(fn (array $item) => $item['field'] . ':' . $item['type'])
            ->values()
            ->all();
    }

    protected function requirementItem(string $field, string $type): array
    {
        return [
            'field' => $field,
            'type' => $type,
            'label' => $this->requirementLabel($field),
            'blocking' => in_array($type, ['currently_due', 'past_due'], true),
        ];
    }

    protected function requirementLabel(string $field): string
    {
        $labels = [
            'requirements.pending_verification' => 'Stripe esta verificando la informacion enviada',
            'requirements.past_due' => 'Hay informacion vencida o urgente por completar',
            'requirements.currently_due' => 'Hay informacion requerida para activar pagos',
            'requirements.eventually_due' => 'Hay informacion que Stripe podria pedir mas adelante',
            'business_profile.mcc' => 'Giro o categoria del negocio',
            'business_profile.url' => 'Sitio web de la tienda',
            'business_profile.product_description' => 'Descripcion de productos o servicios',
            'external_account' => 'Cuenta bancaria para depositos',
            'individual.first_name' => 'Nombre del representante',
            'individual.last_name' => 'Apellido del representante',
            'individual.email' => 'Correo del representante',
            'individual.phone' => 'Telefono del representante',
            'individual.dob.day' => 'Dia de nacimiento del representante',
            'individual.dob.month' => 'Mes de nacimiento del representante',
            'individual.dob.year' => 'Anio de nacimiento del representante',
            'individual.address.line1' => 'Direccion del representante',
            'individual.address.city' => 'Ciudad del representante',
            'individual.address.state' => 'Estado del representante',
            'individual.address.postal_code' => 'Codigo postal del representante',
            'individual.id_number' => 'Identificacion fiscal o personal',
            'company.name' => 'Razon social',
            'company.tax_id' => 'RFC o identificador fiscal',
            'company.phone' => 'Telefono de la empresa',
            'company.address.line1' => 'Direccion de la empresa',
            'company.address.city' => 'Ciudad de la empresa',
            'company.address.state' => 'Estado de la empresa',
            'company.address.postal_code' => 'Codigo postal de la empresa',
            'tos_acceptance.date' => 'Aceptacion de terminos de Stripe',
            'tos_acceptance.ip' => 'IP de aceptacion de terminos de Stripe',
        ];

        return $labels[$field] ?? str($field)
            ->replace(['.', '_'], ' ')
            ->headline()
            ->toString();
    }

    protected function disabledReasonLabel(?string $reason): ?string
    {
        if (blank($reason)) {
            return null;
        }

        $labels = [
            'requirements.pending_verification' => 'Stripe esta revisando la informacion de la cuenta. No se requiere accion por ahora; vuelve a consultar el estado en unos minutos.',
            'requirements.past_due' => 'Hay informacion vencida o urgente que el comercio debe completar en Stripe para poder recibir pagos.',
            'requirements.currently_due' => 'Falta informacion obligatoria para activar pagos. Continua la configuracion en Stripe.',
            'requirements.eventually_due' => 'Stripe necesita informacion adicional antes de habilitar completamente la cuenta.',
            'listed' => 'La cuenta fue marcada por Stripe y necesita revision desde el Dashboard de Stripe.',
            'rejected.fraud' => 'Stripe rechazo la cuenta por riesgo de fraude.',
            'rejected.terms_of_service' => 'Stripe rechazo la cuenta por incumplimiento de sus terminos de servicio.',
            'rejected.listed' => 'Stripe rechazo la cuenta porque aparece en una lista restringida.',
            'rejected.other' => 'Stripe rechazo la cuenta. Revisa el Dashboard de Stripe para mas detalles.',
            'under_review' => 'Stripe esta revisando la cuenta. Puede tardar un poco antes de habilitar pagos.',
            'fields_needed' => 'Falta informacion por completar en Stripe.',
        ];

        return $labels[$reason] ?? 'Stripe requiere atencion para esta cuenta: ' . $reason;
    }

    protected function fillFromStripeAccount(TenantStripeAccount $connectAccount, array $account, array $extra = []): TenantStripeAccount
    {
        $currentlyDue = data_get($account, 'requirements.currently_due', []);
        $pastDue = data_get($account, 'requirements.past_due', []);
        $disabledReason = data_get($account, 'requirements.disabled_reason');
        $chargesEnabled = (bool) data_get($account, 'charges_enabled', false);
        $payoutsEnabled = (bool) data_get($account, 'payouts_enabled', false);
        $detailsSubmitted = (bool) data_get($account, 'details_submitted', false);

        $status = match (true) {
            $chargesEnabled && $payoutsEnabled => TenantStripeAccount::STATUS_ENABLED,
            $detailsSubmitted && (filled($disabledReason) || ! empty($currentlyDue) || ! empty($pastDue)) => TenantStripeAccount::STATUS_RESTRICTED,
            default => TenantStripeAccount::STATUS_ONBOARDING_PENDING,
        };

        $connectAccount->forceFill([
            'stripe_account_id' => data_get($account, 'id') ?: $connectAccount->stripe_account_id,
            'account_type' => data_get($account, 'type', $connectAccount->account_type),
            'connect_status' => $status,
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
            'disabled_reason' => $disabledReason,
            'country' => data_get($account, 'country'),
            'default_currency' => data_get($account, 'default_currency'),
            'requirements_currently_due' => $currentlyDue,
            'requirements_eventually_due' => data_get($account, 'requirements.eventually_due', []),
            'requirements_past_due' => $pastDue,
            'provider_payload' => $account,
            'onboarding_completed_at' => $chargesEnabled && $payoutsEnabled
                ? ($connectAccount->onboarding_completed_at ?: now())
                : $connectAccount->onboarding_completed_at,
            'last_synced_at' => now(),
            ...$extra,
        ])->save();

        return $connectAccount->fresh();
    }

    protected function secretKey(): string
    {
        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        return (string) $secretKey;
    }

    protected function tenantUrl(Tenant $tenant): ?string
    {
        $domain = $tenant->domains()->where('domain', 'like', '%.cloudishop.mx')->value('domain')
            ?: $tenant->domains()->value('domain');

        return $domain ? "https://{$domain}" : null;
    }

    protected function flatten(array $payload, ?string $prefix = null): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            $name = $prefix === null ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $result += $this->flatten($value, $name);
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }
}
