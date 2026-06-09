<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderProductGalleryItemsRequest;
use App\Http\Requests\Admin\StoreProductGalleryItemRequest;
use App\Http\Requests\Admin\UpdateProductGalleryItemRequest;
use App\Http\Resources\Admin\AdminProductGalleryItemResource;
use App\Models\Product;
use App\Models\ProductGalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductGalleryItemController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $query = $product->galleryItems()
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->when($request->filled('media_type'), function ($query) use ($request) {
                $query->where('media_type', $request->string('media_type')->toString());
            })
            ->ordered();

        return response()->json([
            'ok' => true,
            'message' => 'Galería de producto obtenida correctamente.',
            'data' => AdminProductGalleryItemResource::collection($query->get()),
        ]);
    }

    public function store(StoreProductGalleryItemRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('media');

        $data['product_id'] = $product->id;
        $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
        $data['media_disk'] = 'public';
        $data['media_path'] = $file->store("products/{$product->id}/gallery", 'public');

        unset($data['media']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) $product->galleryItems()->max('sort_order')) + 1;
        }

        $galleryItem = ProductGalleryItem::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Elemento de galería creado correctamente.',
            'data' => new AdminProductGalleryItemResource($galleryItem),
        ], 201);
    }

    public function show(Product $product, ProductGalleryItem $galleryItem): JsonResponse
    {
        $this->ensureGalleryItemBelongsToProduct($product, $galleryItem);

        return response()->json([
            'ok' => true,
            'message' => 'Elemento de galería obtenido correctamente.',
            'data' => new AdminProductGalleryItemResource($galleryItem),
        ]);
    }

    public function update(
        UpdateProductGalleryItemRequest $request,
        Product $product,
        ProductGalleryItem $galleryItem
    ): JsonResponse {
        $this->ensureGalleryItemBelongsToProduct($product, $galleryItem);

        $data = $request->validated();

        if ($request->hasFile('media')) {
            $file = $request->file('media');

            if ($galleryItem->media_path && Storage::disk($galleryItem->media_disk ?: 'public')->exists($galleryItem->media_path)) {
                Storage::disk($galleryItem->media_disk ?: 'public')->delete($galleryItem->media_path);
            }

            $data['media_type'] = $this->resolveMediaType($file->getMimeType(), $data['media_type'] ?? null);
            $data['media_disk'] = 'public';
            $data['media_path'] = $file->store("products/{$product->id}/gallery", 'public');
        }

        unset($data['media']);

        $galleryItem->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Elemento de galería actualizado correctamente.',
            'data' => new AdminProductGalleryItemResource($galleryItem->fresh()),
        ]);
    }

    public function destroy(Product $product, ProductGalleryItem $galleryItem): JsonResponse
    {
        $this->ensureGalleryItemBelongsToProduct($product, $galleryItem);

        if ($galleryItem->media_path && Storage::disk($galleryItem->media_disk ?: 'public')->exists($galleryItem->media_path)) {
            Storage::disk($galleryItem->media_disk ?: 'public')->delete($galleryItem->media_path);
        }

        $galleryItem->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Elemento de galería eliminado correctamente.',
        ]);
    }

    public function toggle(Product $product, ProductGalleryItem $galleryItem): JsonResponse
    {
        $this->ensureGalleryItemBelongsToProduct($product, $galleryItem);

        $galleryItem->update([
            'is_active' => ! $galleryItem->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del elemento de galería actualizado correctamente.',
            'data' => new AdminProductGalleryItemResource($galleryItem->fresh()),
        ]);
    }

    public function reorder(ReorderProductGalleryItemsRequest $request, Product $product): JsonResponse
    {
        DB::transaction(function () use ($request, $product) {
            foreach ($request->validated('items') as $itemData) {
                $product->galleryItems()
                    ->whereKey($itemData['id'])
                    ->update(['sort_order' => $itemData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de galería actualizado correctamente.',
            'data' => AdminProductGalleryItemResource::collection($product->galleryItems()->ordered()->get()),
        ]);
    }

    protected function resolveMediaType(string $mimeType, ?string $requestedType = null): string
    {
        $detectedType = str_starts_with($mimeType, 'video/')
            ? ProductGalleryItem::MEDIA_TYPE_VIDEO
            : ProductGalleryItem::MEDIA_TYPE_IMAGE;

        if ($requestedType && $requestedType !== $detectedType) {
            abort(422, 'El tipo de media no coincide con el archivo enviado.');
        }

        return $detectedType;
    }

    protected function ensureGalleryItemBelongsToProduct(Product $product, ProductGalleryItem $galleryItem): void
    {
        abort_unless((int) $galleryItem->product_id === (int) $product->id, 404);
    }
}
