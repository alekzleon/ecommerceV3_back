<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCustomerPfrProfileRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerPfrProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->customerPfrProfile;

        return response()->json([
            'ok' => true,
            'message' => $profile
                ? 'Perfil PFR obtenido correctamente.'
                : 'El cliente aún no ha completado el perfil PFR.',
            'data' => $profile,
        ]);
    }

    public function store(StoreCustomerPfrProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $file = $request->file('tax_certificate');

        DB::beginTransaction();

        try {
            $existingProfile = $user->customerPfrProfile;
            $payload = Arr::except($validated, ['tax_certificate']);
            $payload['price_list'] = $payload['price_list'] ?? 'Lista 3';

            if ($file) {
                if ($existingProfile?->tax_certificate_disk && $existingProfile?->tax_certificate_path) {
                    Storage::disk($existingProfile->tax_certificate_disk)->delete($existingProfile->tax_certificate_path);
                }

                $payload['tax_certificate_disk'] = 'public';
                $payload['tax_certificate_path'] = $file->store('customer-tax-certificates', 'public');
                $payload['tax_certificate_original_name'] = $file->getClientOriginalName();
                $payload['tax_certificate_mime'] = $file->getClientMimeType();
                $payload['tax_certificate_size'] = $file->getSize();
            }

            $profile = $user->customerPfrProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $payload
            );

            if (!empty($payload['commercial_name'])) {
                $user->update([
                    'name' => $payload['commercial_name'],
                ]);
            }

            $customerProfileData = [
                'onboarding_status' => CustomerProfile::ONBOARDING_PROFILE_COMPLETED,
            ];

            if (!empty($payload['commercial_name'])) {
                $customerProfileData['commercial_name'] = $payload['commercial_name'];
            }

            $completion = $profile->fresh()->completionSummary();
            $customerProfileData['onboarding_status'] = $completion['percentage'] === 100
                ? CustomerProfile::ONBOARDING_PROFILE_COMPLETED
                : CustomerProfile::ONBOARDING_IN_PROGRESS;

            $user->customerProfile()->updateOrCreate(['user_id' => $user->id], $customerProfileData);

            DB::commit();

            $profile = $profile->fresh();

            return response()->json([
                'ok' => true,
                'message' => 'Perfil PFR guardado correctamente.',
                'data' => [
                    'profile' => $profile,
                    'completion' => $profile->completionSummary(),
                ],
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible guardar el perfil PFR.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
