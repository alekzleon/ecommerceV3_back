<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductPriceScalesRequest;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductPriceScaleController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->payload($product),
        ]);
    }

    public function update(UpdateProductPriceScalesRequest $request, Product $product): JsonResponse
    {
        $promotion = $this->promotionForProduct($product);
        $validated = $request->validated();

        $promotion->fill([
            'name' => $validated['name'] ?? "Escalas {$product->name}",
            'slug' => $promotion->exists ? $promotion->slug : $this->uniqueSlug("escalas-{$product->slug}"),
            'description' => $validated['description'] ?? null,
            'type' => PromotionType::PRICE_SCALE_PERCENTAGE,
            'is_active' => $validated['is_active'] ?? true,
            'requires_login' => false,
            'is_general' => true,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'config' => [
                'scales' => $request->normalizedScales(),
            ],
        ]);

        $promotion->save();
        $promotion->products()->sync([$product->id]);

        return response()->json([
            'ok' => true,
            'message' => 'Escalas de precio actualizadas correctamente.',
            'data' => $this->payload($product->fresh()),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $promotion = $this->existingPromotionForProduct($product);

        if ($promotion) {
            $promotion->update(['is_active' => false]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Escalas de precio desactivadas correctamente.',
            'data' => $this->payload($product->fresh()),
        ]);
    }

    protected function payload(Product $product): array
    {
        $promotion = $this->existingPromotionForProduct($product);
        $scales = collect(data_get($promotion?->config, 'scales', []))
            ->sortBy('from_quantity')
            ->values()
            ->all();

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
            ],
            'promotion_id' => $promotion?->id,
            'name' => $promotion?->name,
            'description' => $promotion?->description,
            'is_active' => (bool) ($promotion?->is_active ?? false),
            'starts_at' => $promotion?->starts_at?->toDateTimeString(),
            'ends_at' => $promotion?->ends_at?->toDateTimeString(),
            'scales' => $scales,
        ];
    }

    protected function promotionForProduct(Product $product): Promotion
    {
        return $this->existingPromotionForProduct($product) ?? new Promotion();
    }

    protected function existingPromotionForProduct(Product $product): ?Promotion
    {
        return $product->promotions()
            ->where('type', PromotionType::PRICE_SCALE_PERCENTAGE->value)
            ->first();
    }

    protected function uniqueSlug(string $base): string
    {
        $baseSlug = Str::slug($base) ?: 'escalas-producto';
        $slug = $baseSlug;
        $counter = 1;

        while (Promotion::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
