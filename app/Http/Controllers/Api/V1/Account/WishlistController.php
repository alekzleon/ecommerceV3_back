<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddWishlistProductRequest;
use App\Http\Requests\Account\StoreWishlistRequest;
use App\Http\Requests\Account\UpdateWishlistRequest;
use App\Http\Resources\Account\WishlistProductResource;
use App\Http\Resources\Account\WishlistResource;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlists = $request->user()
            ->wishlists()
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Listas obtenidas correctamente.',
            'data' => WishlistResource::collection($wishlists),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
        ]);

        $productId = $validated['product_id'] ?? null;

        $wishlists = $request->user()
            ->wishlists()
            ->withCount('products')
            ->when($productId, function ($query) use ($productId) {
                $query->withExists([
                    'products as has_product' => fn ($productQuery) => $productQuery->where('products.id', $productId),
                ]);
            })
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Opciones de listas obtenidas correctamente.',
            'data' => [
                'product_id' => $productId,
                'has_lists' => $wishlists->isNotEmpty(),
                'can_create_list' => true,
                'lists' => WishlistResource::collection($wishlists),
            ],
        ]);
    }

    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $wishlist = $user->wishlists()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'position' => $data['position'] ?? $this->nextPosition($user),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Lista creada correctamente.',
            'data' => new WishlistResource($wishlist->loadCount('products')),
        ], 201);
    }

    public function show(Request $request, Wishlist $wishlist): JsonResponse
    {
        $this->ensureOwnership($wishlist, $request->user());

        $perPage = max(1, min(100, (int) $request->integer('per_page', 24)));

        $products = $wishlist->products()
            ->with([
                'category:id,name,slug',
                'family:id,category_id,name,slug',
            ])
            ->where('is_active', true)
            ->orderByDesc('wishlist_products.created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Detalle de lista obtenido correctamente.',
            'data' => [
                'wishlist' => new WishlistResource($wishlist->loadCount('products')),
                'products' => WishlistProductResource::collection($products->items()),
            ],
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

    public function update(UpdateWishlistRequest $request, Wishlist $wishlist): JsonResponse
    {
        $this->ensureOwnership($wishlist, $request->user());

        $wishlist->update($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Lista actualizada correctamente.',
            'data' => new WishlistResource($wishlist->fresh()->loadCount('products')),
        ]);
    }

    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        $this->ensureOwnership($wishlist, $request->user());

        $wishlist->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Lista eliminada correctamente.',
            'data' => [
                'id' => $wishlist->id,
                'deleted' => true,
            ],
        ]);
    }

    public function addProduct(AddWishlistProductRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $payload = DB::transaction(function () use ($user, $data) {
            $wishlist = $this->resolveWishlist($user, $data);
            $productId = (int) $data['product_id'];
            $alreadyExists = $wishlist->products()->where('products.id', $productId)->exists();

            if (! $alreadyExists) {
                $wishlist->products()->attach($productId);
            }

            $product = Product::query()
                ->with([
                    'category:id,name,slug',
                    'family:id,category_id,name,slug',
                ])
                ->find($productId);

            return [
                'wishlist' => $wishlist->fresh()->loadCount('products'),
                'product' => $product,
                'already_exists' => $alreadyExists,
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => $payload['already_exists']
                ? 'El producto ya estaba en la lista.'
                : 'Producto agregado a la lista correctamente.',
            'data' => [
                'wishlist' => new WishlistResource($payload['wishlist']),
                'product' => $payload['product'] ? new WishlistProductResource($payload['product']) : null,
                'already_exists' => $payload['already_exists'],
                'action' => $payload['already_exists'] ? 'already_exists' : 'added',
            ],
        ], $payload['already_exists'] ? 200 : 201);
    }

    public function removeProduct(Request $request, Wishlist $wishlist, Product $product): JsonResponse
    {
        $this->ensureOwnership($wishlist, $request->user());

        $removed = $wishlist->products()->detach($product->id) > 0;

        return response()->json([
            'ok' => true,
            'message' => $removed
                ? 'Producto eliminado de la lista correctamente.'
                : 'El producto no estaba en la lista.',
            'data' => [
                'wishlist_id' => $wishlist->id,
                'product_id' => $product->id,
                'removed' => $removed,
            ],
        ]);
    }

    protected function resolveWishlist(User $user, array $data): Wishlist
    {
        if (! empty($data['wishlist_id'])) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->whereKey($data['wishlist_id'])
                ->first();

            abort_unless($wishlist, 403, 'No tienes acceso a esta lista.');

            return $wishlist;
        }

        $name = trim((string) $data['list_name']);

        abort_if($name === '', 422, 'El nombre de la lista es obligatorio.');

        return Wishlist::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => $name,
            ],
            [
                'position' => $this->nextPosition($user),
            ]
        );
    }

    protected function nextPosition(User $user): int
    {
        return ((int) $user->wishlists()->max('position')) + 1;
    }

    protected function ensureOwnership(Wishlist $wishlist, User $user): void
    {
        abort_unless((int) $wishlist->user_id === (int) $user->id, 403, 'No tienes acceso a esta lista.');
    }
}
