<?php

namespace App\Http\Resources\Admin;

use App\Models\CustomDomain;
use App\Services\Domains\CloudflareCustomHostnameService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomDomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomDomain $domain */
        $domain = $this->resource;
        $cloudflare = app(CloudflareCustomHostnameService::class);

        return [
            'id' => $domain->id,
            'tenant_id' => $domain->tenant_id,
            'domain' => $domain->hostname,
            'hostname' => $domain->hostname,
            'status' => $domain->app_status,
            'app_status' => $domain->app_status,
            'hostname_status' => $domain->hostname_status,
            'ssl_status' => $domain->ssl_status,
            'is_ready' => $domain->is_ready,
            'cloudflare_hostname_id' => $domain->cloudflare_hostname_id,
            'cloudflare_configured' => $cloudflare->configured(),
            'dns' => $cloudflare->dnsInstructions($domain),
            'verification_errors' => $domain->verification_errors ?: [],
            'verified_at' => $domain->verified_at?->toISOString(),
            'last_checked_at' => $domain->last_checked_at?->toISOString(),
            'created_at' => $domain->created_at?->toISOString(),
            'updated_at' => $domain->updated_at?->toISOString(),
        ];
    }
}
