<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteCustomerRequest;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Mail\CustomerInvitationMail;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function invite(InviteCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $temporaryPassword = Str::password(12);
        $loginUrl = rtrim(config('services.frontend.url'), '/') . '/login';

        DB::beginTransaction();

        try {
            $user = User::create([
                'role_id' => User::ROLE_CLIENTE,
                'name' => $validated['commercial_name'],
                'username' => $validated['email'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'invited_at' => now(),
            ]);

            $user->customerProfile()->create([
                'commercial_name' => $validated['commercial_name'],
                'whatsapp' => $validated['whatsapp'],
                'status' => CustomerProfile::STATUS_ACTIVO,
                'onboarding_status' => CustomerProfile::ONBOARDING_INVITED,
            ]);

            Mail::to($user->email)->send(new CustomerInvitationMail(
                $user,
                $temporaryPassword,
                $loginUrl
            ));

            DB::commit();

            $user->load('customerProfile');

            return response()->json([
                'ok' => true,
                'message' => 'Cliente dado de alta e invitación enviada correctamente.',
                'data' => $user,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible dar de alta e invitar al cliente.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = User::query()
            ->where('role_id', 6)
            ->with([
                'customerProfile',
                'defaultAddress',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->input('search'));

                $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('id_microsip', 'like', "%{$search}%")
                                ->orWhere('route', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->input('status');

                $q->whereHas('customerProfile', function ($profileQuery) use ($status) {
                    $profileQuery->where('status', $status);
                });
            })
            ->orderByDesc('id');

        $customers = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'message' => 'Clientes obtenidos correctamente.',
            'data' => $customers,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $user = User::create([
                'role_id' => 6,
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $profileData = $validated['profile'] ?? [];

            $user->customerProfile()->create([
                'id_microsip' => $profileData['id_microsip'] ?? null,
                'status' => $profileData['status'] ?? CustomerProfile::STATUS_ACTIVO,
                'credit_limit' => $profileData['credit_limit'] ?? 0,
                'credit_days' => $profileData['credit_days'] ?? 0,
                'discount_percent' => $profileData['discount_percent'] ?? 0,
                'assigned_seller_id' => $profileData['assigned_seller_id'] ?? null,
                'route' => $profileData['route'] ?? null,
                'notes' => $profileData['notes'] ?? null,
            ]);

            $addressData = $validated['address'] ?? [];
            $shouldCreateAddress = $this->hasAddressData($addressData);

            if ($shouldCreateAddress) {
                $user->addresses()->create([
                    'alias' => $addressData['alias'] ?? 'Principal',
                    'contact_name' => $addressData['contact_name'] ?? null,
                    'street' => $addressData['street'] ?? '',
                    'external_number' => $addressData['external_number'] ?? null,
                    'internal_number' => $addressData['internal_number'] ?? null,
                    'neighborhood' => $addressData['neighborhood'] ?? null,
                    'zip_code' => $addressData['zip_code'] ?? null,
                    'city' => $addressData['city'] ?? null,
                    'state' => $addressData['state'] ?? null,
                    'references' => $addressData['references'] ?? null,
                    'phone' => $addressData['phone'] ?? null,
                    'is_default' => true,
                ]);
            }

            DB::commit();

            $user->load([
                'customerProfile',
                'defaultAddress',
                'addresses',
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Cliente creado correctamente.',
                'data' => $user,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible crear el cliente.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(User $customer): JsonResponse
    {
        if ((int) $customer->role_id !== 6) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado no es un cliente.',
            ], 404);
        }

        $customer->load([
            'customerProfile',
            'customerPfrProfile',
            'defaultAddress',
            'addresses',
        ]);

        $pfrProfile = $customer->customerPfrProfile;

        return response()->json([
            'ok' => true,
            'message' => 'Cliente obtenido correctamente.',
            'data' => $this->customerDetailPayload($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, User $customer): JsonResponse
    {
        if ((int) $customer->role_id !== 6) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado no es un cliente.',
            ], 404);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $customer->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
            ]);

            if (!empty($validated['password'])) {
                $customer->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

            $profileData = $validated['profile'] ?? [];

            $customer->customerProfile()->updateOrCreate(
                ['user_id' => $customer->id],
                [
                    'id_microsip' => $profileData['id_microsip'] ?? null,
                    'status' => $profileData['status'] ?? CustomerProfile::STATUS_ACTIVO,
                    'credit_limit' => $profileData['credit_limit'] ?? null,
                    'credit_days' => $profileData['credit_days'] ?? null,
                    'discount_percent' => $profileData['discount_percent'] ?? null,
                    'assigned_seller_id' => $profileData['assigned_seller_id'] ?? null,
                    'route' => $profileData['route'] ?? null,
                    'notes' => $profileData['notes'] ?? null,
                ]
            );

            if (Arr::has($validated, 'customer_pfr_profile.price_list')) {
                $customer->customerPfrProfile()->updateOrCreate(
                    ['user_id' => $customer->id],
                    ['price_list' => Arr::get($validated, 'customer_pfr_profile.price_list')]
                );
            }

            $addressData = $validated['address'] ?? [];
            $shouldCreateOrUpdateAddress = $this->hasAddressData($addressData);

            if ($shouldCreateOrUpdateAddress) {
                $defaultAddress = $customer->defaultAddress;

                if ($defaultAddress) {
                    $defaultAddress->update([
                        'alias' => $addressData['alias'] ?? $defaultAddress->alias,
                        'contact_name' => $addressData['contact_name'] ?? null,
                        'street' => $addressData['street'] ?? '',
                        'external_number' => $addressData['external_number'] ?? null,
                        'internal_number' => $addressData['internal_number'] ?? null,
                        'neighborhood' => $addressData['neighborhood'] ?? null,
                        'zip_code' => $addressData['zip_code'] ?? null,
                        'city' => $addressData['city'] ?? null,
                        'state' => $addressData['state'] ?? null,
                        'references' => $addressData['references'] ?? null,
                        'phone' => $addressData['phone'] ?? null,
                        'is_default' => true,
                    ]);
                } else {
                    UserAddress::where('user_id', $customer->id)->update(['is_default' => false]);

                    $customer->addresses()->create([
                        'alias' => $addressData['alias'] ?? 'Principal',
                        'contact_name' => $addressData['contact_name'] ?? null,
                        'street' => $addressData['street'] ?? '',
                        'external_number' => $addressData['external_number'] ?? null,
                        'internal_number' => $addressData['internal_number'] ?? null,
                        'neighborhood' => $addressData['neighborhood'] ?? null,
                        'zip_code' => $addressData['zip_code'] ?? null,
                        'city' => $addressData['city'] ?? null,
                        'state' => $addressData['state'] ?? null,
                        'references' => $addressData['references'] ?? null,
                        'phone' => $addressData['phone'] ?? null,
                        'is_default' => true,
                    ]);
                }
            }

            DB::commit();

            $customer->load([
                'customerProfile',
                'customerPfrProfile',
                'defaultAddress',
                'addresses',
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Cliente actualizado correctamente.',
                'data' => $this->customerDetailPayload($customer),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible actualizar el cliente.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy(User $customer): JsonResponse
    {
        if ((int) $customer->role_id !== 6) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado no es un cliente.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $customer->delete();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Cliente eliminado correctamente.',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible eliminar el cliente.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, User $customer): JsonResponse
    {
        if ((int) $customer->role_id !== 6) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario indicado no es un cliente.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:activo,baja,suspendido_credito'],
        ]);

        $customer->customerProfile()->updateOrCreate(
            ['user_id' => $customer->id],
            ['status' => $validated['status']]
        );

        $customer->load([
            'customerProfile',
            'defaultAddress',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Status del cliente actualizado correctamente.',
            'data' => $customer,
        ]);
    }

    private function hasAddressData(array $addressData): bool
    {
        $fields = [
            'alias',
            'contact_name',
            'street',
            'external_number',
            'internal_number',
            'neighborhood',
            'zip_code',
            'city',
            'state',
            'references',
            'phone',
        ];

        foreach ($fields as $field) {
            if (!empty($addressData[$field])) {
                return true;
            }
        }

        return false;
    }

    private function customerDetailPayload(User $customer): array
    {
        $customer->loadMissing([
            'customerProfile',
            'customerPfrProfile',
            'defaultAddress',
            'addresses',
        ]);

        $pfrProfile = $customer->customerPfrProfile;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'username' => $customer->username,
            'email' => $customer->email,
            'role_id' => $customer->role_id,
            'must_change_password' => $customer->must_change_password,
            'invited_at' => $customer->invited_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
            'customer_profile' => $customer->customerProfile,
            'customer_pfr_profile' => $pfrProfile,
            'pfr_completion' => $pfrProfile
                ? $pfrProfile->completionSummary()
                : $this->emptyPfrCompletion(),
            'default_address' => $customer->defaultAddress,
            'addresses' => $customer->addresses,
        ];
    }

    private function emptyPfrCompletion(): array
    {
        return [
            'percentage' => 0,
            'completed_fields' => 0,
            'total_fields' => 8,
            'missing_fields' => [
                ['field' => 'commercial_name', 'label' => 'Nombre comercial'],
                ['field' => 'purchasing_contact_name', 'label' => 'Contacto de compras'],
                ['field' => 'business_phone', 'label' => 'Telefono del comercio'],
                ['field' => 'business_activity', 'label' => 'Giro comercial'],
                ['field' => 'payment_method', 'label' => 'Metodo de pago'],
                ['field' => 'price_list', 'label' => 'Lista'],
                ['field' => 'requires_invoice', 'label' => 'Facturacion'],
                ['field' => 'delivery_same_as_fiscal', 'label' => 'Entrega en direccion fiscal'],
            ],
            'sections' => [
                'general' => [
                    'percentage' => 0,
                    'completed_fields' => 0,
                    'total_fields' => 6,
                    'missing_fields' => [
                        ['field' => 'commercial_name', 'label' => 'Nombre comercial'],
                        ['field' => 'purchasing_contact_name', 'label' => 'Contacto de compras'],
                        ['field' => 'business_phone', 'label' => 'Telefono del comercio'],
                        ['field' => 'business_activity', 'label' => 'Giro comercial'],
                        ['field' => 'payment_method', 'label' => 'Metodo de pago'],
                        ['field' => 'price_list', 'label' => 'Lista'],
                    ],
                    'applies' => true,
                ],
                'billing' => [
                    'percentage' => 0,
                    'completed_fields' => 0,
                    'total_fields' => 1,
                    'missing_fields' => [
                        ['field' => 'requires_invoice', 'label' => 'Facturacion'],
                    ],
                    'applies' => true,
                ],
                'delivery' => [
                    'percentage' => 0,
                    'completed_fields' => 0,
                    'total_fields' => 1,
                    'missing_fields' => [
                        ['field' => 'delivery_same_as_fiscal', 'label' => 'Entrega en direccion fiscal'],
                    ],
                    'applies' => true,
                ],
            ],
        ];
    }
}
