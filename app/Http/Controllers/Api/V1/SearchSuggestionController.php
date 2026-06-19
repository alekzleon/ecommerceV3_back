<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 4) {
            return response()->json([
                'query' => $query,
                'did_you_mean' => null,
                'suggestions' => [
                    'products' => [],
                    'brands' => [],
                    'categories' => [],
                    'families' => [],
                ],
            ]);
        }

        $normalizedQuery = $this->normalize($query);

        $products = Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug',
            ])
            ->select([
                'id',
                'name',
                'slug',
                'brand',
                'sku',
                'keyword',
                'image_path',
                'default_price',
                'category_id',
                'family_id',
            ])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('brand', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('keyword', 'like', "%{$query}%");
            })
            ->limit(8)
            ->get();

        $brands = Product::query()
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->where('brand', 'like', "%{$query}%")
            ->distinct()
            ->limit(5)
            ->pluck('brand')
            ->map(fn ($brand) => ['name' => $brand])
            ->values();

        $categories = Product::query()
            ->with('category:id,grupo_linea_id,name,slug')
            ->where('is_active', true)
            ->whereHas('category', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->map(fn ($category) => [
                'id' => $category->grupo_linea_id ?? $category->id,
                'local_id' => $category->id,
                'grupo_linea_id' => $category->grupo_linea_id,
                'name' => $category->name,
                'slug' => $category->slug ?? null,
            ])
            ->values();

        $families = Product::query()
            ->with('family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug')
            ->where('is_active', true)
            ->whereHas('family', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->pluck('family')
            ->filter()
            ->unique('id')
            ->map(fn ($family) => [
                'id' => $family->linea_articulo_id ?? $family->id,
                'local_id' => $family->id,
                'linea_articulo_id' => $family->linea_articulo_id,
                'category_id' => $family->grupo_linea_id ?? $family->category_id,
                'local_category_id' => $family->category_id,
                'grupo_linea_id' => $family->grupo_linea_id,
                'name' => $family->name,
                'slug' => $family->slug ?? null,
            ])
            ->values();

        $didYouMean = $this->buildDidYouMean($normalizedQuery);

        return response()->json([
            'query' => $query,
            'did_you_mean' => $didYouMean,
            'suggestions' => [
                'products' => $products->map(function ($product) {
                    $price = (float) $product->default_price;

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'brand' => $product->brand,
                        'sku' => $product->sku,
                        'image_url' => $product->image_path ? asset('storage/' . $product->image_path) : null,
                        'price' => $price,
                        'base_default_price' => (float) $product->default_price,
                        'price_info' => [
                            'precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                            'requested_precio_empresa_id' => ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                            'is_default_price_list' => true,
                            'source' => 'products.default_price',
                        ],
                        'category' => $product->category?->name,
                        'family' => $product->family?->name,
                    ];
                })->values(),

                'brands' => $brands,
                'categories' => $categories,
                'families' => $families,
            ],
        ]);
    }

    protected function buildDidYouMean(string $normalizedQuery): ?string
    {
        $words = explode(' ', $normalizedQuery);

        $dictionary = Product::query()
            ->where('is_active', true)
            ->select(['name', 'brand', 'keyword'])
            ->limit(500)
            ->get();

        $terms = collect();

        foreach ($dictionary as $item) {
            // nombre
            if (!empty($item->name)) {
                foreach (explode(' ', $this->normalize($item->name)) as $word) {
                    if (strlen($word) > 2) {
                        $terms->push($word);
                    }
                }
            }

            // marca
            if (!empty($item->brand)) {
                $terms->push($this->normalize($item->brand));
            }

            // keywords
            if (!empty($item->keyword)) {
                foreach (preg_split('/[,|;]/', $item->keyword) as $keyword) {
                    $keyword = $this->normalize(trim($keyword));
                    if ($keyword !== '') {
                        foreach (explode(' ', $keyword) as $word) {
                            if (strlen($word) > 2) {
                                $terms->push($word);
                            }
                        }
                    }
                }
            }
        }

        $terms = $terms->unique()->values();

        $correctedWords = [];
        $hasChanges = false;

        foreach ($words as $word) {
            $bestMatch = $word;
            $bestScore = 0;

            foreach ($terms as $candidate) {
                similar_text($word, $candidate, $percent);

                $distance = levenshtein($word, $candidate);
                $maxLen = max(strlen($word), strlen($candidate));

                $levScore = $maxLen > 0 ? (1 - ($distance / $maxLen)) * 100 : 0;

                $score = ($percent * 0.6) + ($levScore * 0.4);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $candidate;
                }
            }

            // si el score es suficientemente bueno, corregimos
            if ($bestScore >= 75 && $bestMatch !== $word) {
                $correctedWords[] = $bestMatch;
                $hasChanges = true;
            } else {
                $correctedWords[] = $word;
            }
        }

        $result = implode(' ', $correctedWords);

        return $hasChanges ? $result : null;
    }

    protected function normalize(string $value): string
    {
        $value = Str::lower($value);
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
