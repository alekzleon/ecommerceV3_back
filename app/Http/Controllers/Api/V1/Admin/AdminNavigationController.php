<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class AdminNavigationController extends Controller
{
    public function menu(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role.modules');

        if (!$user->role) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol asignado.',
            ], 403);
        }

        $groupNames = [
            'analitica' => 'Analítica',
            'administracion' => 'Administración',
            'catalogo' => 'Catálogo',
            'operacion' => 'Operación',
            'finanzas' => 'Finanzas',
            'marketing' => 'Marketing',
            'control' => 'Control',
            'sistema' => 'Sistema',
        ];

        $modules = $user->role->modules
            ->where('is_active', true)
            ->where('name', '!=', 'front_ecommerce')
            ->sortBy('sort_order')
            ->values();

        $grouped = $modules->groupBy('group_key')->map(function ($items, $groupKey) use ($groupNames) {
            return [
                'group_key' => $groupKey,
                'group_name' => $groupNames[$groupKey] ?? ucfirst(str_replace('_', ' ', $groupKey)),
                'items' => $items->map(function ($module) {
                    return [
                        'name' => $module->name,
                        'display_name' => $module->display_name,
                        'route_name' => $module->route_name,
                        'icon' => $module->icon,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'menu' => $grouped,
        ]);
    }
}
