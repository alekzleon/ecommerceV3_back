<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingRequest;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new SiteSettingResource(SiteSetting::current()),
        ]);
    }

    public function store(UpdateSiteSettingRequest $request): JsonResponse
    {
        return $this->save($request);
    }

    public function show(string $id): JsonResponse
    {
        return $this->index();
    }

    public function update(UpdateSiteSettingRequest $request, string $id): JsonResponse
    {
        return $this->save($request);
    }

    public function destroy(string $id): JsonResponse
    {
        $settings = SiteSetting::current();

        $this->deleteFile($settings->logo_path, $settings->logo_disk);
        $this->deleteFile($settings->favicon_path, $settings->favicon_disk);
        $this->deleteFile($settings->og_image_path, $settings->og_image_disk);

        $settings->update([
            'site_title' => null,
            'logo_path' => null,
            'favicon_path' => null,
            'contact_numbers' => [],
            'email' => null,
            'address' => null,
            'social_links' => [],
            'forms_recipient_email' => null,
            'meta' => [],
            'google_analytics_pixel' => null,
            'meta_pixel' => null,
            'loyalty' => [],
            'og_image_path' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Configuración reiniciada correctamente.',
            'data' => new SiteSettingResource($settings->fresh()),
        ]);
    }

    protected function save(UpdateSiteSettingRequest $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $data = $request->validated();

        if ($logo = $this->fileFrom($request, ['logo', 'identity.logo'])) {
            $this->deleteFile($settings->logo_path, $settings->logo_disk);
            $data['logo_disk'] = 'public';
            $data['logo_path'] = $logo->store('settings', 'public');
        }

        if ($favicon = $this->fileFrom($request, ['favicon', 'identity.favicon'])) {
            $this->deleteFile($settings->favicon_path, $settings->favicon_disk);
            $data['favicon_disk'] = 'public';
            $data['favicon_path'] = $favicon->store('settings', 'public');
        }

        if ($ogImage = $this->fileFrom($request, ['og_image', 'seo.og_image'])) {
            $this->deleteFile($settings->og_image_path, $settings->og_image_disk);
            $data['og_image_disk'] = 'public';
            $data['og_image_path'] = $ogImage->store('settings', 'public');
        }

        unset($data['logo'], $data['favicon'], $data['og_image'], $data['identity'], $data['seo']);

        if (array_key_exists('contact_numbers', $data)) {
            $data['contact_numbers'] = collect($data['contact_numbers'] ?? [])
                ->filter(fn ($number) => filled($number))
                ->values()
                ->all();
        }

        $settings->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Configuración actualizada correctamente.',
            'data' => new SiteSettingResource($settings->fresh()),
        ]);
    }

    protected function deleteFile(?string $path, ?string $disk): void
    {
        if ($path && Storage::disk($disk ?: 'public')->exists($path)) {
            Storage::disk($disk ?: 'public')->delete($path);
        }
    }

    protected function fileFrom(UpdateSiteSettingRequest $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            if ($request->hasFile($key)) {
                return $request->file($key);
            }
        }

        return null;
    }
}
