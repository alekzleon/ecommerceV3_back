<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant || $tenant->isSubscriptionUsable()) {
            return $next($request);
        }

        $payload = [
            'ok' => false,
            'code' => 'ecommerce_suspended',
            'message' => 'Ecommerce temporalmente suspendido.',
            'brand' => [
                'name' => 'CloudiShop',
                'logo_text' => 'CloudiShop',
            ],
        ];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, 404);
        }

        return response()->view('errors.store-suspended', $payload, 404);
    }
}
