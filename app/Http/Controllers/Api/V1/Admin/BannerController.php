<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderBannersRequest;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\Banner\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = Banner::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
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
                'data' => BannerResource::collection($query->get()),
            ]);
        }

        $banners = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => BannerResource::collection($banners->getCollection()),
            'meta' => [
                'current_page' => $banners->currentPage(),
                'last_page' => $banners->lastPage(),
                'per_page' => $banners->perPage(),
                'total' => $banners->total(),
                'from' => $banners->firstItem(),
                'to' => $banners->lastItem(),
            ],
        ]);
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('media');

        $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
        $data['media_disk'] = 'public';
        $data['media_path'] = $file->store('banners', 'public');

        unset($data['media'], $data['name']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) Banner::query()->max('sort_order')) + 1;
        }

        $banner = Banner::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Banner creado correctamente.',
            'data' => new BannerResource($banner),
        ], 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new BannerResource($banner),
        ]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('media')) {
            $file = $request->file('media');

            if ($banner->media_path && Storage::disk($banner->media_disk ?: 'public')->exists($banner->media_path)) {
                Storage::disk($banner->media_disk ?: 'public')->delete($banner->media_path);
            }

            $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
            $data['media_disk'] = 'public';
            $data['media_path'] = $file->store('banners', 'public');
        }

        unset($data['media'], $data['name']);

        $banner->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Banner actualizado correctamente.',
            'data' => new BannerResource($banner->fresh()),
        ]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        if ($banner->media_path && Storage::disk($banner->media_disk ?: 'public')->exists($banner->media_path)) {
            Storage::disk($banner->media_disk ?: 'public')->delete($banner->media_path);
        }

        $banner->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Banner eliminado correctamente.',
        ]);
    }

    public function toggle(Banner $banner): JsonResponse
    {
        $banner->update([
            'is_active' => ! $banner->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del banner actualizado correctamente.',
            'data' => new BannerResource($banner->fresh()),
        ]);
    }

    public function reorder(ReorderBannersRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('banners') as $bannerData) {
                Banner::query()
                    ->whereKey($bannerData['id'])
                    ->update(['sort_order' => $bannerData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de banners actualizado correctamente.',
            'data' => BannerResource::collection(Banner::query()->ordered()->get()),
        ]);
    }

    protected function resolveMediaType(string $mimeType, ?string $requestedType = null): string
    {
        $detectedType = str_starts_with($mimeType, 'video/')
            ? Banner::MEDIA_TYPE_VIDEO
            : Banner::MEDIA_TYPE_IMAGE;

        if ($requestedType && $requestedType !== $detectedType) {
            abort(422, 'El tipo de media no coincide con el archivo enviado.');
        }

        return $detectedType;
    }
}
