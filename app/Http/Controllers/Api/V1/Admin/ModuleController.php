<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function index(): JsonResponse
    {
        $modules = Module::query()
            ->whereNotIn('name', [
                'carga_masiva_productos',
                'front_ecommerce',
                'variantes',
            ])
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Module $module) => [
                'id' => $module->id,
                'name' => $module->name,
                'display_name' => $module->display_name,
                'description' => $module->description,
                'group_key' => $module->group_key,
                'sort_order' => $module->sort_order,
                'route_name' => $module->route_name,
                'icon' => $module->icon,
                'is_active' => (bool) $module->is_active,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'message' => 'Módulos obtenidos correctamente.',
            'data' => $modules,
        ]);
    }
}
