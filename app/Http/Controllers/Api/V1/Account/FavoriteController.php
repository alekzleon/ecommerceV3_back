<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ToggleFavoriteRequest;
use App\Http\Resources\Account\FavoriteProductResource;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Services\ProductPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(
        protected ProductPriceService $productPriceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 24));

        $favorites = $request->user()
            ->favoriteProducts()
            ->with([
                'category:id,name,slug',
                'family:id,category_id,name,slug',
            ])
            ->where('is_active', true)
            ->orderByDesc('product_favorites.created_at')
            ->paginate($perPage)
            ->appends($request->query());

        $this->productPriceService->decorateProducts($favorites->getCollection(), $request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Favoritos obtenidos correctamente.',
            'data' => FavoriteProductResource::collection($favorites->items()),
            'meta' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
                'from' => $favorites->firstItem(),
                'to' => $favorites->lastItem(),
            ],
        ]);
    }

    public function toggle(ToggleFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();
        $productId = (int) $request->validated('product_id');

        $favorite = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        $favoritedAt = null;

        if ($favorite) {
            $favorite->delete();
            $isFavorite = false;
            $action = 'removed';
            $message = 'Producto eliminado de favoritos.';
        } else {
            $favorite = ProductFavorite::query()->create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);

            $favoritedAt = $favorite->created_at?->toDateTimeString();
            $isFavorite = true;
            $action = 'added';
            $message = 'Producto agregado a favoritos.';
        }

        $product = Product::query()
            ->with([
                'category:id,name,slug',
                'family:id,category_id,name,slug',
            ])
            ->find($productId);

        if ($product) {
            $product->setAttribute('is_favorite_for_current_user', $isFavorite);
            $product->setAttribute('favorite_created_at', $favoritedAt);
            $this->productPriceService->decorateProducts(collect([$product]), $user);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => [
                'product_id' => $productId,
                'is_favorite' => $isFavorite,
                'action' => $action,
                'product' => $product ? new FavoriteProductResource($product) : null,
            ],
        ]);
    }
}
