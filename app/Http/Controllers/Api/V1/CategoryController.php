<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get([
                'id',
                'grupo_linea_id',
                'code',
                'name',
                'slug',
            ])
            ->map(fn (Category $category) => [
                'id' => $category->grupo_linea_id ?? $category->id,
                'local_id' => $category->id,
                'grupo_linea_id' => $category->grupo_linea_id,
                'code' => $category->code,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }
}
