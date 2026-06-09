<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\ImportCartExcelRequest;
use App\Http\Requests\Cart\SelectPromotionGiftRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Http\Resources\Cart\CartSummaryResource;
use App\Models\CartItem;
use App\Models\Promotion;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CartExcelService;
use App\Services\Orders\OrderService;
use App\Services\ProductPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CartExcelService $cartExcelService,
        protected OrderService $orderService,
        protected ProductPriceService $productPriceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());
        if ($recoverableOrder) {
            return response()->json([
                'success' => true,
                'message' => 'Hay un carrito pendiente de recuperar.',
                'data' => [
                    'cart' => null,
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ]);
        }

        // $cart = $this->cartService->getOrCreateActiveCart($request->user())->load('items');
        $cart = $this->cartService->recalculateCart(
            $this->cartService->getOrCreateActiveCart($request->user())
        );

        return response()->json([
            'success' => true,
            'message' => 'Carrito obtenido correctamente.',
            'data' => new CartResource($cart),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $recoverableOrder = $this->orderService->findRecoverablePendingOrder($request->user());

        if ($recoverableOrder) {
            return response()->json([
                'success' => true,
                'message' => 'Hay un carrito pendiente de recuperar.',
                'data' => [
                    'cart' => null,
                    'summary' => null,
                    'recoverable_order' => $this->orderService->recoverableOrderPayload($recoverableOrder),
                ],
            ]);
        }

        $cart = $this->cartService->recalculateCart(
            $this->cartService->getOrCreateActiveCart($request->user())
        );

        return response()->json([
            'success' => true,
            'message' => 'Resumen del carrito obtenido correctamente.',
            'data' => new CartSummaryResource($cart),
        ]);
    }

    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $product = Product::query()
            ->with(['category', 'family'])
            ->findOrFail($request->integer('product_id'));
        
        if (!$product) {
            return $this->errorResponse('El producto no existe.');
        }

        $validationError = $this->validateProductCanBeAdded($product, $request->user());

        if ($validationError) {
            return $validationError;
        }

        $cart = $this->cartService->addItem(
            user: $request->user(),
            product: $product,
            quantity: (float) $request->input('quantity')
        );

        return response()->json([
            'success' => true,
            'message' => 'Producto agregado al carrito correctamente.',
            'data' => new CartResource($cart),
        ], 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartService->updateItemQuantity(
            user: $request->user(),
            item: $item,
            quantity: (float) $request->input('quantity')
        );

        return response()->json([
            'success' => true,
            'message' => 'Cantidad actualizada correctamente.',
            'data' => new CartResource($cart),
        ]);
    }

    public function destroyItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            user: $request->user(),
            item: $item
        );

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado del carrito correctamente.',
            'data' => new CartResource($cart),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clearCart($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Carrito vaciado correctamente.',
            'data' => new CartResource($cart),
        ]);
    }

    public function downloadExcelLayout(Request $request): BinaryFileResponse
    {
        $path = $this->cartExcelService->createLayoutWorkbookPath($request->user());

        return response()->download(
            $path,
            'layout-carga-carrito.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importExcel(ImportCartExcelRequest $request): JsonResponse
    {
        $result = $this->cartExcelService->importIntoCart($request->user(), $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Archivo Excel procesado correctamente.',
            'data' => [
                'cart' => new CartResource($result['cart']),
                'summary' => $result['summary'],
            ],
        ]);
    }

    public function selectPromotionGift(SelectPromotionGiftRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->loadUsablePromotion($request, $promotion);
        $this->ensurePromotionType($promotion, 'brand_amount_choose_gift_item');

        $cart = $this->cartService
            ->getOrCreateActiveCart($request->user())
            ->load(['items.product.category', 'items.product.family']);

        $this->ensureBrandAmountPromotionIsEligible($cart, $promotion);

        $giftItem = $promotion->giftItems()
            ->where('gift_items.id', $request->integer('gift_item_id'))
            ->where('gift_items.is_active', true)
            ->first();

        if (! $giftItem) {
            return $this->errorResponse('El artículo de regalo no pertenece a esta promoción.', 422);
        }

        $cart = $this->cartService->selectPromotionGiftItem($cart, $promotion, $giftItem);

        return response()->json([
            'success' => true,
            'message' => 'Regalo seleccionado correctamente.',
            'data' => [
                'cart' => new CartResource($cart),
                'selected_gift_item' => [
                    'id' => $giftItem->id,
                    'name' => $giftItem->name,
                    'code' => $giftItem->code,
                    'description' => $giftItem->description,
                    'image_url' => $giftItem->image_url,
                    'estimated_value' => $giftItem->estimated_value !== null ? (float) $giftItem->estimated_value : null,
                    'unit_label' => $giftItem->unit_label,
                ],
            ],
        ]);
    }

    public function clearPromotionGift(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->loadUsablePromotion($request, $promotion);
        $this->ensurePromotionType($promotion, 'brand_amount_choose_gift_item');

        $cart = $this->cartService
            ->getOrCreateActiveCart($request->user())
            ->load(['items.product.category', 'items.product.family']);

        $cart = $this->cartService->clearPromotionGiftItemSelection($cart, $promotion);

        return response()->json([
            'success' => true,
            'message' => 'Selección de regalo eliminada correctamente.',
            'data' => new CartResource($cart),
        ]);
    }

    public function addPromotionGiftProduct(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->loadUsablePromotion($request, $promotion);
        $this->ensurePromotionType($promotion, 'brand_amount_get_product');

        $cart = $this->cartService
            ->getOrCreateActiveCart($request->user())
            ->load(['items.product.category', 'items.product.family']);

        $this->ensureBrandAmountPromotionIsEligible($cart, $promotion);

        $targetProductId = (int) data_get($promotion->config, 'target_product_id', 0);
        $targetQuantity = (float) data_get($promotion->config, 'target_quantity', 1);

        $product = Product::query()
            ->with(['category', 'family'])
            ->find($targetProductId);

        if (! $product) {
            return $this->errorResponse('El SKU asignado a la promoción no existe.', 422);
        }

        $validationError = $this->validateProductCanBeAdded($product, $request->user());

        if ($validationError) {
            return $validationError;
        }

        $existingQuantity = (float) optional($cart->items->firstWhere('product_id', $targetProductId))->quantity;
        $missingQuantity = max(0, round($targetQuantity - $existingQuantity, 2));

        if ($missingQuantity > 0) {
            $cart = $this->cartService->addItem(
                user: $request->user(),
                product: $product,
                quantity: $missingQuantity
            );
        } else {
            $cart = $this->cartService->recalculateCart($cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'SKU de regalo agregado al carrito correctamente.',
            'data' => [
                'cart' => new CartResource($cart),
                'gift_product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'quantity_added' => $missingQuantity,
                    'target_quantity' => $targetQuantity,
                ],
            ],
        ]);
    }

    protected function validateProductCanBeAdded(Product $product, $user): ?JsonResponse
    {
        $attributes = $product->getAttributes();

        // Validar activo (si existe campo)
        if (array_key_exists('is_active', $attributes) && ! (bool) $product->is_active) {
            return $this->errorResponse('El producto no está disponible actualmente.');
        }

        if (array_key_exists('status', $attributes) && (string) $product->status !== 'active') {
            return $this->errorResponse('El producto no está disponible actualmente.');
        }

        // Validar precio
        if ((float) $this->productPriceService->priceForProduct($product, $user)['price'] <= 0) {
            return $this->errorResponse('El producto no tiene un precio válido.');
        }

        return null;
    }

    protected function errorResponse(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    protected function loadUsablePromotion(Request $request, Promotion $promotion): Promotion
    {
        return Promotion::query()
            ->with(['products', 'giftItems'])
            ->usable($request->user())
            ->findOrFail($promotion->id);
    }

    protected function ensurePromotionType(Promotion $promotion, string $expectedType): void
    {
        abort_unless($promotion->type->value === $expectedType, 422, 'La promoción no corresponde a esta acción.');
    }

    protected function ensureBrandAmountPromotionIsEligible($cart, Promotion $promotion): void
    {
        $brand = trim((string) data_get($promotion->config, 'brand', ''));
        $minimumAmount = (float) data_get($promotion->config, 'minimum_amount', 0);

        $brandSubtotal = round((float) $cart->items
            ->filter(fn ($item) => mb_strtolower(trim((string) $item->brand_snapshot)) === mb_strtolower($brand))
            ->sum(fn ($item) => (float) $item->base_unit_price_snapshot * (float) $item->quantity), 2);

        abort_if($brand === '' || $minimumAmount <= 0, 422, 'La promoción no tiene una configuración válida.');
        abort_if($brandSubtotal < $minimumAmount, 422, 'El carrito todavía no cumple con el monto mínimo de la promoción.');
    }
}
