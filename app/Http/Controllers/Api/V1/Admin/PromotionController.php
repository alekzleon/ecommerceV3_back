<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PromotionType;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Http\Resources\Promotion\AdminPromotionResource;
use App\Models\GiftItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 20));

        $promotions = Promotion::query()
            ->with([
                'products:id,name,sku',
                'giftItems:id,name,code,estimated_value,unit_label,is_active',
                'users:id,name,email,username,role_id',
            ])
            ->withCount(['products', 'giftItems', 'users'])
            ->latest()
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => AdminPromotionResource::collection($promotions->getCollection()),
            'meta' => [
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'per_page' => $promotions->perPage(),
                'total' => $promotions->total(),
                'from' => $promotions->firstItem(),
                'to' => $promotions->lastItem(),
            ],
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $promotionTypes = collect(PromotionType::cases())
            ->map(fn (PromotionType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values();

        $giftItems = GiftItem::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'code', 'estimated_value', 'unit_label', 'is_active'])
            ->map(fn (GiftItem $giftItem) => [
                'id' => $giftItem->id,
                'name' => $giftItem->name,
                'code' => $giftItem->code,
                'estimated_value' => $giftItem->estimated_value !== null ? (float) $giftItem->estimated_value : null,
                'unit_label' => $giftItem->unit_label,
                'is_active' => (bool) $giftItem->is_active,
            ])
            ->values();

        $brandOptions = Product::query()
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values();

        $clients = User::query()
            ->where('role_id', User::ROLE_CLIENTE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'promotion_types' => $promotionTypes,
                'gift_items' => $giftItems,
                'brand_options' => $brandOptions,
                'clients' => $clients,
                'new_admin_types' => [
                    PromotionType::BUY_SKU_GET_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                    PromotionType::PRICE_SCALE_PERCENTAGE->value,
                ],
            ],
        ]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $productIds = $validated['product_ids'] ?? [];
        $giftItemIds = $validated['gift_item_ids'] ?? [];
        $userIds = $validated['user_ids'] ?? [];

        unset($validated['product_ids'], $validated['gift_item_ids'], $validated['user_ids'], $validated['is_combinable']);

        $promotion = Promotion::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        if (!empty($productIds)) {
            $promotion->products()->sync($productIds);
        }

        if (!empty($giftItemIds)) {
            $promotion->giftItems()->sync($giftItemIds);
        }

        if (!$promotion->is_general) {
            $promotion->users()->sync($userIds);
        }

        if (!empty($productIds) || !empty($giftItemIds) || !empty($userIds)) {
            app(ActivityLogService::class)->record([
                'module' => 'promotions',
                'action' => 'promotion_relations_synced',
                'summary' => 'Productos/regalos/clientes de promoción asignados',
                'entity_type' => 'promotion',
                'entity_id' => $promotion->id,
                'new_values' => [
                    'product_ids' => array_values($productIds),
                    'gift_item_ids' => array_values($giftItemIds),
                    'user_ids' => array_values($userIds),
                ],
                'metadata' => [
                    'name' => $promotion->name,
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Promoción creada correctamente.',
            'data' => new AdminPromotionResource($promotion->fresh()->load(['products', 'giftItems', 'users'])),
        ], 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new AdminPromotionResource($promotion->load(['products', 'giftItems', 'users'])),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validated();
        $productIds = $validated['product_ids'] ?? [];
        $giftItemIds = $validated['gift_item_ids'] ?? [];
        $userIds = $validated['user_ids'] ?? [];
        $oldProductIds = $promotion->products()->pluck('products.id')->all();
        $oldGiftItemIds = $promotion->giftItems()->pluck('gift_items.id')->all();
        $oldUserIds = $promotion->users()->pluck('users.id')->all();

        unset($validated['product_ids'], $validated['gift_item_ids'], $validated['user_ids'], $validated['is_combinable']);

        $validated['config'] = array_merge($promotion->config ?? [], $validated['config'] ?? []);

        $promotion->update($validated);

        if ($request->has('product_ids')) {
            $promotion->products()->sync($productIds);
        }

        if ($request->has('gift_item_ids')) {
            $promotion->giftItems()->sync($giftItemIds);
        }

        if ($promotion->is_general) {
            $promotion->users()->sync([]);
        } elseif ($request->has('user_ids')) {
            $promotion->users()->sync($userIds);
        }

        if ($request->has('product_ids') || $request->has('gift_item_ids') || $request->has('user_ids') || $request->has('is_general')) {
            app(ActivityLogService::class)->record([
                'module' => 'promotions',
                'action' => 'promotion_relations_synced',
                'summary' => 'Productos/regalos/clientes de promoción actualizados',
                'entity_type' => 'promotion',
                'entity_id' => $promotion->id,
                'old_values' => [
                    'product_ids' => array_values($oldProductIds),
                    'gift_item_ids' => array_values($oldGiftItemIds),
                    'user_ids' => array_values($oldUserIds),
                ],
                'new_values' => [
                    'product_ids' => $request->has('product_ids') ? array_values($productIds) : array_values($oldProductIds),
                    'gift_item_ids' => $request->has('gift_item_ids') ? array_values($giftItemIds) : array_values($oldGiftItemIds),
                    'user_ids' => $promotion->is_general
                        ? []
                        : ($request->has('user_ids') ? array_values($userIds) : array_values($oldUserIds)),
                ],
                'metadata' => [
                    'name' => $promotion->name,
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Promoción actualizada correctamente.',
            'data' => new AdminPromotionResource($promotion->fresh()->load(['products', 'giftItems', 'users'])),
        ]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Promoción eliminada correctamente.',
        ]);
    }

    public function toggle(Promotion $promotion): JsonResponse
    {
        $promotion->update([
            'is_active' => ! $promotion->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado actualizado correctamente.',
            'is_active' => $promotion->is_active,
        ]);
    }

    public function syncProducts(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $oldProductIds = $promotion->products()->pluck('products.id')->all();

        $promotion->products()->sync($data['product_ids']);

        app(ActivityLogService::class)->record([
            'module' => 'promotions',
            'action' => 'promotion_products_synced',
            'summary' => 'Productos de promoción sincronizados',
            'entity_type' => 'promotion',
            'entity_id' => $promotion->id,
            'old_values' => [
                'product_ids' => array_values($oldProductIds),
            ],
            'new_values' => [
                'product_ids' => array_values($data['product_ids']),
            ],
            'metadata' => [
                'name' => $promotion->name,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Productos de la promoción sincronizados correctamente.',
            'data' => new AdminPromotionResource($promotion->fresh()->load(['products', 'giftItems', 'users'])),
        ]);
    }
}
