<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantAttributeRequest;
use App\Http\Requests\Admin\UpdateVariantAttributeRequest;
use App\Http\Resources\Admin\AdminVariantAttributeResource;
use App\Models\Product;
use App\Models\VariantAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariantAttributeController extends Controller
{
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
        $attribute = $product->variantAttributes()->create($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante creado correctamente.',
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

        $variantAttribute->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Atributo de variante eliminado correctamente.',
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
}
