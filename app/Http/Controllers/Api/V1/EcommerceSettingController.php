<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EcommerceSetting;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EcommerceSettingController extends Controller
{
    public function homeBenefits(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => 'home_benefits',
                'value' => $this->homeBenefitsValue(),
            ],
        ]);
    }

    public function homeBenefit(int $benefit): JsonResponse
    {
        $this->ensureHomeBenefitNumber($benefit);

        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::homeBenefitKey($benefit),
                'value' => $this->homeBenefitValue($benefit),
            ],
        ]);
    }

    public function abandonedCart(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_ABANDONED_CART,
                'value' => EcommerceSetting::abandonedCartSettings(),
            ],
        ]);
    }

    public function saleNotifications(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_SALE_NOTIFICATIONS,
                'value' => EcommerceSetting::saleNotificationSettings(),
            ],
        ]);
    }

    public function navTitle(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_NAV_TITLE,
                'value' => EcommerceSetting::getValue(EcommerceSetting::KEY_NAV_TITLE, [
                    'title' => null,
                ]),
            ],
        ]);
    }

    public function generalLogo(): JsonResponse
    {
        $settings = SiteSetting::current();

        return response()->json([
            'ok' => true,
            'data' => [
                'key' => 'general_logo',
                'value' => [
                    'logo_path' => $settings->logo_path,
                    'logo_url' => $settings->logo_url,
                ],
            ],
        ]);
    }

    public function contactFaqImage(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_CONTACT_FAQ_IMAGE,
                'value' => $this->contactFaqImageValue(),
            ],
        ]);
    }

    public function contactMapUrl(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_CONTACT_MAP_URL,
                'value' => EcommerceSetting::getValue(EcommerceSetting::KEY_CONTACT_MAP_URL, [
                    'url' => null,
                ]),
            ],
        ]);
    }

    public function metaPixel(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'key' => EcommerceSetting::KEY_META_PIXEL,
                'value' => EcommerceSetting::getValue(EcommerceSetting::KEY_META_PIXEL, [
                    'pixel_id' => null,
                ]),
            ],
        ]);
    }

    protected function contactFaqImageValue(): array
    {
        $value = EcommerceSetting::getValue(EcommerceSetting::KEY_CONTACT_FAQ_IMAGE, [
            'image_disk' => 'public',
            'image_path' => null,
        ]);

        $path = data_get($value, 'image_path');
        $disk = data_get($value, 'image_disk', 'public') ?: 'public';

        return [
            'image_disk' => $disk,
            'image_path' => $path,
            'image_url' => $path ? Storage::disk($disk)->url($path) : null,
        ];
    }

    protected function homeBenefitsValue(): array
    {
        return collect([1, 2, 3])
            ->map(fn (int $benefit) => $this->homeBenefitValue($benefit))
            ->values()
            ->all();
    }

    protected function homeBenefitValue(int $benefit): array
    {
        $value = EcommerceSetting::homeBenefitValue($benefit);
        $path = data_get($value, 'icon_path');
        $disk = data_get($value, 'icon_disk', 'public') ?: 'public';

        return [
            'benefit' => $benefit,
            'title' => data_get($value, 'title'),
            'text' => data_get($value, 'text'),
            'icon_disk' => $disk,
            'icon_path' => $path,
            'icon_url' => $path ? Storage::disk($disk)->url($path) : null,
        ];
    }

    protected function ensureHomeBenefitNumber(int $benefit): void
    {
        abort_unless(in_array($benefit, [1, 2, 3], true), 404, 'El beneficio solicitado no existe.');
    }
}
