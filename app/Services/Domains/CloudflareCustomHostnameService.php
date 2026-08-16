<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use App\Models\Tenant;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stancl\Tenancy\Database\Models\Domain;

class CloudflareCustomHostnameService
{
    public function configured(): bool
    {
        return filled($this->apiToken()) && filled($this->zoneId());
    }

    public function cnameTarget(): string
    {
        return (string) config('services.cloudflare_for_saas.cname_target', 'domains.cloudishop.mx');
    }

    public function normalizeHostname(string $value): string
    {
        $value = trim(strtolower($value));

        if (str_contains($value, '://')) {
            $value = parse_url($value, PHP_URL_HOST) ?: $value;
        }

        $value = explode('/', $value)[0] ?? $value;
        $value = explode('?', $value)[0] ?? $value;
        $value = explode('#', $value)[0] ?? $value;

        return trim(explode(':', $value)[0] ?? $value, " \t\n\r\0\x0B.");
    }

    public function assertHostnameCanBeUsed(string $hostname, Tenant $tenant): void
    {
        if (! preg_match('/^(?=.{1,253}$)(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $hostname)) {
            abort(422, 'Ingresa un dominio valido, por ejemplo www.marca.com.');
        }

        $platformZone = (string) config('services.cloudflare_for_saas.platform_zone', 'cloudishop.mx');

        if ($hostname === $platformZone || str_ends_with($hostname, ".{$platformZone}")) {
            abort(422, 'No puedes registrar dominios internos de CloudiShop como dominio personalizado.');
        }

        $exists = CustomDomain::query()
            ->where('hostname', $hostname)
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        if ($exists) {
            abort(422, 'Este dominio ya esta conectado a otra tienda.');
        }

        $reserved = Domain::query()
            ->where('domain', $hostname)
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        if ($reserved) {
            abort(422, 'Este dominio ya esta reservado por otra tienda.');
        }
    }

    public function create(CustomDomain $customDomain): CustomDomain
    {
        if (! $this->configured()) {
            $customDomain->forceFill([
                'app_status' => CustomDomain::STATUS_PENDING_DNS,
                'hostname_status' => CustomDomain::STATUS_PENDING_DNS,
                'ssl_status' => null,
                'cname_target' => $this->cnameTarget(),
            ])->save();

            return $customDomain;
        }

        try {
            $response = Http::withToken($this->apiToken())
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpoint(), [
                    'hostname' => $customDomain->hostname,
                    'ssl' => [
                        'method' => (string) config('services.cloudflare_for_saas.ssl_method', 'http'),
                        'type' => (string) config('services.cloudflare_for_saas.ssl_type', 'dv'),
                    ],
                ])
                ->throw()
                ->json('result');
        } catch (RequestException $exception) {
            abort(502, data_get($exception->response?->json(), 'errors.0.message', 'Cloudflare no pudo crear el Custom Hostname.'));
        }

        return $this->applyCloudflarePayload($customDomain, $response);
    }

    public function refresh(CustomDomain $customDomain): CustomDomain
    {
        if (! $this->configured() || ! $customDomain->cloudflare_hostname_id) {
            $customDomain->forceFill([
                'last_checked_at' => now(),
            ])->save();

            return $customDomain;
        }

        try {
            $response = Http::withToken($this->apiToken())
                ->acceptJson()
                ->timeout(20)
                ->get($this->endpoint($customDomain->cloudflare_hostname_id))
                ->throw()
                ->json('result');
        } catch (RequestException $exception) {
            abort(502, data_get($exception->response?->json(), 'errors.0.message', 'Cloudflare no pudo consultar el Custom Hostname.'));
        }

        return $this->applyCloudflarePayload($customDomain, $response);
    }

    public function delete(CustomDomain $customDomain): void
    {
        if ($this->configured() && $customDomain->cloudflare_hostname_id) {
            Http::withToken($this->apiToken())
                ->acceptJson()
                ->timeout(20)
                ->delete($this->endpoint($customDomain->cloudflare_hostname_id));
        }

        Domain::query()
            ->where('tenant_id', $customDomain->tenant_id)
            ->where('domain', $customDomain->hostname)
            ->delete();

        $customDomain->delete();
    }

    public function dnsInstructions(CustomDomain $customDomain): array
    {
        return [
            'type' => 'CNAME',
            'name' => $this->dnsNameHint($customDomain->hostname),
            'hostname' => $customDomain->hostname,
            'target' => $customDomain->cname_target ?: $this->cnameTarget(),
            'note' => 'Algunos proveedores piden solo el subdominio en Nombre y otros el hostname completo.',
        ];
    }

    private function applyCloudflarePayload(CustomDomain $customDomain, ?array $payload): CustomDomain
    {
        $hostnameStatus = (string) data_get($payload, 'status', CustomDomain::STATUS_PENDING_VALIDATION);
        $sslStatus = data_get($payload, 'ssl.status');
        $ready = $hostnameStatus === CustomDomain::STATUS_ACTIVE && $sslStatus === CustomDomain::STATUS_ACTIVE;

        $customDomain->forceFill([
            'cloudflare_hostname_id' => data_get($payload, 'id', $customDomain->cloudflare_hostname_id),
            'app_status' => $this->appStatus($hostnameStatus, $sslStatus),
            'hostname_status' => $hostnameStatus,
            'ssl_status' => $sslStatus,
            'cname_target' => $customDomain->cname_target ?: $this->cnameTarget(),
            'verification_errors' => data_get($payload, 'verification_errors'),
            'cloudflare_response' => $payload,
            'verified_at' => $ready ? ($customDomain->verified_at ?: now()) : null,
            'last_checked_at' => now(),
        ])->save();

        if ($customDomain->is_ready) {
            Domain::query()->updateOrCreate(
                ['domain' => $customDomain->hostname],
                ['tenant_id' => $customDomain->tenant_id],
            );
        }

        return $customDomain;
    }

    private function appStatus(string $hostnameStatus, ?string $sslStatus): string
    {
        if ($hostnameStatus === CustomDomain::STATUS_ACTIVE && $sslStatus === CustomDomain::STATUS_ACTIVE) {
            return CustomDomain::STATUS_ACTIVE;
        }

        if (in_array($hostnameStatus, ['blocked', 'failed', 'moved'], true) || in_array($sslStatus, ['failed', 'deleted'], true)) {
            return CustomDomain::STATUS_ERROR;
        }

        if ($hostnameStatus === CustomDomain::STATUS_ACTIVE && $sslStatus !== CustomDomain::STATUS_ACTIVE) {
            return CustomDomain::STATUS_PENDING_SSL;
        }

        return CustomDomain::STATUS_PENDING_VALIDATION;
    }

    private function dnsNameHint(string $hostname): string
    {
        return explode('.', $hostname)[0] ?: $hostname;
    }

    private function endpoint(?string $customHostnameId = null): string
    {
        $base = 'https://api.cloudflare.com/client/v4/zones/'.$this->zoneId().'/custom_hostnames';

        return $customHostnameId ? "{$base}/{$customHostnameId}" : $base;
    }

    private function apiToken(): ?string
    {
        return config('services.cloudflare_for_saas.api_token');
    }

    private function zoneId(): ?string
    {
        return config('services.cloudflare_for_saas.zone_id');
    }
}
