<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('role');

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (! $user->role || ! $user->role->is_active || $user->role->name !== 'super_admin') {
            return response()->json([
                'ok' => false,
                'message' => 'Solo super_admin CloudiShop puede acceder a la consola central.',
            ], 403);
        }

        return $next($request);
    }
}
