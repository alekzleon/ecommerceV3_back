<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Mail\PasswordResetLinkMail;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function __construct(
        private TenantNotificationService $tenantNotifications
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $clientRole = Role::query()
            ->where('name', 'cliente')
            ->where('is_active', true)
            ->first();

        if (!$clientRole) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay un rol de cliente activo configurado.',
            ], 500);
        }

        $user = DB::transaction(function () use ($validated, $clientRole) {
            $user = User::create([
                'role_id' => $clientRole->id,
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ]);

            $user->customerProfile()->create([
                'commercial_name' => $validated['commercial_name'] ?? $validated['name'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'status' => CustomerProfile::STATUS_ACTIVO,
                'onboarding_status' => CustomerProfile::ONBOARDING_IN_PROGRESS,
            ]);

            return $user;
        });

        $user->load(['role.modules', 'customerProfile']);

        $token = $user->createToken(
            $request->input('device_name', 'react-web')
        )->plainTextToken;

        $modules = $this->accessibleModules($user);

        $this->tenantNotifications->sendToUser(
            $user,
            'Bienvenido a Cloudi Shop',
            'Tu cuenta esta lista',
            "Hola {$user->name}, tu registro se completo correctamente. Ya puedes iniciar sesion y comenzar a comprar.",
            $this->frontendUrl($request),
            'Ir a la tienda',
            [
                'Correo' => $user->email,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Registro exitoso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => '/',
            'is_internal' => false,
            'must_change_password' => false,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'modules' => $modules,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->input('login'));
        $password = $request->input('password');

        $user = User::query()
            ->with(['role.modules'])
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->role) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol asignado.',
            ], 403);
        }

        if (!$user->role->is_active) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu rol está inactivo.',
            ], 403);
        }

        $token = $user->createToken(
            $request->input('device_name', 'react-web')
        )->plainTextToken;

        $modules = $this->accessibleModules($user);

        $isInternal = $user->role->name !== 'cliente';
        $redirectTo = $isInternal ? '/admin' : '/';

        return response()->json([
            'ok' => true,
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => $redirectTo,
            'is_internal' => $isInternal,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'modules' => $modules,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['role.modules']);

        if (!$user->role) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol asignado.',
            ], 403);
        }

        $modules = $this->accessibleModules($user);

        $isInternal = $user->role->name !== 'cliente';
        $redirectTo = $isInternal ? '/admin' : '/';

        return response()->json([
            'ok' => true,
            'redirect_to' => $redirectTo,
            'is_internal' => $isInternal,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'modules' => $modules,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $resetUrl = rtrim((string) config('services.frontend.url'), '/') . '/reset-password?' . http_build_query([
                'email' => $user->email,
                'token' => $token,
            ]);

            Mail::to($user->email)->send(new PasswordResetLinkMail($user, $resetUrl));
        }

        return response()->json([
            'ok' => true,
            'message' => 'Si el correo existe, enviaremos instrucciones para recuperar la contraseña.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $resetUser = null;

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                ])->save();

                $user->tokens()->delete();
                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['El token es inválido o expiró.'],
            ]);
        }

        if ($resetUser) {
            $this->tenantNotifications->sendToUser(
                $resetUser,
                'Tu contraseña fue actualizada',
                'Contraseña actualizada',
                'Tu contraseña se actualizo correctamente. Si no reconoces este cambio, contacta al equipo de soporte de inmediato.',
                $this->frontendUrl($request),
                'Ir a la tienda',
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ]);

        $this->tenantNotifications->sendToUser(
            $user,
            'Tu contraseña fue actualizada',
            'Contraseña actualizada',
            'Tu contraseña se actualizo correctamente. Si no reconoces este cambio, contacta al equipo de soporte de inmediato.',
            $this->frontendUrl($request),
            'Ir a la tienda',
        );

        return response()->json([
            'ok' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    protected function accessibleModules(User $user): array
    {
        return $user->role->modules
            ->where('is_active', true)
            ->filter(fn ($module) => ! tenant() || tenant()->hasPlanModule($module->name))
            ->pluck('name')
            ->values()
            ->toArray();
    }

    private function frontendUrl(Request $request): string
    {
        return rtrim((string) ($request->headers->get('origin') ?: config('services.frontend.url')), '/');
    }
}
