<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowCustomDomainCorsOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $host = $this->hostFromOrigin($origin);

        if ($origin && $host && $this->isConnectedCustomDomain($host)) {
            config([
                'cors.allowed_origins' => array_values(array_unique([
                    ...config('cors.allowed_origins', []),
                    $origin,
                ])),
            ]);
        }

        return $next($request);
    }

    private function hostFromOrigin(?string $origin): ?string
    {
        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $parts = parse_url($origin);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        return $host;
    }

    private function isConnectedCustomDomain(string $host): bool
    {
        try {
            return CustomDomain::query()
                ->where('hostname', $host)
                ->where('app_status', CustomDomain::STATUS_ACTIVE)
                ->where('hostname_status', CustomDomain::STATUS_ACTIVE)
                ->where('ssl_status', CustomDomain::STATUS_ACTIVE)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
