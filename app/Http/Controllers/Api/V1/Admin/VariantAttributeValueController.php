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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VariantAttributeValueController extends Controller
{
    public function indexCatalog(Request $request, VariantAttribute $variantAttribute): JsonResponse
    {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        $query = $variantAttribute->values()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('value', 'like', "%{$search}%")
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
            'message' => 'Valores del atributo obtenidos correctamente.',
            'data' => AdminVariantAttributeValueResource::collection($query->get()),
        ]);
    }

    public function storeCatalog(
        StoreVariantAttributeValueRequest $request,
        VariantAttribute $variantAttribute
    ): JsonResponse {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);

        $value = $variantAttribute->values()->create($this->valuePayload($request, $variantAttribute));

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo creado correctamente.',
            'data' => new AdminVariantAttributeValueResource($value->load('attribute')),
        ], 201);
    }

    public function updateCatalog(
        UpdateVariantAttributeValueRequest $request,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);
        $this->ensureValueBelongsToAttribute($variantAttribute, $attributeValue);

        $attributeValue->update($this->valuePayload($request, $variantAttribute, $attributeValue));

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo actualizado correctamente.',
            'data' => new AdminVariantAttributeValueResource($attributeValue->fresh()->load('attribute')),
        ]);
    }

    public function destroyCatalog(
        Request $request,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);
        $this->ensureValueBelongsToAttribute($variantAttribute, $attributeValue);

        $this->deleteColorImage($attributeValue);

        $attributeValue->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Valor de atributo eliminado correctamente.',
        ]);
    }

    public function toggleCatalog(
        Request $request,
        VariantAttribute $variantAttribute,
        VariantAttributeValue $attributeValue
    ): JsonResponse {
        $this->ensureCatalogAttributeIsVisible($request, $variantAttribute);
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

    public function store(
        StoreVariantAttributeValueRequest $request,
        Product $product,
        VariantAttribute $variantAttribute
    ): JsonResponse {
        $this->ensureAttributeBelongsToProduct($product, $variantAttribute);

        $value = $variantAttribute->values()->create($this->valuePayload($request, $variantAttribute));

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

        $attributeValue->update($this->valuePayload($request, $variantAttribute, $attributeValue));

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

        $this->deleteColorImage($attributeValue);

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

    protected function ensureCatalogAttributeIsVisible(Request $request, VariantAttribute $attribute): void
    {
        abort_unless($attribute->product_id === null, 404);

        abort_unless(
            $attribute->is_system || (int) $attribute->user_id === (int) $request->user()?->id,
            404
        );
    }

    protected function valuePayload(
        Request $request,
        VariantAttribute $attribute,
        ?VariantAttributeValue $value = null
    ): array {
        $data = $request->validated();
        unset($data['image'], $data['remove_image']);

        if ($attribute->slug !== 'color') {
            return $data;
        }

        $metadata = $data['metadata'] ?? $value?->metadata ?? [];
        $metadata = is_array($metadata) ? $metadata : [];

        if ($request->boolean('remove_image')) {
            $this->deleteColorImage($value);
            unset($metadata['image_disk'], $metadata['image_path']);
        }

        if ($request->hasFile('image')) {
            $this->deleteColorImage($value);

            $metadata['image_disk'] = 'public';
            $metadata['image_path'] = $request->file('image')->store('variant-colors', 'public');
        }

        $data['metadata'] = $metadata;

        return $data;
    }

    protected function deleteColorImage(?VariantAttributeValue $value): void
    {
        $path = data_get($value?->metadata, 'image_path');
        $disk = data_get($value?->metadata, 'image_disk', 'public') ?: 'public';

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
