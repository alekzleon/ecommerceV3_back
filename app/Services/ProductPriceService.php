<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductPriceService
{
    public const DEFAULT_PRICE_COMPANY_ID = 7863;
    public const DEFAULT_PRICE_COMPANY_NAME = '3 Precio';

    public function priceCompanyIdForUser(?User $user): int
    {
        return self::DEFAULT_PRICE_COMPANY_ID;
    }

    public function priceForProduct(Product $product, ?User $user = null): array
    {
        return $this->pricesForProducts(collect([$product]), $user)->get((int) $product->id)
            ?? $this->pricePayload($product);
    }

    public function pricesForProducts(Collection $products, ?User $user = null): Collection
    {
        $products = $products->filter(fn ($product) => $product instanceof Product)->values();

        if ($products->isEmpty()) {
            return collect();
        }

        return $products->mapWithKeys(fn (Product $product) => [
            (int) $product->id => $this->pricePayload($product),
        ]);
    }

    protected function pricePayload(Product $product): array
    {
        return [
            'price' => round((float) $product->default_price, 2),
            'precio_empresa_id' => self::DEFAULT_PRICE_COMPANY_ID,
            'requested_precio_empresa_id' => self::DEFAULT_PRICE_COMPANY_ID,
            'is_default_price_list' => true,
            'source' => 'products.default_price',
        ];
    }
}
