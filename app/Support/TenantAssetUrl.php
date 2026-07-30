<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class TenantAssetUrl
{
    public static function make(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $request = request();

        $tenantHost = $request->attributes->get('tenant_host')
            ?: $request->header('X-Tenant-Host')
            ?: $request->header('X-Store-Domain')
            ?: $request->query('tenant_host');

        if (! is_string($tenantHost) || trim($tenantHost) === '') {
            return null;
        }

        return URL::to('/api/v1/tenant-assets/' . ltrim($path, '/')) . '?' . http_build_query([
            'tenant_host' => strtolower(trim($tenantHost)),
        ]);
    }
}
