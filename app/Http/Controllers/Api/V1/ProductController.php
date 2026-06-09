<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\CartStatus;
use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ProductPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        protected ProductPriceService $productPriceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 24));
        $sort = trim((string) $request->string('sort')->toString());
        $search = trim((string) $request->string('search')->toString());
        $categoryId = $request->filled('category_id') ? (int) $request->integer('category_id') : null;
        $categorySlug = trim((string) $request->string('category_slug')->toString());
        $familyId = $request->filled('family_id') ? (int) $request->integer('family_id') : null;
        $familySlug = trim((string) $request->string('family_slug')->toString());
        $brand = trim((string) $request->string('brand')->toString());
        $user = $this->currentUser($request);
        $userId = $user ? (int) $user->id : null;

        $query = Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug',
                'promotions' => function ($query) {
                    $query->usable(null)
                        ->orderBy('priority')
                        ->orderByDesc('id');
                },
            ])
            ->where('is_active', true);

        if ($userId) {
            $query->withExists([
                'favoritedByUsers as is_favorite' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $userId),
            ]);
        }

        if ($search !== '') {
            $normalizedSearch = preg_replace('/\s+/', ' ', $search);

            $query->where(function ($subQuery) use ($normalizedSearch) {
                $subQuery->where('name', 'like', '%' . $normalizedSearch . '%')
                    ->orWhere('description', 'like', '%' . $normalizedSearch . '%')
                    ->orWhere('short_description', 'like', '%' . $normalizedSearch . '%')
                    ->orWhere('brand', 'like', '%' . $normalizedSearch . '%')
                    ->orWhere('keyword', 'like', '%' . $normalizedSearch . '%')
                    ->orWhere('sku', 'like', '%' . $normalizedSearch . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($normalizedSearch) {
                        $categoryQuery->where('name', 'like', '%' . $normalizedSearch . '%');
                    })
                    ->orWhereHas('family', function ($familyQuery) use ($normalizedSearch) {
                        $familyQuery->where('name', 'like', '%' . $normalizedSearch . '%');
                    });
            });
        }

        if (!empty($categoryId)) {
            $query->where(function ($categoryFilter) use ($categoryId) {
                $categoryFilter->where('category_id', $categoryId)
                    ->orWhereHas('category', function ($categoryQuery) use ($categoryId) {
                        $categoryQuery->where('grupo_linea_id', $categoryId);
                    });
            });
        }

        if ($categorySlug !== '') {
            $query->whereHas('category', function ($categoryQuery) use ($categorySlug) {
                $categoryQuery->where('slug', $categorySlug);
            });
        }

        if (!empty($familyId)) {
            $query->where(function ($familyFilter) use ($familyId) {
                $familyFilter->where('family_id', $familyId)
                    ->orWhereHas('family', function ($familyQuery) use ($familyId) {
                        $familyQuery->where('linea_articulo_id', $familyId);
                    });
            });
        }

        if ($familySlug !== '') {
            $query->whereHas('family', function ($familyQuery) use ($familySlug) {
                $familyQuery->where('slug', $familySlug);
            });
        }

        if ($brand !== '') {
            $query->where('brand', $brand);
        }

        switch ($sort) {
            case 'price_asc':
                $this->orderByResolvedPrice($query, $user, 'asc');
                break;

            case 'price_desc':
                $this->orderByResolvedPrice($query, $user, 'desc');
                break;

            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;

            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;

            case 'relevant':
            default:
                $query->latest('id');
                break;
        }

        $products = $query->paginate($perPage)->appends($request->query());

        $this->productPriceService->decorateProducts($products->getCollection(), $user);
        $products->getCollection()->transform(fn (Product $product) => $this->formatProduct($product));

        return response()->json([
            'ok' => true,
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function recentPurchases(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $userId = $user ? (int) $user->id : null;
        $productIds = $userId ? $this->recentPurchasedProductIds($userId) : collect();
        $source = $productIds->isNotEmpty() ? 'recent_purchases' : 'random';

        if ($productIds->count() < 10) {
            $randomProductIds = Product::query()
                ->where('is_active', true)
                ->whereNotIn('id', $productIds)
                ->inRandomOrder()
                ->limit(10 - $productIds->count())
                ->pluck('id');

            $productIds = $productIds->merge($randomProductIds)->values();
        }

        $products = $this->productListQuery($userId)
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();

        $this->productPriceService->decorateProducts($products, $user);
        $products = $products->map(fn (Product $product) => $this->formatProduct($product));

        return response()->json([
            'ok' => true,
            'message' => $source === 'recent_purchases'
                ? 'Últimos productos comprados obtenidos correctamente.'
                : 'Productos aleatorios obtenidos correctamente.',
            'data' => $products,
            'meta' => [
                'source' => $source,
                'limit' => 10,
                'total' => $products->count(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $user = $this->currentUser($request);
        $userId = $user ? (int) $user->id : null;

        $product = $this->productListQuery($userId)
            ->with([
                'activeGalleryItems',
                'activeVariantAttributes.activeValues',
                'activeVariants.attributeValues.attribute',
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'ok' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $this->productPriceService->decorateProducts(collect([$product]), $user);

        return response()->json([
            'ok' => true,
            'data' => $this->formatProduct($product),
        ]);
    }

    protected function currentUser(Request $request): ?User
    {
        return $request->user('sanctum') ?? $request->user();
    }

    protected function currentUserId(Request $request): ?int
    {
        $user = $this->currentUser($request);

        return $user ? (int) $user->id : null;
    }

    protected function productListQuery(?int $userId = null)
    {
        return Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug',
                'promotions' => function ($query) {
                    $query->usable(null)
                        ->orderBy('priority')
                        ->orderByDesc('id');
                },
            ])
            ->when($userId, function ($query) use ($userId) {
                $query->withExists([
                    'favoritedByUsers as is_favorite' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $userId),
                ]);
            });
    }

    protected function recentPurchasedProductIds(int $userId)
    {
        return DB::table('cart_items')
            ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
            ->where('carts.user_id', $userId)
            ->where('carts.status', CartStatus::CONVERTED->value)
            ->whereNotNull('cart_items.product_id')
            ->select('cart_items.product_id')
            ->selectRaw('MAX(COALESCE(carts.converted_at, carts.updated_at)) as last_purchased_at')
            ->groupBy('cart_items.product_id')
            ->orderByDesc('last_purchased_at')
            ->limit(10)
            ->pluck('cart_items.product_id');
    }

    protected function formatProduct(Product $product): array
    {
        $price = $product->getAttribute('current_price') !== null
            ? (float) $product->getAttribute('current_price')
            : (float) $product->default_price;

        $activePromotions = $product->promotions
            ->map(fn (Promotion $promotion) => $this->formatProductPromotion($promotion, $price))
            ->values();

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'family_id' => $product->family_id,
            'category' => $product->category ? [
                'id' => $product->category->grupo_linea_id ?? $product->category->id,
                'local_id' => $product->category->id,
                'grupo_linea_id' => $product->category->grupo_linea_id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'family' => $product->family ? [
                'id' => $product->family->linea_articulo_id ?? $product->family->id,
                'local_id' => $product->family->id,
                'linea_articulo_id' => $product->family->linea_articulo_id,
                'category_id' => $product->family->grupo_linea_id ?? $product->family->category_id,
                'local_category_id' => $product->family->category_id,
                'grupo_linea_id' => $product->family->grupo_linea_id,
                'name' => $product->family->name,
                'slug' => $product->family->slug,
            ] : null,
            'microsip_id' => $product->microsip_id,
            ...$this->formatMicrosipFields($product),
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'image_path' => $product->image_path,
            'image_url' => $product->image_url,
            'default_price' => $price,
            'base_default_price' => (float) $product->default_price,
            'price_info' => [
                'precio_empresa_id' => $product->getAttribute('price_company_id') ?? ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'requested_precio_empresa_id' => $product->getAttribute('requested_price_company_id') ?? ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'is_default_price_list' => (bool) ($product->getAttribute('is_default_price_list') ?? true),
                'source' => $product->getAttribute('price_source') ?? 'precios_articulos_default_missing',
            ],
            'sku' => $product->sku,
            'is_active' => (bool) $product->is_active,
            'is_favorite' => (bool) $product->is_favorite,
            'brand' => $product->brand,
            'keyword' => $product->keyword,
            'processed' => (bool) $product->processed,
            'has_active_promotions' => $activePromotions->isNotEmpty(),
            'active_promotions_count' => $activePromotions->count(),
            'active_promotions' => $activePromotions,
            ...($product->relationLoaded('activeGalleryItems') ? [
                'gallery' => $product->activeGalleryItems
                    ->map(fn ($item) => $this->formatGalleryItem($item))
                    ->values(),
            ] : []),
            ...($product->relationLoaded('activeVariants') ? [
                'variants' => $product->activeVariants
                    ->map(fn ($variant) => $this->formatVariant($variant, $price))
                    ->values(),
            ] : []),
            ...($product->relationLoaded('activeVariantAttributes') ? [
                'variant_options' => $this->formatVariantOptions($product),
                'variant_attributes' => $this->formatVariantAttributes($product),
                'variant_attribute_values' => $this->formatVariantAttributeValues($product),
            ] : []),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }

    protected function formatMicrosipFields(Product $product): array
    {
        return [
            'es_almacenable' => $product->es_almacenable,
            'es_juego' => $product->es_juego,
            'estatus' => $product->estatus,
            'causa_susp' => $product->causa_susp,
            'fecha_susp' => $product->fecha_susp?->toDateString(),
            'imprimir_comp' => $product->imprimir_comp,
            'permitir_agregar_comp' => $product->permitir_agregar_comp,
            'linea_articulo_id' => $product->linea_articulo_id,
            'unidad_venta' => $product->unidad_venta,
            'unidad_compra' => $product->unidad_compra,
            'contenido_unidad_compra' => $product->contenido_unidad_compra !== null ? (float) $product->contenido_unidad_compra : null,
            'peso_unitario' => $product->peso_unitario !== null ? (float) $product->peso_unitario : null,
            'es_peso_variable' => $product->es_peso_variable,
            'seguimiento' => $product->seguimiento,
            'dias_garantia' => $product->dias_garantia,
            'es_importado' => $product->es_importado,
            'es_siempre_importado' => $product->es_siempre_importado,
            'pctje_arancel' => $product->pctje_arancel !== null ? (float) $product->pctje_arancel : null,
            'notas_compras' => $product->notas_compras,
            'imprimir_notas_compras' => $product->imprimir_notas_compras,
            'notas_ventas' => $product->notas_ventas,
            'imprimir_notas_ventas' => $product->imprimir_notas_ventas,
            'es_precio_variable' => $product->es_precio_variable,
            'cuenta_almacen' => $product->cuenta_almacen,
            'cuenta_costo_venta' => $product->cuenta_costo_venta,
            'cuenta_ventas' => $product->cuenta_ventas,
            'cuenta_dscto_ventas' => $product->cuenta_dscto_ventas,
            'cuenta_devol_ventas' => $product->cuenta_devol_ventas,
            'cuenta_compras' => $product->cuenta_compras,
            'cuenta_devol_compras' => $product->cuenta_devol_compras,
            'aplicar_factor_venta' => $product->aplicar_factor_venta,
            'factor_venta' => $product->factor_venta !== null ? (float) $product->factor_venta : null,
            'red_precio_con_impto' => $product->red_precio_con_impto,
            'factor_red_precio_con_impto' => $product->factor_red_precio_con_impto !== null ? (float) $product->factor_red_precio_con_impto : null,
            'usuario_creador' => $product->usuario_creador,
            'fecha_hora_creacion' => $product->fecha_hora_creacion?->toJSON(),
            'usuario_aut_creacion' => $product->usuario_aut_creacion,
            'usuario_ult_modif' => $product->usuario_ult_modif,
            'fecha_hora_ult_modif' => $product->fecha_hora_ult_modif?->toJSON(),
            'usuario_aut_modif' => $product->usuario_aut_modif,
        ];
    }

    protected function formatGalleryItem($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'media_type' => $item->media_type,
            'media_path' => $item->media_path,
            'media_url' => $item->media_url,
            'title' => $item->title,
            'description' => $item->description,
            'sort_order' => (int) $item->sort_order,
            'is_active' => (bool) $item->is_active,
        ];
    }

    protected function formatVariant($variant, float $productPrice): array
    {
        $price = $variant->price !== null ? (float) $variant->price : $productPrice;

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'price' => $price,
            'compare_price' => $variant->compare_price !== null ? (float) $variant->compare_price : null,
            'stock' => $variant->stock,
            'sort_order' => (int) $variant->sort_order,
            'is_active' => (bool) $variant->is_active,
            'applies_promotions' => (bool) $variant->applies_promotions,
            'metadata' => $variant->metadata,
            'attribute_value_ids' => $variant->relationLoaded('attributeValues')
                ? $variant->attributeValues->pluck('id')->values()
                : [],
            'attribute_values' => $variant->relationLoaded('attributeValues')
                ? $variant->attributeValues->map(fn ($value) => [
                    'id' => $value->id,
                    'variant_attribute_id' => $value->variant_attribute_id,
                    'attribute' => $value->relationLoaded('attribute') && $value->attribute ? [
                        'id' => $value->attribute->id,
                        'name' => $value->attribute->name,
                        'slug' => $value->attribute->slug,
                    ] : null,
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'metadata' => $value->metadata,
                ])->values()
                : [],
        ];
    }

    protected function formatVariantOptions(Product $product)
    {
        return $product->activeVariantAttributes
            ->map(function ($attribute) use ($product) {
                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'values' => $attribute->activeValues
                        ->map(fn ($value) => [
                            'id' => $value->id,
                            'value' => $value->value,
                            'slug' => $value->slug,
                            'metadata' => $value->metadata,
                            'variant_ids' => $this->variantIdsForAttributeValue($product, (int) $value->id),
                        ])
                        ->values(),
                ];
            })
            ->values();
    }

    protected function formatVariantAttributes(Product $product)
    {
        return $product->activeVariantAttributes
            ->map(fn ($attribute) => [
                'id' => $attribute->id,
                'product_id' => $attribute->product_id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'sort_order' => (int) $attribute->sort_order,
                'is_active' => (bool) $attribute->is_active,
            ])
            ->values();
    }

    protected function formatVariantAttributeValues(Product $product)
    {
        return $product->activeVariantAttributes
            ->flatMap(function ($attribute) use ($product) {
                return $attribute->activeValues
                    ->map(fn ($value) => [
                    'id' => $value->id,
                    'variant_attribute_id' => $value->variant_attribute_id,
                    'attribute' => [
                        'id' => $attribute->id,
                        'name' => $attribute->name,
                        'slug' => $attribute->slug,
                    ],
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'sort_order' => (int) $value->sort_order,
                    'is_active' => (bool) $value->is_active,
                    'metadata' => $value->metadata,
                    'variant_ids' => $this->variantIdsForAttributeValue($product, (int) $value->id),
                ]);
            })
            ->values();
    }

    protected function variantIdsForAttributeValue(Product $product, int $attributeValueId)
    {
        if (! $product->relationLoaded('activeVariants')) {
            return collect();
        }

        return $product->activeVariants
            ->filter(function ($variant) use ($attributeValueId) {
                return $variant->relationLoaded('attributeValues')
                    && $variant->attributeValues->contains('id', $attributeValueId);
            })
            ->pluck('id')
            ->values();
    }

    protected function formatProductPromotion(Promotion $promotion, float $productPrice): array
    {
        $config = $promotion->config ?? [];

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'slug' => $promotion->slug,
            'type' => $promotion->type->value,
            'label' => $promotion->type->label(),
            'description' => $promotion->description,
            'message' => $this->promotionMessage($promotion, $productPrice),
            'priority' => $promotion->priority,
            'starts_at' => $promotion->starts_at?->toDateTimeString(),
            'ends_at' => $promotion->ends_at?->toDateTimeString(),
            'config' => $config,
        ];
    }

    protected function promotionMessage(Promotion $promotion, float $productPrice): string
    {
        $config = $promotion->config ?? [];

        return match ($promotion->type) {
            PromotionType::BUNDLE_PAY_X_TAKE_Y => sprintf(
                'Lleva %s y paga %s',
                (int) data_get($config, 'buy_quantity', 0),
                (int) data_get($config, 'pay_quantity', 0)
            ),
            PromotionType::BUY_X_GET_Y => sprintf(
                'Compra %s y recibe %s con %s%% de descuento',
                (int) data_get($config, 'buy_quantity', 0),
                (int) data_get($config, 'target_quantity', 1),
                (float) data_get($config, 'discount_percentage', 100)
            ),
            PromotionType::BUY_X_GET_DISCOUNT => sprintf(
                'Compra %s y obtén %s%% de descuento',
                (int) data_get($config, 'buy_quantity', 0),
                (float) data_get($config, 'discount_percentage', 0)
            ),
            PromotionType::DIRECT_PERCENTAGE => sprintf(
                '%s%% de descuento',
                (float) data_get($config, 'discount_percentage', 0)
            ),
            PromotionType::STRIKETHROUGH_PRICE => sprintf(
                'Precio promocional: $%s',
                number_format((float) data_get($config, 'promotional_price', $productPrice), 2)
            ),
            PromotionType::BUY_SKU_GET_GIFT_ITEM => sprintf(
                'Compra %s y recibe regalo exclusivo',
                (int) data_get($config, 'buy_quantity', 1)
            ),
            PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM => sprintf(
                'Alcanza $%s en %s y elige regalo',
                number_format((float) data_get($config, 'minimum_amount', 0), 2),
                (string) data_get($config, 'brand', 'la marca')
            ),
            PromotionType::BRAND_AMOUNT_GET_PRODUCT => sprintf(
                'Alcanza $%s en %s y recibe SKU promocional',
                number_format((float) data_get($config, 'minimum_amount', 0), 2),
                (string) data_get($config, 'brand', 'la marca')
            ),
        };
    }

    protected function orderByResolvedPrice($query, ?User $user, string $direction): void
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $priceCompanyId = $this->productPriceService->priceCompanyIdForUser($user);
        $defaultPriceCompanyId = ProductPriceService::DEFAULT_PRICE_COMPANY_ID;
        $priceExpression = <<<'SQL'
COALESCE(
    (SELECT NULLIF(pa.precio, 0) FROM precios_articulos pa WHERE pa.product_id = products.id AND pa.precio_empresa_id = ? ORDER BY pa.id DESC LIMIT 1),
    (SELECT pad.precio FROM precios_articulos pad WHERE pad.product_id = products.id AND pad.precio_empresa_id = ? ORDER BY pad.id DESC LIMIT 1),
    0
)
SQL;

        $query
            ->orderByRaw($priceExpression . ' ' . $direction, [$priceCompanyId, $defaultPriceCompanyId])
            ->orderBy('name', 'asc');
    }
}
