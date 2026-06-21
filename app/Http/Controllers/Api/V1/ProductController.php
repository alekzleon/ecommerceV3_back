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
use Illuminate\Support\Str;

class ProductController extends Controller
{
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
                $query->orderBy('default_price', 'asc');
                break;

            case 'price_desc':
                $query->orderBy('default_price', 'desc');
                break;

            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;

            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;

            case 'relevant':
            default:
                $query->orderBy('name', 'asc')->orderBy('id', 'asc');
                break;
        }

        $products = $query->paginate($perPage)->appends($request->query());

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
        $limit = max(1, min((int) $request->integer('limit', 10), 24));
        $productIds = $userId ? $this->recentPurchasedProductIds($userId, $limit) : collect();
        $source = $productIds->isNotEmpty() ? 'recent_purchases' : 'random';

        if ($productIds->isEmpty()) {
            $contextTerms = $this->homeContextTerms($request);

            if ($contextTerms->isNotEmpty()) {
                $productIds = $this->relatedProductIdsFromTerms($contextTerms, $limit);
                $source = $productIds->isNotEmpty() ? 'search_context' : 'random';
            }
        }

        if ($productIds->count() < $limit) {
            $randomProductIds = Product::query()
                ->where('is_active', true)
                ->whereNotIn('id', $productIds)
                ->inRandomOrder()
                ->limit($limit - $productIds->count())
                ->pluck('id');

            $productIds = $productIds->merge($randomProductIds)->values();
        }

        $products = $this->productListQuery($userId)
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();

        $products = $products->map(fn (Product $product) => $this->formatProduct($product));

        return response()->json([
            'ok' => true,
            'message' => match ($source) {
                'recent_purchases' => 'Últimos productos comprados obtenidos correctamente.',
                'search_context' => 'Productos relacionados con el contexto de búsqueda obtenidos correctamente.',
                default => 'Productos aleatorios obtenidos correctamente.',
            },
            'data' => $products,
            'meta' => [
                'source' => $source,
                'limit' => $limit,
                'total' => $products->count(),
            ],
        ]);
    }

    public function smartSearch(Request $request): JsonResponse
    {
        $queryText = trim((string) $request->string('q')->toString());
        $perPage = max(1, min((int) $request->integer('per_page', 24), 60));
        $page = max(1, (int) $request->integer('page', 1));
        $user = $this->currentUser($request);
        $userId = $user ? (int) $user->id : null;
        $parsed = $this->parseSmartSearchQuery($queryText);
        $terms = collect($parsed['terms']);
        $inStockOnly = filter_var($request->input('in_stock', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;

        $productsQuery = $this->productListQuery($userId)
            ->where('is_active', true)
            ->when($inStockOnly, fn ($query) => $query->where(function ($stockQuery) {
                $stockQuery->whereNull('stock')->orWhere('stock', '>', 0);
            }))
            ->when($parsed['min_price'] !== null, fn ($query) => $query->where('default_price', '>=', $parsed['min_price']))
            ->when($parsed['max_price'] !== null, fn ($query) => $query->where('default_price', '<=', $parsed['max_price']));

        if ($terms->isNotEmpty() && $parsed['max_price'] === null && $parsed['min_price'] === null) {
            $productsQuery->where(fn ($subQuery) => $this->applySmartSearchTerms($subQuery, $terms));
        }

        $products = $productsQuery
            ->limit(500)
            ->get()
            ->map(function (Product $product) use ($parsed, $terms) {
                $match = $this->scoreSmartSearchProduct($product, $terms, $parsed);
                $payload = $this->formatProduct($product);
                $payload['relevance_score'] = $match['score'];
                $payload['match_reasons'] = $match['reasons'];

                return $payload;
            })
            ->filter(function (array $product) use ($terms, $parsed) {
                if ($terms->isEmpty()) {
                    return true;
                }

                if ($parsed['max_price'] !== null || $parsed['min_price'] !== null) {
                    return $product['relevance_score'] >= 5;
                }

                return $product['relevance_score'] > 0;
            })
            ->sortByDesc('relevance_score')
            ->values();

        $total = $products->count();
        $items = $products->forPage($page, $perPage)->values();

        return response()->json([
            'ok' => true,
            'message' => 'Búsqueda inteligente aplicada correctamente.',
            'data' => [
                'query' => $queryText,
                'interpreted' => [
                    'intent' => $parsed['intent'],
                    'recipient' => $parsed['recipient'],
                    'min_price' => $parsed['min_price'],
                    'max_price' => $parsed['max_price'],
                    'keywords' => $parsed['keywords'],
                    'terms' => $parsed['terms'],
                    'filters' => [
                        'price_gte' => $parsed['min_price'],
                        'price_lte' => $parsed['max_price'],
                        'in_stock' => $inStockOnly,
                    ],
                ],
                'products' => $items,
            ],
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
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

    protected function recentPurchasedProductIds(int $userId, int $limit = 10)
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
            ->limit($limit)
            ->pluck('cart_items.product_id');
    }

    protected function homeContextTerms(Request $request)
    {
        $terms = $request->input('search_terms', []);

        if (is_string($terms)) {
            $terms = preg_split('/[,|]/', $terms) ?: [];
        }

        if (!is_array($terms)) {
            return collect();
        }

        return collect($terms)
            ->map(fn ($term) => preg_replace('/\s+/', ' ', trim((string) $term)))
            ->filter(fn ($term) => filled($term) && mb_strlen($term) >= 2)
            ->unique()
            ->take(5)
            ->values();
    }

    protected function relatedProductIdsFromTerms($terms, int $limit)
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where(function ($subQuery) use ($terms) {
                foreach ($terms as $term) {
                    $subQuery->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%")
                        ->orWhere('brand', 'like', "%{$term}%")
                        ->orWhere('keyword', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($term) {
                            $categoryQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('family', function ($familyQuery) use ($term) {
                            $familyQuery->where('name', 'like', "%{$term}%");
                        });
                }
            })
            ->inRandomOrder()
            ->limit($limit);

        return $query->pluck('id');
    }

    protected function parseSmartSearchQuery(string $query): array
    {
        $normalized = $this->normalizeSmartSearchText($query);
        $maxPrice = $this->extractSmartSearchPrice($normalized, [
            '/(?:menos\s+de|menor\s+a|menor\s+de|hasta|maximo|max|debajo\s+de|por\s+menos\s+de)\s*\$?\s*([0-9]+(?:[.,][0-9]+)?)/',
            '/\$?\s*([0-9]+(?:[.,][0-9]+)?)\s*(?:pesos|mxn)?\s*(?:o\s+menos|para\s+abajo)/',
        ]);
        $minPrice = $this->extractSmartSearchPrice($normalized, [
            '/(?:mas\s+de|mayor\s+a|mayor\s+de|desde|minimo|min)\s*\$?\s*([0-9]+(?:[.,][0-9]+)?)/',
        ]);

        $intent = str_contains($normalized, 'regalo') || str_contains($normalized, 'detalle') || str_contains($normalized, 'presente')
            ? 'gift'
            : 'product_search';
        $recipient = $this->extractSmartSearchRecipient($normalized);
        $keywords = $this->smartSearchKeywords($normalized);
        $terms = $this->expandSmartSearchTerms($keywords, $intent, $recipient);

        return [
            'intent' => $intent,
            'recipient' => $recipient,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'keywords' => $keywords,
            'terms' => $terms,
        ];
    }

    protected function extractSmartSearchPrice(string $query, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                return round((float) str_replace(',', '.', $matches[1]), 2);
            }
        }

        return null;
    }

    protected function extractSmartSearchRecipient(string $query): ?string
    {
        $recipients = [
            'mama' => ['mama', 'madre', 'mamá'],
            'papa' => ['papa', 'padre', 'papá'],
            'mujer' => ['mujer', 'esposa', 'novia', 'hermana', 'abuela'],
            'hombre' => ['hombre', 'esposo', 'novio', 'hermano', 'abuelo'],
            'nino' => ['nino', 'niño', 'infantil', 'kids'],
            'oficina' => ['oficina', 'trabajo', 'home office'],
        ];

        foreach ($recipients as $recipient => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($query, $this->normalizeSmartSearchText($alias))) {
                    return $recipient;
                }
            }
        }

        return null;
    }

    protected function smartSearchKeywords(string $query): array
    {
        $query = preg_replace('/\b[0-9]+(?:[.,][0-9]+)?\b/', ' ', $query);
        $query = preg_replace('/\b(?:pesos|mxn|menos|menor|mayor|hasta|maximo|max|minimo|min|desde|para|por|de|del|la|el|los|las|un|una|unos|unas|y|o|con|sin|que|sea|algo|busco|buscar|quiero|necesito)\b/', ' ', $query);

        return collect(preg_split('/\s+/', trim((string) $query)) ?: [])
            ->map(fn ($term) => trim((string) $term))
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    protected function expandSmartSearchTerms(array $keywords, string $intent, ?string $recipient): array
    {
        $terms = collect($keywords);

        if ($intent === 'gift') {
            $terms = $terms->merge(['regalo', 'detalle', 'presente', 'promocion', 'oferta']);
        }

        $recipientTerms = [
            'mama' => ['mama', 'madre', 'mujer', 'hogar', 'cuidado', 'belleza'],
            'papa' => ['papa', 'padre', 'hombre', 'herramienta', 'tecnologia'],
            'mujer' => ['mujer', 'belleza', 'cuidado', 'hogar'],
            'hombre' => ['hombre', 'tecnologia', 'herramienta', 'gaming'],
            'nino' => ['nino', 'infantil', 'kids', 'juguete'],
            'oficina' => ['oficina', 'trabajo', 'productividad', 'escritorio'],
        ];

        if ($recipient && isset($recipientTerms[$recipient])) {
            $terms = $terms->merge($recipientTerms[$recipient]);
        }

        return $terms
            ->map(fn ($term) => $this->normalizeSmartSearchText((string) $term))
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    protected function applySmartSearchTerms($query, $terms): void
    {
        foreach ($terms as $term) {
            $query->orWhere('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('keyword', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%"))
                ->orWhereHas('family', fn ($familyQuery) => $familyQuery->where('name', 'like', "%{$term}%"));
        }
    }

    protected function scoreSmartSearchProduct(Product $product, $terms, array $parsed): array
    {
        $score = 0;
        $reasons = [];
        $haystack = [
            'name' => $this->normalizeSmartSearchText((string) $product->name),
            'keyword' => $this->normalizeSmartSearchText((string) $product->keyword),
            'short_description' => $this->normalizeSmartSearchText((string) $product->short_description),
            'description' => $this->normalizeSmartSearchText((string) $product->description),
            'brand' => $this->normalizeSmartSearchText((string) $product->brand),
            'category' => $this->normalizeSmartSearchText((string) $product->category?->name),
            'family' => $this->normalizeSmartSearchText((string) $product->family?->name),
            'sku' => $this->normalizeSmartSearchText((string) $product->sku),
        ];

        foreach ($terms as $term) {
            $matched = false;

            foreach ([
                'name' => 24,
                'keyword' => 22,
                'category' => 16,
                'family' => 16,
                'short_description' => 12,
                'brand' => 10,
                'description' => 8,
                'sku' => 6,
            ] as $field => $weight) {
                if ($term !== '' && str_contains($haystack[$field], $term)) {
                    $score += $weight;
                    $matched = true;
                    $reasons[] = match ($field) {
                        'name' => "Coincide con el nombre: {$term}",
                        'keyword' => "Coincide con palabras clave: {$term}",
                        'category' => "Coincide con categoría: {$term}",
                        'family' => "Coincide con familia: {$term}",
                        'short_description' => "Coincide con descripción corta: {$term}",
                        'brand' => "Coincide con marca: {$term}",
                        'sku' => "Coincide con SKU: {$term}",
                        default => "Coincide con descripción: {$term}",
                    };
                }
            }

            if (! $matched && $parsed['max_price'] !== null) {
                $score += 1;
            }
        }

        $price = (float) $product->default_price;

        if ($parsed['max_price'] !== null && $price <= (float) $parsed['max_price']) {
            $score += 20;
            $reasons[] = 'Precio menor o igual a $' . number_format((float) $parsed['max_price'], 2);
        }

        if ($parsed['min_price'] !== null && $price >= (float) $parsed['min_price']) {
            $score += 10;
            $reasons[] = 'Precio mayor o igual a $' . number_format((float) $parsed['min_price'], 2);
        }

        if ($product->stock === null || (float) $product->stock > 0) {
            $score += 6;
            $reasons[] = 'Disponible para compra';
        }

        if ($parsed['intent'] === 'gift' && collect($terms)->intersect(['regalo', 'detalle', 'presente'])->isNotEmpty()) {
            $score += 6;
            $reasons[] = 'Relacionado con búsqueda de regalo';
        }

        return [
            'score' => min(100, $score),
            'reasons' => collect($reasons)->unique()->take(5)->values()->all(),
        ];
    }

    protected function normalizeSmartSearchText(string $text): string
    {
        $text = Str::ascii(mb_strtolower($text));
        $text = preg_replace('/[^a-z0-9.\s]/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    protected function formatProduct(Product $product): array
    {
        $price = (float) $product->default_price;

        $activePromotions = $product->promotions
            ->map(fn (Promotion $promotion) => $this->formatProductPromotion($promotion, $price))
            ->values();
        $priceScales = $activePromotions
            ->where('type', PromotionType::PRICE_SCALE_PERCENTAGE->value)
            ->flatMap(fn ($promotion) => data_get($promotion, 'config.scales', []))
            ->filter(fn ($scale) => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true)
            ->sortBy('from_quantity')
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
            'stock' => $product->stock !== null ? (float) $product->stock : null,
            'stock_status' => $this->stockStatus($product),
            'stock_message' => $this->stockMessage($product),
            'price_info' => [
                'precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'requested_precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'is_default_price_list' => true,
                'source' => 'products.default_price',
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
            'price_scales' => $priceScales,
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

    protected function stockStatus(Product $product): string
    {
        if ($product->stock === null) {
            return 'untracked';
        }

        if ((float) $product->stock <= 0) {
            return 'out_of_stock';
        }

        if ((float) $product->stock < 5) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    protected function stockMessage(Product $product): ?string
    {
        if ($product->stock !== null && (float) $product->stock > 0 && (float) $product->stock < 5) {
            return 'Hay pocas piezas disponibles.';
        }

        if ($product->stock !== null && (float) $product->stock <= 0) {
            return 'Producto sin inventario disponible.';
        }

        return null;
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
        $usesRealVariants = $product->relationLoaded('activeVariants') && $product->activeVariants->isNotEmpty();

        return $product->activeVariantAttributes
            ->map(function ($attribute) use ($product, $usesRealVariants) {
                $values = $attribute->activeValues
                    ->map(function ($value) use ($product, $usesRealVariants) {
                        $variantIds = $this->variantIdsForAttributeValue($product, (int) $value->id);
                        $isAvailable = $usesRealVariants ? $variantIds->isNotEmpty() : true;

                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'slug' => $value->slug,
                            'metadata' => $value->metadata,
                            'is_available' => $isAvailable,
                            'variant_ids' => $variantIds,
                        ];
                    })
                    ->filter(fn ($value) => $value['is_available'])
                    ->values();

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'values' => $values,
                ];
            })
            ->filter(fn ($attribute) => $attribute['values']->isNotEmpty())
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
        $usesRealVariants = $product->relationLoaded('activeVariants') && $product->activeVariants->isNotEmpty();

        return $product->activeVariantAttributes
            ->flatMap(function ($attribute) use ($product, $usesRealVariants) {
                return $attribute->activeValues
                    ->map(function ($value) use ($attribute, $product, $usesRealVariants) {
                        $variantIds = $this->variantIdsForAttributeValue($product, (int) $value->id);
                        $isAvailable = $usesRealVariants ? $variantIds->isNotEmpty() : true;

                        return [
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
                            'is_available' => $isAvailable,
                            'metadata' => $value->metadata,
                            'variant_ids' => $variantIds,
                        ];
                    })
                    ->filter(fn ($value) => $value['is_available']);
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
            PromotionType::PRICE_SCALE_PERCENTAGE => $this->priceScalePromotionMessage($promotion),
        };
    }

    protected function priceScalePromotionMessage(Promotion $promotion): string
    {
        $firstScale = collect(data_get($promotion->config, 'scales', []))
            ->filter(fn ($scale) => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true)
            ->sortBy('from_quantity')
            ->first();

        if (!$firstScale) {
            return 'Descuento por escala de mayoreo';
        }

        $fromQuantity = (int) ($firstScale['from_quantity'] ?? 0);
        $toQuantity = isset($firstScale['to_quantity']) && $firstScale['to_quantity'] !== ''
            ? (int) $firstScale['to_quantity']
            : null;
        $discountPercentage = (float) ($firstScale['discount_percentage'] ?? 0);
        $range = $toQuantity ? "{$fromQuantity} a {$toQuantity}" : "{$fromQuantity}+";

        return "{$discountPercentage}% de descuento de {$range} pieza(s)";
    }

}
