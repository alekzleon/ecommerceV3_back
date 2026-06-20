<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAbandonedCartSettingRequest;
use App\Http\Requests\Admin\UpdateContactFaqImageRequest;
use App\Http\Requests\Admin\UpdateContactMapUrlRequest;
use App\Http\Requests\Admin\UpdateGeneralLogoRequest;
use App\Http\Requests\Admin\UpdateMetaPixelSettingRequest;
use App\Http\Requests\Admin\UpdateNavTitleSettingRequest;
use App\Models\EcommerceSetting;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EcommerceSettingController extends Controller
{
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

    public function updateAbandonedCart(UpdateAbandonedCartSettingRequest $request): JsonResponse
    {
        $setting = EcommerceSetting::setValue(EcommerceSetting::KEY_ABANDONED_CART, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Configuración de carrito abandonado actualizada correctamente.',
            'data' => [
                'key' => $setting->key,
                'value' => EcommerceSetting::abandonedCartSettings(),
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

    public function updateNavTitle(UpdateNavTitleSettingRequest $request): JsonResponse
    {
        $setting = EcommerceSetting::setValue(EcommerceSetting::KEY_NAV_TITLE, [
            'title' => $request->validated('title'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Título del nav actualizado correctamente.',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
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

    public function updateGeneralLogo(UpdateGeneralLogoRequest $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $file = $request->file('logo');

        if ($settings->logo_path && Storage::disk($settings->logo_disk ?: 'public')->exists($settings->logo_path)) {
            Storage::disk($settings->logo_disk ?: 'public')->delete($settings->logo_path);
        }

        $settings->update([
            'logo_disk' => 'public',
            'logo_path' => $file->store('settings', 'public'),
        ]);

        $settings = $settings->fresh();

        return response()->json([
            'ok' => true,
            'message' => 'Logo general actualizado correctamente.',
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

    public function updateContactFaqImage(UpdateContactFaqImageRequest $request): JsonResponse
    {
        $current = EcommerceSetting::getValue(EcommerceSetting::KEY_CONTACT_FAQ_IMAGE, []);
        $currentPath = data_get($current, 'image_path');
        $currentDisk = data_get($current, 'image_disk', 'public') ?: 'public';

        if ($currentPath && Storage::disk($currentDisk)->exists($currentPath)) {
            Storage::disk($currentDisk)->delete($currentPath);
        }

        $path = $request->file('image')->store('settings/contact', 'public');

        EcommerceSetting::setValue(EcommerceSetting::KEY_CONTACT_FAQ_IMAGE, [
            'image_disk' => 'public',
            'image_path' => $path,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Imagen de preguntas frecuentes actualizada correctamente.',
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

    public function updateContactMapUrl(UpdateContactMapUrlRequest $request): JsonResponse
    {
        $setting = EcommerceSetting::setValue(EcommerceSetting::KEY_CONTACT_MAP_URL, [
            'url' => $request->validated('url'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Link del mapa de contacto actualizado correctamente.',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
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

    public function updateMetaPixel(UpdateMetaPixelSettingRequest $request): JsonResponse
    {
        $setting = EcommerceSetting::setValue(EcommerceSetting::KEY_META_PIXEL, [
            'pixel_id' => $request->validated('pixel_id'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Meta Pixel ID actualizado correctamente.',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
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
