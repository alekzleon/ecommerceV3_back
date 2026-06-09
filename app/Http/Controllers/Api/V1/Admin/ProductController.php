<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);

        $products = Product::query()
            ->with(['category', 'family'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', (int) $request->category_id);
            })
            ->when($request->filled('family_id'), function ($query) use ($request) {
                $query->where('family_id', (int) $request->family_id);
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->when($request->filled('processed'), function ($query) use ($request) {
                $processed = filter_var($request->input('processed'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($processed !== null) {
                    $query->where('processed', $processed);
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'message' => 'Productos obtenidos correctamente.',
            'data' => AdminProductResource::collection($products->getCollection()),
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

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image']);

        $product = Product::create($data);
        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto creado correctamente.',
            'data' => new AdminProductResource($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto obtenido correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image']);

        $product->update($data);
        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto actualizado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], [
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ]);

        $product->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => $product->is_active
                ? 'Producto activado correctamente.'
                : 'Producto desactivado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update([
            'is_active' => false,
        ]);

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto desactivado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    protected function productDetailRelations(): array
    {
        return [
            'category',
            'family',
            'galleryItems',
            'variantAttributes.values',
            'variants.attributeValues.attribute',
        ];
    }
}
