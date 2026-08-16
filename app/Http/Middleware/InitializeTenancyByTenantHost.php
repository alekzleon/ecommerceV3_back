<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $host = $this->tenantHost($request);

        if (! $host) {
            return response()->json([
                'message' => 'Tenant host is required.',
                'required_header' => 'X-Tenant-Host',
            ], 400);
        }

        $tenant = $this->tenantForHost($host);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant could not be identified.',
                'tenant_host' => $host,
            ], 404);
        }

        tenancy()->initialize($tenant);

        $request->attributes->set('tenant_host', $host);

        return $next($request);
    }

    private function tenantHost(Request $request): ?string
    {
        $value = $request->header('X-Tenant-Host')
            ?: $request->header('X-Store-Domain')
            ?: $request->query('tenant_host');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim(strtolower($value));

        if (str_contains($value, '://')) {
            $value = parse_url($value, PHP_URL_HOST) ?: $value;
        }

        return explode(':', $value)[0] ?: null;
    }

    private function tenantForHost(string $host): ?Tenant
    {
        $domain = Domain::query()
            ->with('tenant')
            ->where('domain', $host)
            ->first();

        if ($domain?->tenant) {
            return $domain->tenant;
        }

        return CustomDomain::query()
            ->with('tenant')
            ->where('hostname', $host)
            ->where('app_status', CustomDomain::STATUS_ACTIVE)
            ->where('hostname_status', CustomDomain::STATUS_ACTIVE)
            ->where('ssl_status', CustomDomain::STATUS_ACTIVE)
            ->first()
            ?->tenant;
    }
}
