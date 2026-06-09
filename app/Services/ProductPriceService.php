<?php

namespace App\Services;

use App\Models\ClaveCliente;
use App\Models\PrecioArticulo;
use App\Models\PrecioCliCli;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductPriceService
{
    public const DEFAULT_PRICE_COMPANY_ID = 7863;
    public const DEFAULT_PRICE_COMPANY_NAME = '3 Precio';

    public function priceCompanyIdForUser(?User $user): int
    {
        if (! $user) {
            return self::DEFAULT_PRICE_COMPANY_ID;
        }

        $customerKeys = $this->customerKeys($user);
        $hasCustomerId = filled($user->microsip_id) && is_numeric($user->microsip_id);

        if (! $hasCustomerId && empty($customerKeys)) {
            return self::DEFAULT_PRICE_COMPANY_ID;
        }

        $query = PrecioCliCli::query()
            ->where(function (Builder $query) use ($user, $customerKeys, $hasCustomerId) {
                if ($hasCustomerId) {
                    $query->orWhere('cliente_id', (int) $user->microsip_id);
                }

                foreach ($customerKeys as $key) {
                    $query->orWhere('clave_cliente', $key);
                }
            })
            ->orderByDesc('id');

        return (int) ($query->value('precio_empresa_id') ?: self::DEFAULT_PRICE_COMPANY_ID);
    }

    public function priceForProduct(Product $product, ?User $user = null): array
    {
        return $this->pricesForProducts(collect([$product]), $user)->get((int) $product->id)
            ?? $this->fallbackPayload($product, $this->priceCompanyIdForUser($user));
    }

    public function pricesForProducts(Collection $products, ?User $user = null): Collection
    {
        $products = $products->filter(fn ($product) => $product instanceof Product)->values();
        $priceCompanyId = $this->priceCompanyIdForUser($user);

        if ($products->isEmpty()) {
            return collect();
        }

        $productIds = $products->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();
        $targetPrices = $this->priceRowsByProductId($productIds, $priceCompanyId);
        $defaultPrices = $priceCompanyId === self::DEFAULT_PRICE_COMPANY_ID
            ? collect()
            : $this->priceRowsByProductId($productIds, self::DEFAULT_PRICE_COMPANY_ID);

        return $products->mapWithKeys(function (Product $product) use ($priceCompanyId, $targetPrices, $defaultPrices) {
            $targetPrice = $targetPrices->get((int) $product->id);
            $defaultPrice = $defaultPrices->get((int) $product->id);
            $targetHasValidPrice = $targetPrice && (
                $priceCompanyId === self::DEFAULT_PRICE_COMPANY_ID
                || round((float) $targetPrice->precio, 6) > 0
            );
            $row = $targetHasValidPrice ? $targetPrice : $defaultPrice;

            if (! $row) {
                return [(int) $product->id => $this->fallbackPayload($product, $priceCompanyId)];
            }

            return [(int) $product->id => [
                'price' => round((float) $row->precio, 2),
                'precio_empresa_id' => (int) $row->precio_empresa_id,
                'requested_precio_empresa_id' => $priceCompanyId,
                'is_default_price_list' => (int) $row->precio_empresa_id === self::DEFAULT_PRICE_COMPANY_ID,
                'source' => (int) $row->precio_empresa_id === self::DEFAULT_PRICE_COMPANY_ID
                    ? 'precios_articulos_default'
                    : 'precios_articulos',
            ]];
        });
    }

    public function decorateProducts(Collection $products, ?User $user = null): Collection
    {
        $prices = $this->pricesForProducts($products, $user);

        return $products->each(function (Product $product) use ($prices) {
            $payload = $prices->get((int) $product->id);

            if (! $payload) {
                return;
            }

            $product->setAttribute('current_price', $payload['price']);
            $product->setAttribute('price_company_id', $payload['precio_empresa_id']);
            $product->setAttribute('requested_price_company_id', $payload['requested_precio_empresa_id']);
            $product->setAttribute('is_default_price_list', $payload['is_default_price_list']);
            $product->setAttribute('price_source', $payload['source']);
        });
    }

    protected function priceRowsByProductId(Collection $productIds, int $priceCompanyId): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        return PrecioArticulo::query()
            ->whereIn('product_id', $productIds->all())
            ->where('precio_empresa_id', $priceCompanyId)
            ->orderByDesc('id')
            ->get()
            ->unique('product_id')
            ->keyBy(fn (PrecioArticulo $precioArticulo) => (int) $precioArticulo->product_id);
    }

    protected function fallbackPayload(Product $product, int $requestedPriceCompanyId): array
    {
        return [
            'price' => 0.0,
            'precio_empresa_id' => self::DEFAULT_PRICE_COMPANY_ID,
            'requested_precio_empresa_id' => $requestedPriceCompanyId,
            'is_default_price_list' => true,
            'source' => 'precios_articulos_default_missing',
        ];
    }

    protected function customerKeys(User $user): array
    {
        return collect([
            $user->username,
            $user->customerProfile?->id_microsip,
            $user->microsip_id,
        ])
            ->merge($this->syncedCustomerKeys($user))
            ->filter(fn ($key) => filled($key))
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->values()
            ->all();
    }

    protected function syncedCustomerKeys(User $user): array
    {
        if (! filled($user->microsip_id) || ! is_numeric($user->microsip_id)) {
            return [];
        }

        return ClaveCliente::query()
            ->where('cliente_id', (int) $user->microsip_id)
            ->pluck('clave_cliente')
            ->all();
    }
}
