<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAbandonedCartSettingRequest;
use App\Http\Requests\Admin\UpdateContactFaqImageRequest;
use App\Http\Requests\Admin\UpdateContactMapUrlRequest;
use App\Http\Requests\Admin\UpdateGeneralLogoRequest;
use App\Http\Requests\Admin\UpdateHomeBenefitRequest;
use App\Http\Requests\Admin\UpdateMetaPixelSettingRequest;
use App\Http\Requests\Admin\UpdateNavTitleSettingRequest;
use App\Http\Requests\Admin\UpdateSaleNotificationSettingRequest;
use App\Http\Requests\Admin\UpdateStorefrontSettingRequest;
use App\Models\EcommerceSetting;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EcommerceSettingController extends Controller
{
    public function storefront(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->storefrontPayload(),
        ]);
    }

    public function updateStorefront(UpdateStorefrontSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('is_published', $validated) ||
            array_key_exists('construction_title', $validated) ||
            array_key_exists('construction_message', $validated)) {
            $storefront = EcommerceSetting::storefrontSettings();

            foreach (['is_published', 'construction_title', 'construction_message'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $storefront[$field] = $validated[$field];
                }
            }

            EcommerceSetting::setValue(EcommerceSetting::KEY_STOREFRONT, $storefront);
        }

        $activeTemplate = $validated['active_template'] ?? $validated['template'] ?? null;

        if ($activeTemplate !== null) {
            EcommerceSetting::setValue(EcommerceSetting::KEY_HOME_TEMPLATE, [
                'active_template' => $activeTemplate,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Configuración de publicación y plantilla visual actualizada correctamente.',
            'data' => $this->storefrontPayload(),
        ]);
    }

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

    public function updateHomeBenefit(UpdateHomeBenefitRequest $request, int $benefit): JsonResponse
    {
        $this->ensureHomeBenefitNumber($benefit);

        $current = EcommerceSetting::homeBenefitValue($benefit);
        $value = array_merge($current, [
            'benefit' => $benefit,
        ]);
        $validated = $request->validated();

        if (array_key_exists('title', $validated)) {
            $value['title'] = $validated['title'];
        }

        if (array_key_exists('text', $validated)) {
            $value['text'] = $validated['text'];
        }

        $currentPath = data_get($current, 'icon_path');
        $currentDisk = data_get($current, 'icon_disk', 'public') ?: 'public';

        if ($request->boolean('remove_icon') && $currentPath) {
            $this->deleteFile($currentPath, $currentDisk);
            $value['icon_disk'] = 'public';
            $value['icon_path'] = null;
        }

        $icon = $request->file('icon') ?: $request->file('icono');

        if ($icon) {
            $this->deleteFile($currentPath, $currentDisk);
            $value['icon_disk'] = 'public';
            $value['icon_path'] = $icon->store('settings/home-benefits', 'public');
        }

        $setting = EcommerceSetting::setValue(EcommerceSetting::homeBenefitKey($benefit), $value);

        return response()->json([
            'ok' => true,
            'message' => "Beneficio {$benefit} actualizado correctamente.",
            'data' => [
                'key' => $setting->key,
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

    public function updateSaleNotifications(UpdateSaleNotificationSettingRequest $request): JsonResponse
    {
        $setting = EcommerceSetting::setValue(EcommerceSetting::KEY_SALE_NOTIFICATIONS, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Configuración de notificaciones de venta actualizada correctamente.',
            'data' => [
                'key' => $setting->key,
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

    protected function storefrontPayload(): array
    {
        $storefront = EcommerceSetting::storefrontSettings();
        $template = EcommerceSetting::homeTemplateSettings();

        return [
            'is_published' => (bool) data_get($storefront, 'is_published', false),
            'construction' => [
                'title' => data_get($storefront, 'construction_title'),
                'message' => data_get($storefront, 'construction_message'),
            ],
            'active_template' => data_get($template, 'active_template', EcommerceSetting::HOME_TEMPLATE_CLASSIC),
            'available_templates' => EcommerceSetting::availableTemplates(),
        ];
    }

    protected function deleteFile(?string $path, ?string $disk): void
    {
        if ($path && Storage::disk($disk ?: 'public')->exists($path)) {
            Storage::disk($disk ?: 'public')->delete($path);
        }
    }
}
