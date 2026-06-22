<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Admin\AdminCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Category::query()
            ->withCount(['products', 'families'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            });

        match ($request->string('sort_by', 'name_asc')->toString()) {
            'name_desc' => $query->orderByDesc('name'),
            'latest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };

        $categories = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Categorías obtenidas correctamente.',
            'data' => AdminCategoryResource::collection($categories->getCollection()),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = ($data['code'] ?? null) ?: $this->uniqueCode($data['name']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('categories', 'public');
        }

        unset($data['image']);

        $category = Category::create($data)->loadCount(['products', 'families']);

        return response()->json([
            'ok' => true,
            'message' => 'Categoría creada correctamente.',
            'data' => new AdminCategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['families' => fn ($query) => $query->orderBy('name')])
            ->loadCount(['products', 'families']);

        return response()->json([
            'ok' => true,
            'message' => 'Categoría obtenida correctamente.',
            'data' => new AdminCategoryResource($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if (($data['code'] ?? null) === null && array_key_exists('code', $data)) {
            unset($data['code']);
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($category);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($category);
            $data['image_path'] = $request->file('image')->store('categories', 'public');
        }

        unset($data['image'], $data['remove_image']);

        $category->update($data);
        $category->loadCount(['products', 'families']);

        return response()->json([
            'ok' => true,
            'message' => 'Categoría actualizada correctamente.',
            'data' => new AdminCategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->update(['is_active' => false]);
        $category->loadCount(['products', 'families']);

        return response()->json([
            'ok' => true,
            'message' => 'Categoría desactivada correctamente.',
            'data' => new AdminCategoryResource($category),
        ]);
    }

    public function updateStatus(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update(['is_active' => (bool) $validated['is_active']]);
        $category->loadCount(['products', 'families']);

        return response()->json([
            'ok' => true,
            'message' => $category->is_active
                ? 'Categoría activada correctamente.'
                : 'Categoría desactivada correctamente.',
            'data' => new AdminCategoryResource($category),
        ]);
    }

    protected function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)) ?: 'CAT', 0, 8));
        $code = $base;
        $counter = 1;

        while (Category::query()->where('code', $code)->exists()) {
            $code = Str::substr($base, 0, max(1, 10 - strlen((string) $counter))) . $counter;
            $counter++;
        }

        return $code;
    }

    protected function deleteImage(Category $category): void
    {
        if ($category->image_path && Storage::disk('public')->exists($category->image_path)) {
            Storage::disk('public')->delete($category->image_path);
        }
    }
}
