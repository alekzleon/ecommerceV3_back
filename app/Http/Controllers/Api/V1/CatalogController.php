<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function sidebar(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'grupo_linea_id', 'code', 'name', 'slug']);

        $categoryCounts = Product::query()
            ->select('category_id', DB::raw('COUNT(*) as total'))
            ->where('is_active', true)
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $familyCounts = Product::query()
            ->select('family_id', DB::raw('COUNT(*) as total'))
            ->where('is_active', true)
            ->whereNotNull('family_id')
            ->groupBy('family_id')
            ->pluck('total', 'family_id');

        $categories->load([
            'families' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name')
                    ->select(['id', 'linea_articulo_id', 'category_id', 'grupo_linea_id', 'name', 'slug']);
            }
        ]);

        $categoryFamilies = $categories->map(function ($category) use ($categoryCounts, $familyCounts) {
            $categoryId = $category->grupo_linea_id ?? $category->id;

            return [
                'id' => $categoryId,
                'local_id' => $category->id,
                'grupo_linea_id' => $category->grupo_linea_id,
                'code' => $category->code,
                'slug' => $category->slug,
                'name' => $category->name,
                'count' => (int) ($categoryCounts[$category->id] ?? 0),
                'families' => $category->families->map(function ($family) use ($familyCounts, $categoryId) {
                    return [
                        'id' => $family->linea_articulo_id ?? $family->id,
                        'local_id' => $family->id,
                        'linea_articulo_id' => $family->linea_articulo_id,
                        'category_id' => $categoryId,
                        'local_category_id' => $family->category_id,
                        'grupo_linea_id' => $family->grupo_linea_id,
                        'slug' => $family->slug,
                        'name' => $family->name,
                        'count' => (int) ($familyCounts[$family->id] ?? 0),
                    ];
                })->values(),
            ];
        })->values();

        $brandOptions = Product::query()
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand', DB::raw('COUNT(*) as total'))
            ->groupBy('brand')
            ->orderBy('brand')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->brand,
                    'count' => (int) $item->total,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'categoryFamilies' => $categoryFamilies,
                'brandOptions' => $brandOptions,
            ],
        ]);
    }
}
