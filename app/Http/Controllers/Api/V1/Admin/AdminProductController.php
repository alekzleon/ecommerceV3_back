<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 300) : 20;

        $search = trim((string) $request->get('search', ''));
        $categoryId = $request->get('category_id');
        $familyId = $request->get('family_id');
        $isActive = $request->get('is_active');
        $sortBy = $request->get('sort_by', 'latest');
        $withoutPagination = filter_var($request->get('without_pagination', false), FILTER_VALIDATE_BOOLEAN);

        $query = Product::query()
            ->with([
                'category:id,name,slug',
                'family:id,name,slug',
            ]);

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (!empty($familyId)) {
            $query->where('family_id', $familyId);
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        switch ($sortBy) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;

            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;

            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;

            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;

            case 'oldest':
                $query->orderBy('id', 'asc');
                break;

            case 'latest':
            default:
                $query->orderByDesc('id');
                break;
        }

        if ($withoutPagination) {
            return response()->json([
                'ok' => true,
                'data' => $query->get(),
            ]);
        }

        return response()->json(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $product = Product::create($validated);

        $product->load([
            'category:id,name,slug',
            'family:id,name,slug',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Producto creado correctamente.',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'category:id,name,slug',
            'family:id,name,slug',
        ])->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate($this->rules($product->id));

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $product->update($validated);

        $product->load([
            'category:id,name,slug',
            'family:id,name,slug',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Producto actualizado correctamente.',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    /**
     * Validation rules.
     */
    protected function rules(?int $productId = null): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'family_id' => ['nullable', 'integer', 'exists:families,id'],

            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('products', 'name')->ignore($productId),
            ],
            'subtitle' => ['nullable', 'string', 'max:190'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['nullable', 'string'],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],

            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            'image' => ['nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['nullable'],

            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_digital' => ['nullable', 'boolean'],

            'published_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}