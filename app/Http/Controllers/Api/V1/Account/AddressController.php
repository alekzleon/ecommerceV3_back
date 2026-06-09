<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Requests\Account\UpdateAddressRequest;
use App\Http\Resources\Account\AddressResource;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Direcciones obtenidas correctamente.',
            'data' => AddressResource::collection($addresses),
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->addressData($request->validated());

        $address = DB::transaction(function () use ($user, $data) {
            $shouldBeDefault = (bool) ($data['is_default'] ?? false)
                || ! $user->addresses()->exists();

            if ($shouldBeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$data,
                'is_default' => $shouldBeDefault,
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dirección creada correctamente.',
            'data' => new AddressResource($address),
        ], 201);
    }

    public function show(Request $request, UserAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        return response()->json([
            'ok' => true,
            'message' => 'Dirección obtenida correctamente.',
            'data' => new AddressResource($address),
        ]);
    }

    public function update(UpdateAddressRequest $request, UserAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        $data = $this->addressData($request->validated());

        DB::transaction(function () use ($request, $address, $data) {
            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dirección actualizada correctamente.',
            'data' => new AddressResource($address->fresh()),
        ]);
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        DB::transaction(function () use ($request, $address) {
            $wasDefault = (bool) $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $nextAddress = $request->user()
                    ->addresses()
                    ->latest('id')
                    ->first();

                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dirección eliminada correctamente.',
        ]);
    }

    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dirección predeterminada actualizada correctamente.',
            'data' => new AddressResource($address->fresh()),
        ]);
    }

    protected function ensureOwnership(Request $request, UserAddress $address): void
    {
        abort_unless((int) $address->user_id === (int) $request->user()->id, 404, 'Dirección no encontrada.');
    }

    protected function addressData(array $validated): array
    {
        $data = collect($validated)
            ->only([
                'alias',
                'street',
                'address_line_2',
                'zip_code',
                'neighborhood',
                'state',
                'contact_name',
                'phone',
                'is_default',
            ])
            ->all();

        if (array_key_exists('delivery_note', $validated)) {
            $data['references'] = $validated['delivery_note'];
        }

        return $data;
    }
}
