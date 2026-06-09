<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderProductVariantsRequest;
use App\Http\Requests\Admin\StoreProductVariantRequest;
use App\Http\Requests\Admin\UpdateProductVariantRequest;
use App\Http\Resources\Admin\AdminProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $query = $product->variants()
            ->with('attributeValues.attribute')
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->when($request->has('applies_promotions') && $request->input('applies_promotions') !== '', function ($query) use ($request) {
                $appliesPromotions = filter_var($request->input('applies_promotions'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($appliesPromotions !== null) {
                    $query->where('applies_promotions', $appliesPromotions);
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });

        return response()->json([
            'ok' => true,
            'message' => 'Variantes de producto obtenidas correctamente.',
            'data' => AdminProductVariantResource::collection($query->get()),
        ]);
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $attributeValueIds = $data['attribute_value_ids'] ?? [];

        unset($data['attribute_value_ids']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) $product->variants()->max('sort_order')) + 1;
        }

        $variant = DB::transaction(function () use ($product, $data, $attributeValueIds) {
            $variant = $product->variants()->create($data);
            $variant->attributeValues()->sync($attributeValueIds);

            return $variant;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Variante creada correctamente.',
            'data' => new AdminProductVariantResource($variant->fresh()->load('attributeValues.attribute')),
        ], 201);
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);

        return response()->json([
            'ok' => true,
            'message' => 'Variante obtenida correctamente.',
            'data' => new AdminProductVariantResource($variant->load('attributeValues.attribute')),
        ]);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);

        $data = $request->validated();
        $attributeValueIds = $data['attribute_value_ids'] ?? null;

        unset($data['attribute_value_ids']);

        DB::transaction(function () use ($variant, $data, $attributeValueIds, $request) {
            $variant->update($data);

            if ($request->has('attribute_value_ids')) {
                $variant->attributeValues()->sync($attributeValueIds ?? []);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Variante actualizada correctamente.',
            'data' => new AdminProductVariantResource($variant->fresh()->load('attributeValues.attribute')),
        ]);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);

        $variant->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Variante eliminada correctamente.',
        ]);
    }

    public function updateStatus(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $variant->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado de variante actualizado correctamente.',
            'data' => new AdminProductVariantResource($variant->fresh()->load('attributeValues.attribute')),
        ]);
    }

    public function reorder(ReorderProductVariantsRequest $request, Product $product): JsonResponse
    {
        DB::transaction(function () use ($request, $product) {
            foreach ($request->validated('variants') as $variantData) {
                $product->variants()
                    ->whereKey($variantData['id'])
                    ->update(['sort_order' => $variantData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de variantes actualizado correctamente.',
            'data' => AdminProductVariantResource::collection(
                $product->variants()->with('attributeValues.attribute')->get()
            ),
        ]);
    }

    protected function ensureVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        abort_unless((int) $variant->product_id === (int) $product->id, 404);
    }
}
