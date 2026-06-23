<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageAccess
{
    private const ALLOWED_ROLES = [
        'super_admin',
        'admin',
        'sistemas',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('role');

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (!$user->role || !$user->role->is_active) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol activo asignado.',
            ], 403);
        }

        if (! in_array($user->role->name, self::ALLOWED_ROLES, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'No tienes permiso para administrar roles, permisos y usuarios.',
            ], 403);
        }

        return $next($request);
    }
}
