<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderBrandBannersRequest;
use App\Http\Requests\Admin\StoreBrandBannerRequest;
use App\Http\Requests\Admin\UpdateBrandBannerRequest;
use App\Http\Resources\BrandBanner\BrandBannerResource;
use App\Models\BrandBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BrandBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = BrandBanner::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('media_type'), function ($query) use ($request) {
                $query->where('media_type', $request->string('media_type')->toString());
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->ordered();

        if (filter_var($request->input('without_pagination', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'data' => BrandBannerResource::collection($query->get()),
            ]);
        }

        $brandBanners = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => BrandBannerResource::collection($brandBanners->getCollection()),
            'meta' => [
                'current_page' => $brandBanners->currentPage(),
                'last_page' => $brandBanners->lastPage(),
                'per_page' => $brandBanners->perPage(),
                'total' => $brandBanners->total(),
                'from' => $brandBanners->firstItem(),
                'to' => $brandBanners->lastItem(),
            ],
        ]);
    }

    public function store(StoreBrandBannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('media');

        $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
        $data['media_disk'] = 'public';
        $data['media_path'] = $file->store('brand-banners', 'public');

        unset($data['media'], $data['name']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) BrandBanner::query()->max('sort_order')) + 1;
        }

        $brandBanner = BrandBanner::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Banner de marca creado correctamente.',
            'data' => new BrandBannerResource($brandBanner),
        ], 201);
    }

    public function show(BrandBanner $brandBanner): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new BrandBannerResource($brandBanner),
        ]);
    }

    public function update(UpdateBrandBannerRequest $request, BrandBanner $brandBanner): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('media')) {
            $file = $request->file('media');

            if ($brandBanner->media_path && Storage::disk($brandBanner->media_disk ?: 'public')->exists($brandBanner->media_path)) {
                Storage::disk($brandBanner->media_disk ?: 'public')->delete($brandBanner->media_path);
            }

            $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
            $data['media_disk'] = 'public';
            $data['media_path'] = $file->store('brand-banners', 'public');
        }

        unset($data['media'], $data['name']);

        $brandBanner->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Banner de marca actualizado correctamente.',
            'data' => new BrandBannerResource($brandBanner->fresh()),
        ]);
    }

    public function destroy(BrandBanner $brandBanner): JsonResponse
    {
        if ($brandBanner->media_path && Storage::disk($brandBanner->media_disk ?: 'public')->exists($brandBanner->media_path)) {
            Storage::disk($brandBanner->media_disk ?: 'public')->delete($brandBanner->media_path);
        }

        $brandBanner->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Banner de marca eliminado correctamente.',
        ]);
    }

    public function toggle(BrandBanner $brandBanner): JsonResponse
    {
        $brandBanner->update([
            'is_active' => ! $brandBanner->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del banner de marca actualizado correctamente.',
            'data' => new BrandBannerResource($brandBanner->fresh()),
        ]);
    }

    public function reorder(ReorderBrandBannersRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('banners') as $brandBannerData) {
                BrandBanner::query()
                    ->whereKey($brandBannerData['id'])
                    ->update(['sort_order' => $brandBannerData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de banners de marca actualizado correctamente.',
            'data' => BrandBannerResource::collection(BrandBanner::query()->ordered()->get()),
        ]);
    }

    protected function resolveMediaType(string $mimeType, ?string $requestedType = null): string
    {
        $detectedType = str_starts_with($mimeType, 'video/')
            ? BrandBanner::MEDIA_TYPE_VIDEO
            : BrandBanner::MEDIA_TYPE_IMAGE;

        if ($requestedType && $requestedType !== $detectedType) {
            abort(422, 'El tipo de media no coincide con el archivo enviado.');
        }

        return $detectedType;
    }
}
