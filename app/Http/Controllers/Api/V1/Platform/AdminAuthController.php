<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $login = trim($validated['login']);

        $user = User::query()
            ->with('role')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (! $user->role || ! $user->role->is_active || $user->role->name !== 'super_admin') {
            return response()->json([
                'ok' => false,
                'message' => 'Solo super_admin CloudiShop puede acceder a la consola central.',
            ], 403);
        }

        $token = $user->createToken($validated['device_name'] ?? 'platform-admin')->plainTextToken;

        return response()->json([
            'ok' => true,
            'message' => 'Inicio de sesión central exitoso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => '/platform/admin',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'redirect_to' => '/platform/admin',
            'user' => $this->userPayload($request->user()->loadMissing('role')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Sesión central cerrada correctamente.',
        ]);
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => [
                'id' => $user->role?->id,
                'name' => $user->role?->name,
                'display_name' => $user->role?->display_name,
            ],
        ];
    }
}
