<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EcommerceSetting;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EcommerceSettingController extends Controller
{
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
}
