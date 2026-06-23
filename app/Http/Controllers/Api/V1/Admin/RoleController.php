<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('modules')
            ->when(! filter_var($request->input('include_client', false), FILTER_VALIDATE_BOOLEAN), function ($query) {
                $query->where('name', '!=', 'cliente');
            })
            ->orderBy('display_name')
            ->get()
            ->map(fn (Role $role) => $this->rolePayload($role))
            ->values();

        return response()->json([
            'ok' => true,
            'message' => 'Roles obtenidos correctamente.',
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $role->modules()->sync($data['module_ids'] ?? []);
        $role->load('modules');

        return response()->json([
            'ok' => true,
            'message' => 'Rol creado correctamente.',
            'data' => $this->rolePayload($role),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('modules');

        return response()->json([
            'ok' => true,
            'message' => 'Rol obtenido correctamente.',
            'data' => $this->rolePayload($role),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validatedData($request, $role);

        if ($role->name === 'super_admin' && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json([
                'ok' => false,
                'message' => 'No puedes desactivar el rol Super Admin.',
            ], 422);
        }

        $role->update([
            'name' => $role->name === 'super_admin' ? $role->name : ($data['name'] ?? $role->name),
            'display_name' => $data['display_name'] ?? $role->display_name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);

        if (array_key_exists('module_ids', $data)) {
            $guard = $this->guardProtectedRoleModules($role, $data['module_ids']);

            if ($guard) {
                return $guard;
            }

            $role->modules()->sync($data['module_ids']);
        }

        $role->load('modules');

        return response()->json([
            'ok' => true,
            'message' => 'Rol actualizado correctamente.',
            'data' => $this->rolePayload($role),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, ['super_admin', 'admin', 'sistemas', 'cliente'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Este rol base no se puede eliminar.',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'No puedes eliminar un rol con usuarios asignados.',
            ], 422);
        }

        $role->modules()->detach();
        $role->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Rol eliminado correctamente.',
        ]);
    }

    public function updateModules(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'module_ids' => ['required', 'array'],
            'module_ids.*' => ['integer', Rule::exists('modules', 'id')],
        ]);

        $guard = $this->guardProtectedRoleModules($role, $data['module_ids']);

        if ($guard) {
            return $guard;
        }

        $role->modules()->sync($data['module_ids']);
        $role->load('modules');

        return response()->json([
            'ok' => true,
            'message' => 'Permisos del rol actualizados correctamente.',
            'data' => $this->rolePayload($role),
        ]);
    }

    protected function validatedData(Request $request, ?Role $role = null): array
    {
        $roleId = $role?->id;
        $isUpdate = $role !== null;

        return $request->validate([
            'name' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'display_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'module_ids' => ['sometimes', 'array'],
            'module_ids.*' => ['integer', Rule::exists('modules', 'id')],
        ]);
    }

    protected function guardProtectedRoleModules(Role $role, array $moduleIds): ?JsonResponse
    {
        if ($role->name !== 'super_admin') {
            return null;
        }

        $requiredModules = Module::query()
            ->whereIn('name', ['dashboard', 'usuarios', 'roles'])
            ->pluck('id', 'name');

        $missing = $requiredModules
            ->filter(fn (int $moduleId) => ! in_array($moduleId, $moduleIds, true))
            ->keys()
            ->values();

        if ($missing->isEmpty()) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Super Admin debe conservar acceso a dashboard, usuarios y roles.',
            'missing_modules' => $missing,
        ], 422);
    }

    protected function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_active' => (bool) $role->is_active,
            'modules' => $role->modules
                ->sortBy('sort_order')
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
                ->values(),
            'module_ids' => $role->modules->pluck('id')->values(),
            'created_at' => $role->created_at?->toDateTimeString(),
            'updated_at' => $role->updated_at?->toDateTimeString(),
        ];
    }
}
