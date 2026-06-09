<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantAttributeValueRequest;
use App\Http\Requests\Admin\UpdateVariantAttributeValueRequest;
use App\Http\Resources\Admin\AdminVariantAttributeValueResource;
use App\Models\Product;
use App\Models\VariantAttribute;
use App\Models\VariantAttributeValue;
use Illuminate\Http\JsonResponse;

class VariantAttributeValueController extends Controller
{
    public function store(
        StoreVariantAttributeValueRequest $request,
        Product $product,
        VariantAttribute $variantAttribute
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        $value = $variantAttribute->values()->create($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo creado correctamente.',
            'data' => new AdminVariantAttributeValueResource($value->load('attribute')),
        ], 201);
    }

    public function update(
        UpdateVariantAttributeValueRequest $request,
        Product $product,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);
        $this->ensureValueBelongsToAttribute($variantAttribute, $attributeValue);

        $attributeValue->update($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo actualizado correctamente.',
            'data' => new AdminVariantAttributeValueResource($attributeValue->fresh()->load('attribute')),
        ]);
    }

    public function destroy(
        Product $product,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);
        $this->ensureValueBelongsToAttribute($variantAttribute, $attributeValue);

        $attributeValue->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo eliminado correctamente.',
        ]);
    }

    public function toggle(
        Product $product,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);
        $this->ensureValueBelongsToAttribute($variantAttribute, $attributeValue);

        $attributeValue->update([
            'is_active' => ! $attributeValue->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del valor actualizado correctamente.',
            'data' => new AdminVariantAttributeValueResource($attributeValue->fresh()->load('attribute')),
        ]);
    }

    protected function ensureValueBelongsToAttribute(VariantAttribute $attribute, VariantAttributeValue $value): void
    {
        abort_unless((int) $value->variant_attribute_id === (int) $attribute->id, 404);
    }

    protected function ensureAttributeBelongsToProduct(Product $product, VariantAttribute $attribute): void
    {
        abort_unless((int) $attribute->product_id === (int) $product->id, 404);
    }
}
