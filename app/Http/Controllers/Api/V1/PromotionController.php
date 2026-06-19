<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {
    }

    /**
     * =========================================================================
     * Promociones públicas
     * =========================================================================
     * Esta ruta la usaré para mostrar promociones activas en home, banners,
     * cards, landing de ofertas o cualquier bloque visible al cliente.
     *
     * Nota:
     * - Aquí solo quiero promociones activas y públicas.
     * - Si más adelante manejo vigencias, prioridad, límites o banners,
     *   esta consulta la puedo ir afinando.
     */
    public function index(): JsonResponse
    {
        $promotions = $this->publicPromotionsQuery()
            ->orderByDesc('priority')
            ->get()
            ->map(fn (Promotion $promotion) => $this->formatPromotion($promotion));

        return response()->json([
            'ok' => true,
            'data' => $promotions,
        ]);
    }

    public function random(): JsonResponse
    {
        $promotion = $this->publicPromotionsQuery()
            ->inRandomOrder()
            ->first();

        return response()->json([
            'ok' => true,
            'message' => $promotion
                ? 'Promoción aleatoria obtenida correctamente.'
                : 'No hay promociones disponibles.',
            'data' => $promotion ? $this->formatPromotion($promotion) : null,
        ]);
    }

    public function randomSix(): JsonResponse
    {
        $promotions = $this->publicPromotionsQuery()
            ->inRandomOrder()
            ->limit(6)
            ->get()
            ->map(fn (Promotion $promotion) => $this->formatPromotion($promotion));

        return response()->json([
            'ok' => true,
            'message' => 'Promociones aleatorias obtenidas correctamente.',
            'data' => $promotions,
            'meta' => [
                'limit' => 6,
                'total' => $promotions->count(),
            ],
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 24));

        $promotions = $this->publicPromotionsQuery()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        $promotions->getCollection()->transform(fn (Promotion $promotion) => $this->formatPromotion($promotion));

        return response()->json([
            'ok' => true,
            'message' => 'Ofertas obtenidas correctamente.',
            'data' => $promotions->items(),
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

    /**
     * =========================================================================
     * Promociones del carrito actual
     * =========================================================================
     * Esta ruta me sirve para:
     * - saber qué promociones están aplicadas
     * - devolver ahorro actual
     * - mostrar resumen de promos en drawer o checkout
     *
     * Importante:
     * - No invento otra estructura fuera del carrito.
     * - Las promociones aplicadas las reconstruyo desde cart_items.
     */
    public function cartPromotions(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = $this->cartService->getOrCreateActiveCart($user);
        $cart = $this->cartService->recalculateCart($cart);

        $cart->load([
            'items.product.category',
            'items.product.family',
        ]);

        $promotionsApplied = $this->buildAppliedPromotionsFromCart($cart);

        return response()->json([
            'ok' => true,
            'data' => [
                'cart_id' => $cart->id,
                'items_count' => (float) $cart->items_count,
                'totals' => [
                    'subtotal' => (float) $cart->subtotal_snapshot,
                    'discount' => (float) $cart->discount_snapshot,
                    'tax' => (float) $cart->tax_snapshot,
                    'total' => (float) $cart->total_snapshot,
                ],
                'promotions_applied' => $promotionsApplied,
                'items' => $cart->items->map(function ($item) {
                    $giftUnits = (int) data_get($item->promotion_snapshot, 'gift_units', 0);
                    $giftUnitAccountingPrice = data_get($item->promotion_snapshot, 'gift_unit_accounting_price');
                    $giftLineTotal = data_get($item->promotion_snapshot, 'gift_line_total');

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->name_snapshot,
                        'sku' => $item->sku_snapshot,
                        'quantity' => (float) $item->quantity,
                        'base_unit_price' => (float) $item->base_unit_price_snapshot,
                        'final_unit_price' => (float) $item->final_unit_price_snapshot,
                        'line_subtotal' => (float) $item->line_subtotal_snapshot,
                        'line_discount' => (float) $item->line_discount_snapshot,
                        'gift_units' => $giftUnits,
                        'gift_unit_accounting_price' => $giftUnitAccountingPrice !== null
                            ? (float) $giftUnitAccountingPrice
                            : null,
                        'gift_line_total' => $giftLineTotal !== null
                            ? (float) $giftLineTotal
                            : null,
                        'promotion' => $item->promotion_id ? [
                            'id' => $item->promotion_id,
                            'type' => $item->promotion_type,
                            'name' => $item->promotion_name_snapshot,
                            'snapshot' => $item->promotion_snapshot,
                        ] : null,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * =========================================================================
     * Recalcular promociones manualmente
     * =========================================================================
     * Esta ruta me sirve para forzar actualización del carrito desde frontend
     * si alguna vez lo necesito en drawer, checkout preview o debug.
     *
     * Ojo:
     * - Mi flujo principal sigue siendo recalcular automáticamente desde
     *   CartService cuando se agrega, actualiza o elimina un item.
     */
    public function recalculate(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = $this->cartService->getOrCreateActiveCart($user);
        $cart = $this->cartService->recalculateCart($cart);

        $cart->load([
            'items.product.category',
            'items.product.family',
        ]);

        $promotionsApplied = $this->buildAppliedPromotionsFromCart($cart);

        return response()->json([
            'ok' => true,
            'message' => 'Promociones recalculadas correctamente.',
            'data' => [
                'cart_id' => $cart->id,
                'items_count' => (float) $cart->items_count,
                'totals' => [
                    'subtotal' => (float) $cart->subtotal_snapshot,
                    'discount' => (float) $cart->discount_snapshot,
                    'tax' => (float) $cart->tax_snapshot,
                    'total' => (float) $cart->total_snapshot,
                ],
                'promotions_applied' => $promotionsApplied,
            ],
        ]);
    }

    /**
     * =========================================================================
     * Reconstruir promociones aplicadas desde los items del carrito
     * =========================================================================
     * No estoy guardando un promotions_applied en carts, entonces lo armo
     * leyendo los snapshots de cada item.
     */
    protected function buildAppliedPromotionsFromCart($cart): array
    {
        $grouped = [];

        foreach ($cart->items as $item) {
            if (!$item->promotion_id) {
                continue;
            }

            $promotionId = (int) $item->promotion_id;

            if (!isset($grouped[$promotionId])) {
                $grouped[$promotionId] = [
                    'id' => $promotionId,
                    'type' => $item->promotion_type,
                    'name' => $item->promotion_name_snapshot,
                    'total_discount' => 0,
                    'items_count' => 0,
                    'items' => [],
                    'snapshot' => $item->promotion_snapshot,
                ];
            }

            $grouped[$promotionId]['total_discount'] += (float) $item->line_discount_snapshot;
            $grouped[$promotionId]['items_count'] += 1;

            $giftUnits = (int) data_get($item->promotion_snapshot, 'gift_units', 0);
            $giftLineTotal = (float) data_get($item->promotion_snapshot, 'gift_line_total', 0);

            $grouped[$promotionId]['gift_units'] = ($grouped[$promotionId]['gift_units'] ?? 0) + $giftUnits;
            $grouped[$promotionId]['gift_line_total'] = ($grouped[$promotionId]['gift_line_total'] ?? 0) + $giftLineTotal;
            $grouped[$promotionId]['items'][] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name_snapshot,
                'sku' => $item->sku_snapshot,
                'quantity' => (float) $item->quantity,
                'line_discount' => (float) $item->line_discount_snapshot,
                'gift_units' => $giftUnits,
                'gift_line_total' => round($giftLineTotal, 2),
            ];
        }

        return array_values(array_map(function ($promotion) {
            $promotion['total_discount'] = round((float) $promotion['total_discount'], 2);
            $promotion['gift_units'] = (int) ($promotion['gift_units'] ?? 0);
            $promotion['gift_line_total'] = round((float) ($promotion['gift_line_total'] ?? 0), 2);
            return $promotion;
        }, $grouped));
    }

    protected function publicPromotionsQuery()
    {
        return Promotion::query()
            ->with([
                'products:id,name,slug,sku',
            ])
            ->withCount('products')
            ->active()
            ->currentWindow()
            ->where('is_general', true);
    }

    protected function formatPromotion(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'slug' => $promotion->slug,
            'type' => $promotion->type->value,
            'label' => $promotion->type->label(),
            'description' => $promotion->description,
            'priority' => $promotion->priority,
            'image_path' => $promotion->image_path,
            'image_url' => $promotion->image_path
                ? asset('storage/' . ltrim($promotion->image_path, '/'))
                : null,
            'is_active' => (bool) $promotion->is_active,
            'requires_login' => (bool) $promotion->requires_login,
            'is_general' => (bool) $promotion->is_general,
            'starts_at' => $promotion->starts_at?->toDateTimeString(),
            'ends_at' => $promotion->ends_at?->toDateTimeString(),
            'config' => $promotion->config,
            'products_count' => (int) ($promotion->products_count ?? 0),
            'product_id' => $promotion->products->first()?->id,
            'product_ids' => $promotion->products->pluck('id')->values(),
            'products' => $promotion->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
            ])->values(),
            'created_at' => $promotion->created_at?->toDateTimeString(),
            'updated_at' => $promotion->updated_at?->toDateTimeString(),
        ];
    }
}
