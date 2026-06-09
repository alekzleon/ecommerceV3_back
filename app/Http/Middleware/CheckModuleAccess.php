<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $user = $request->user();

        if (!$user) {
           return response()->json([
            'message' => 'No autenticado.'
        ], 401);
        }

        if (!$user->role) {
            return response()->json([
                'message' => 'Tu usuario no tiene un rol asignado.'
            ], 403);
        }

        if (!$user->role->is_active) {
            return response()->json([
                'message' => 'Tu rol está inactivo.'
            ], 403);           
        }

        if (!$user->hasModuleAccess($moduleName)) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a este módulo.'
            ], 403);    
        }

        return $next($request);
    }
}
