<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportVariantAttributeFromCatalogRequest;
use App\Http\Requests\Admin\StoreVariantAttributeCatalogRequest;
use App\Http\Requests\Admin\StoreVariantAttributeRequest;
use App\Http\Requests\Admin\UpdateVariantAttributeCatalogRequest;
use App\Http\Requests\Admin\UpdateVariantAttributeRequest;
use App\Http\Resources\Admin\AdminVariantAttributeResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VariantAttributeController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $query = VariantAttribute::query()
            ->catalog()
            ->with(['values', 'user'])
            ->where(function ($query) use ($userId) {
                $query->where('is_system', true)
                    ->orWhere('user_id', $userId);
            })
            ->when($request->filled('scope'), function ($query) use ($request, $userId) {
                if ($request->input('scope') === 'system') {
                    $query->where('is_system', true);
                }

                if ($request->input('scope') === 'custom') {
                    $query->where('is_system', false)->where('user_id', $userId);
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->ordered();

        return response()->json([
            'ok' => true,
            'message' => 'Catálogo de atributos de variante obtenido correctamente.',
            'data' => AdminVariantAttributeResource::collection($query->get()),
        ]);
    }

    public function storeCatalog(StoreVariantAttributeCatalogRequest $request): JsonResponse
    {
        $attribute = VariantAttribute::query()->create([
            ...$request->validated(),
            'product_id' => null,
            'user_id' => $request->user()?->id,
            'is_system' => false,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Atributo personalizado creado correctamente.',
            'data' => new AdminVariantAttributeResource($attribute->load(['values', 'user'])),
        ], 201);
    }

    public function showCatalog(Request $request, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        return response()->json([
            'ok' => true,
            'message' => 'Atributo del catálogo obtenido correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->load(['values', 'user'])),
        ]);
    }

    public function updateCatalog(
        UpdateVariantAttributeCatalogRequest $request,
        VariantAttribute $variantAttribute
    ): JsonResponse {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        $data = $request->validated();

        if ($variantAttribute->is_system) {
            unset($data['name'], $data['slug']);
        }

        $variantAttribute->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Atributo del catálogo actualizado correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->fresh()->load(['values', 'user'])),
        ]);
    }

    public function destroyCatalog(Request $request, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        abort_if($variantAttribute->is_system, 422, 'Los atributos base del sistema no se pueden eliminar.');
        abort_unless((int) $variantAttribute->user_id === (int) $request->user()?->id, 403);

        $variantAttribute->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Atributo personalizado eliminado correctamente.',
        ]);
    }

    public function toggleCatalog(Request $request, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        $variantAttribute->update([
            'is_active' => ! $variantAttribute->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del atributo actualizado correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->fresh()->load(['values', 'user'])),
        ]);
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $query = $product->variantAttributes()
            ->with('values')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->ordered();

        return response()->json([
            'ok' => true,
            'message' => 'Atributos de variante obtenidos correctamente.',
            'data' => AdminVariantAttributeResource::collection($query->get()),
        ]);
    }

    public function store(StoreVariantAttributeRequest $request, Product $product): JsonResponse
    {
        $attribute = $product->variantAttributes()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
            'is_system' => false,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante creado correctamente.',
            'data' => new AdminVariantAttributeResource($attribute->load('values')),
        ], 201);
    }

    public function importFromCatalog(
        ImportVariantAttributeFromCatalogRequest $request,
        Product $product
    ): JsonResponse {
        $data = $request->validated();
        $sourceAttribute = VariantAttribute::query()
            ->catalog()
            ->with('values')
            ->findOrFail($data['catalog_attribute_id']);

        $this->ensureCatalogAttributeIsVisible($request, $sourceAttribute);

        abort_if(
            $product->variantAttributes()->where('slug', $sourceAttribute->slug)->exists(),
            422,
            'Este producto ya tiene ese atributo de variante.'
        );

        $attribute = DB::transaction(function () use ($data, $product, $request, $sourceAttribute) {
            $attribute = $product->variantAttributes()->create([
                'user_id' => $request->user()?->id,
                'name' => $sourceAttribute->name,
                'slug' => $sourceAttribute->slug,
                'is_system' => false,
                'sort_order' => $data['sort_order'] ?? $sourceAttribute->sort_order,
                'is_active' => $data['is_active'] ?? $sourceAttribute->is_active,
            ]);

            $sourceValues = $sourceAttribute->values();

            if (array_key_exists('value_ids', $data)) {
                $sourceValues->whereIn('id', $data['value_ids']);
            }

            $sourceValues->ordered()->get()->each(function ($value) use ($attribute) {
                $attribute->values()->create([
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'sort_order' => $value->sort_order,
                    'is_active' => $value->is_active,
                    'metadata' => $value->metadata,
                ]);
            });

            return $attribute;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Atributo importado al producto correctamente.',
            'data' => new AdminVariantAttributeResource($attribute->load('values')),
        ], 201);
    }

    public function show(Product $product, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante obtenido correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->load('values')),
        ]);
    }

    public function update(
        UpdateVariantAttributeRequest $request,
        Product $product,
        VariantAttribute $variantAttribute
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        $variantAttribute->update($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante actualizado correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->fresh()->load('values')),
        ]);
    }

    public function destroy(Product $product, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        $result = DB::transaction(function () use ($product, $variantAttribute) {
            $values = $variantAttribute->values()->get(['id', 'metadata']);
            $valueIds = $values->pluck('id')->values();

            $variantIds = ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereHas('attributeValues', fn ($query) => $query->whereIn('variant_attribute_values.id', $valueIds))
                ->pluck('id');

            ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->delete();

            $variantAttribute->delete();

            return [
                'deleted_values_count' => $values->count(),
                'deleted_variants_count' => $variantIds->count(),
                'image_paths' => $values
                    ->map(fn ($value) => [
                        'disk' => data_get($value->metadata, 'image_disk', 'public') ?: 'public',
                        'path' => data_get($value->metadata, 'image_path'),
                    ])
                    ->filter(fn ($file) => filled($file['path']))
                    ->values()
                    ->all(),
            ];
        });

        foreach ($result['image_paths'] as $file) {
            if (Storage::disk($file['disk'])->exists($file['path'])) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante eliminado correctamente.',
            'data' => [
                'deleted_values_count' => $result['deleted_values_count'],
                'deleted_variants_count' => $result['deleted_variants_count'],
            ],
        ]);
    }

    public function toggle(Product $product, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        $variantAttribute->update([
            'is_active' => ! $variantAttribute->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del atributo actualizado correctamente.',
            'data' => new AdminVariantAttributeResource($variantAttribute->fresh()->load('values')),
        ]);
    }

    protected function ensureAttributeBelongsToProduct(Product $product, VariantAttribute $attribute): void
    {
        abort_unless((int) $attribute->product_id === (int) $product->id, 404);
    }

    protected function ensureCatalogAttributeIsVisible(Request $request, VariantAttribute $attribute): void
    {
        abort_unless($attribute->product_id === null, 404);

        abort_unless(
            $attribute->is_system || (int) $attribute->user_id === (int) $request->user()?->id,
            404
        );
    }
}
