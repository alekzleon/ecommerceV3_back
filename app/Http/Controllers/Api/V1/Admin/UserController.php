<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;
        $sortBy = $request->string('sort_by', 'latest')->toString();

        $query = User::query()
            ->with('role')
            ->where(function ($query) {
                $query->whereNull('role_id')
                    ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('name', '!=', 'cliente'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('role', function ($roleQuery) use ($search) {
                            $roleQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('display_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('role_id'), function ($query) use ($request) {
                $query->where('role_id', (int) $request->integer('role_id'));
            })
            ->when($request->has('role_is_active') && $request->input('role_is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('role_is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('is_active', $isActive));
                }
            });

        match ($sortBy) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'email_asc' => $query->orderBy('email'),
            'email_desc' => $query->orderByDesc('email'),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        if (filter_var($request->input('without_pagination', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'message' => 'Usuarios obtenidos correctamente.',
                'data' => AdminUserResource::collection($query->get()),
            ]);
        }

        $users = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Usuarios obtenidos correctamente.',
            'data' => AdminUserResource::collection($users->getCollection()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->load('role');

        return response()->json([
            'ok' => true,
            'message' => 'Usuario creado correctamente.',
            'data' => new AdminUserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        if (! $this->isInternalUser($user)) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado es un cliente.',
            ], 404);
        }

        $user->load('role');

        return response()->json([
            'ok' => true,
            'message' => 'Usuario obtenido correctamente.',
            'data' => new AdminUserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (! $this->isInternalUser($user)) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado es un cliente.',
            ], 404);
        }

        $data = $request->validated();

        $userData = collect($data)
            ->only(['role_id', 'name', 'username', 'email'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        $user->update($userData);
        $user->load('role');

        return response()->json([
            'ok' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => new AdminUserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $this->isInternalUser($user)) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado es un cliente.',
            ], 404);
        }

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No puedes eliminar tu propio usuario.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $roles = Role::query()
            ->where('name', '!=', 'cliente')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_active' => (bool) $role->is_active,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'roles' => $roles,
                'sort_options' => [
                    ['value' => 'latest', 'label' => 'Más recientes'],
                    ['value' => 'oldest', 'label' => 'Más antiguos'],
                    ['value' => 'name_asc', 'label' => 'Nombre A-Z'],
                    ['value' => 'name_desc', 'label' => 'Nombre Z-A'],
                    ['value' => 'email_asc', 'label' => 'Correo A-Z'],
                    ['value' => 'email_desc', 'label' => 'Correo Z-A'],
                ],
            ],
        ]);
    }

    protected function isInternalUser(User $user): bool
    {
        $user->loadMissing('role');

        return ! $user->role || $user->role->name !== 'cliente';
    }
}
