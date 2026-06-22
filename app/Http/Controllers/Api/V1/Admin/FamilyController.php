<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFamilyRequest;
use App\Http\Requests\Admin\UpdateFamilyRequest;
use App\Http\Resources\Admin\AdminFamilyResource;
use App\Models\Category;
use App\Models\Family;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Family::query()
            ->with('category')
            ->withCount('products')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', (int) $request->integer('category_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
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

        $families = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Familias obtenidas correctamente.',
            'data' => AdminFamilyResource::collection($families->getCollection()),
            'meta' => [
                'current_page' => $families->currentPage(),
                'last_page' => $families->lastPage(),
                'per_page' => $families->perPage(),
                'total' => $families->total(),
                'from' => $families->firstItem(),
                'to' => $families->lastItem(),
            ],
        ]);
    }

    public function store(StoreFamilyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Category::query()->findOrFail($data['category_id']);
        $data['grupo_linea_id'] = $category->grupo_linea_id;

        $family = Family::create($data)->load(['category'])->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Familia creada correctamente.',
            'data' => new AdminFamilyResource($family),
        ], 201);
    }

    public function show(Family $family): JsonResponse
    {
        $family->load('category')->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Familia obtenida correctamente.',
            'data' => new AdminFamilyResource($family),
        ]);
    }

    public function update(UpdateFamilyRequest $request, Family $family): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('category_id', $data)) {
            $category = Category::query()->findOrFail($data['category_id']);
            $data['grupo_linea_id'] = $category->grupo_linea_id;
        }

        $family->update($data);
        $family->load('category')->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Familia actualizada correctamente.',
            'data' => new AdminFamilyResource($family),
        ]);
    }

    public function destroy(Family $family): JsonResponse
    {
        $family->update(['is_active' => false]);
        $family->load('category')->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Familia desactivada correctamente.',
            'data' => new AdminFamilyResource($family),
        ]);
    }

    public function updateStatus(Request $request, Family $family): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $family->update(['is_active' => (bool) $validated['is_active']]);
        $family->load('category')->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => $family->is_active
                ? 'Familia activada correctamente.'
                : 'Familia desactivada correctamente.',
            'data' => new AdminFamilyResource($family),
        ]);
    }
}
