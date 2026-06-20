<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\SearchSuggestionController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\PromotionController as CustomerPromotionController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\BrandBannerController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactFaqController;
use App\Http\Controllers\Api\V1\ContactLeadController;
use App\Http\Controllers\Api\V1\EcommerceSettingController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\FavoriteController;
use App\Http\Controllers\Api\V1\Account\CustomerPfrProfileController;
use App\Http\Controllers\Api\V1\Account\WishlistController;
use App\Http\Controllers\Api\V1\MonthlyPromotionController;
use App\Http\Resources\Account\AddressResource;

// Controllers admin
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EcommerceSettingController as AdminEcommerceSettingController;
use App\Http\Controllers\Api\V1\Admin\CartController as AdminCartController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\CreditController;
use App\Http\Controllers\Api\V1\Admin\CollectionController;
use App\Http\Controllers\Api\V1\Admin\ContactFaqController as AdminContactFaqController;
use App\Http\Controllers\Api\V1\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\MarketingController;
use App\Http\Controllers\Api\V1\Admin\PromotionController;
use App\Http\Controllers\Api\V1\Admin\LogController;
use App\Http\Controllers\Api\V1\Admin\SyncController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\AdminNavigationController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdProductController;
use App\Http\Controllers\Api\V1\Admin\ProductPriceScaleController;
use App\Http\Controllers\Api\V1\Admin\ProductGalleryItemController;
use App\Http\Controllers\Api\V1\Admin\ProductVariantController;
use App\Http\Controllers\Api\V1\Admin\VariantAttributeController;
use App\Http\Controllers\Api\V1\Admin\VariantAttributeValueController;
use App\Http\Controllers\Api\V1\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\V1\Admin\BrandBannerController as AdminBrandBannerController;
use App\Http\Controllers\Api\V1\Admin\MonthlyPromotionController as AdminMonthlyPromotionController;
use App\Http\Controllers\Api\V1\Admin\GiftItemController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1_ping')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'ok' => true,
            'message' => 'API Cloudi Shop funcionando',
        ]);
    });
});

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth pública
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    /*
    |--------------------------------------------------------------------------
    | Rutas públicas ecommerce
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/recent-purchases', [ProductController::class, 'recentPurchases']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/catalog/sidebar', [CatalogController::class, 'sidebar']);
    Route::get('/search/suggestions', [SearchSuggestionController::class, 'index']);
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/brand-banners', [BrandBannerController::class, 'index']);
    Route::get('/monthly-promotions', [MonthlyPromotionController::class, 'index']);
    Route::get('/settings', [SiteSettingController::class, 'show']);
    Route::get('/ecommerce-settings/nav-title', [EcommerceSettingController::class, 'navTitle']);
    Route::get('/ecommerce-settings/general-logo', [EcommerceSettingController::class, 'generalLogo']);
    Route::get('/ecommerce-settings/contact-faq-image', [EcommerceSettingController::class, 'contactFaqImage']);
    Route::get('/ecommerce-settings/contact-map-url', [EcommerceSettingController::class, 'contactMapUrl']);
    Route::get('/ecommerce-settings/meta-pixel', [EcommerceSettingController::class, 'metaPixel']);
    Route::get('/ecommerce-settings/abandoned-cart', [EcommerceSettingController::class, 'abandonedCart']);
    Route::get('/ecommerce-settings/home-benefits', [EcommerceSettingController::class, 'homeBenefits']);
    Route::get('/ecommerce-settings/home-benefits/{benefit}', [EcommerceSettingController::class, 'homeBenefit']);
    Route::get('/contact-faqs', [ContactFaqController::class, 'index']);
    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/contact-leads', [ContactLeadController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Promociones públicas
    |--------------------------------------------------------------------------
    | Estas rutas me sirven para mostrar promociones activas en home,
    | banners, cards de producto, bloques de ofertas, etc.
    |--------------------------------------------------------------------------
    */
    Route::get('/promotions/random', [CustomerPromotionController::class, 'random']);
    Route::get('/promotions/random-six', [CustomerPromotionController::class, 'randomSix']);
    Route::get('/promotions/all', [CustomerPromotionController::class, 'all']);
    Route::get('/promotions', [CustomerPromotionController::class, 'index']);

    Route::post('/webhooks/stripe', StripeWebhookController::class);

    /*
    |--------------------------------------------------------------------------
    | Rutas autenticadas generales
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Auth privada
        |--------------------------------------------------------------------------
        */
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Carrito
        |--------------------------------------------------------------------------
        | El carrito debe regresar ya calculadas las promociones.
        | La idea es que el front no haga cálculos, solo pinte lo que
        | Laravel le regresa.
        |--------------------------------------------------------------------------
        */
        Route::get('/cart', [CartController::class, 'index']);
        Route::get('/cart/summary', [CartController::class, 'summary']);
        Route::get('/cart/excel/layout', [CartController::class, 'downloadExcelLayout']);
        Route::post('/cart/excel/import', [CartController::class, 'importExcel']);
        Route::post('/cart/items', [CartController::class, 'storeItem']);
        Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{item}', [CartController::class, 'destroyItem']);
        Route::delete('/cart/items', [CartController::class, 'clear']);
        Route::post('/cart/cashback/apply', [CartController::class, 'applyCashback']);
        Route::delete('/cart/cashback', [CartController::class, 'clearCashback']);
        Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
        Route::delete('/cart/coupon', [CartController::class, 'clearCoupon']);
        Route::post('/cart/promotions/{promotion}/select-gift', [CartController::class, 'selectPromotionGift']);
        Route::delete('/cart/promotions/{promotion}/select-gift', [CartController::class, 'clearPromotionGift']);
        Route::post('/cart/promotions/{promotion}/add-gift-product', [CartController::class, 'addPromotionGiftProduct']);
        Route::post('/cart/abandoned/{cart}/recover', [CartController::class, 'recoverAbandoned']);

        /*
        |--------------------------------------------------------------------------
        | Checkout
        |--------------------------------------------------------------------------
        | Previa de factura basada en el carrito actual. Recalcula promociones,
        | respeta unidades regalo a $0.10 y devuelve desglose listo para UI.
        |--------------------------------------------------------------------------
        */
        Route::get('/checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('/checkout/validate', [CheckoutController::class, 'validateCart']);
        Route::post('/checkout/orders', [CheckoutController::class, 'createOrder']);
        Route::get('/checkout/orders/{order}', [CheckoutController::class, 'showOrder']);
        Route::post('/checkout/orders/{order}/restore-cart', [CheckoutController::class, 'restoreCartFromOrder']);
        Route::post('/checkout/recoverable-order/restore', [CheckoutController::class, 'restoreRecoverableOrder']);
        Route::post('/checkout/stripe/session', [CheckoutController::class, 'createStripeSession']);
        Route::post('/checkout/stripe/session/confirm', [CheckoutController::class, 'confirmStripeSession']);

        /*
        |--------------------------------------------------------------------------
        | Promociones del cliente autenticado
        |--------------------------------------------------------------------------
        | Estas rutas me ayudan a:
        | - ver promociones aplicadas al carrito
        | - ver promociones elegibles
        | - forzar recálculo si lo necesito
        |--------------------------------------------------------------------------
        */
        Route::get('/promotions/cart', [CustomerPromotionController::class, 'cartPromotions']);
        Route::post('/promotions/recalculate', [CustomerPromotionController::class, 'recalculate']);

        /*
        |--------------------------------------------------------------------------
        | Cuenta del cliente
        |--------------------------------------------------------------------------
        | Aquí más adelante puedo meter perfil, direcciones,
        | pedidos del cliente, favoritos, etc.
        |--------------------------------------------------------------------------
        */
        Route::prefix('account')->group(function () {
            Route::get('/profile', function (Request $request) {
                $user = $request->user()->loadMissing(['role', 'defaultAddress']);

                return response()->json([
                    'ok' => true,
                    'message' => 'Perfil del cliente obtenido correctamente.',
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role ? [
                            'id' => $user->role->id,
                            'name' => $user->role->name,
                            'display_name' => $user->role->display_name,
                        ] : null,
                        'default_address' => $user->defaultAddress
                            ? new AddressResource($user->defaultAddress)
                            : null,
                    ],
                ]);
            });

            Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']);
            Route::apiResource('addresses', AddressController::class);
            Route::get('/favorites', [FavoriteController::class, 'index']);
            Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
            Route::get('/wishlists/options', [WishlistController::class, 'options']);
            Route::post('/wishlists/products', [WishlistController::class, 'addProduct']);
            Route::delete('/wishlists/{wishlist}/products/{product}', [WishlistController::class, 'removeProduct']);
            Route::apiResource('wishlists', WishlistController::class);
            Route::get('/customer-pfr-profile', [CustomerPfrProfileController::class, 'show']);
            Route::post('/customer-pfr-profile', [CustomerPfrProfileController::class, 'store']);

            Route::get('/orders', function () {
                return response()->json([
                    'ok' => true,
                    'message' => 'Pedidos del cliente',
                ]);
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Rutas administrativas
        |--------------------------------------------------------------------------
        */
        Route::prefix('admin')
            ->middleware(['not_client'])
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */
                Route::get('/dashboard', [DashboardController::class, 'index'])
                    ->middleware('module:dashboard');

                Route::get('/navigation/menu', [AdminNavigationController::class, 'menu']);

                /*
                |--------------------------------------------------------------------------
                | Administración
                |--------------------------------------------------------------------------
                */
                Route::get('users/form-options', [UserController::class, 'formOptions'])
                    ->middleware('module:usuarios');

                Route::apiResource('users', UserController::class)
                    ->middleware('module:usuarios');

                Route::apiResource('roles', RoleController::class)
                    ->middleware('module:roles');

                /*
                |--------------------------------------------------------------------------
                | Operación
                |--------------------------------------------------------------------------
                */
                Route::apiResource('orders', OrderController::class)
                    ->middleware('module:pedidos');

                Route::get('carts', [AdminCartController::class, 'index'])
                    ->middleware('module:pedidos');

                Route::get('carts/{cart}', [AdminCartController::class, 'show'])
                    ->middleware('module:pedidos');

                Route::post('carts/{cart}/remind', [AdminCartController::class, 'remind'])
                    ->middleware('module:pedidos');

                Route::delete('carts/{cart}/items', [AdminCartController::class, 'clear'])
                    ->middleware('module:pedidos');

                Route::get('customers', [CustomerController::class, 'index'])
                    ->middleware('module:clientes');

                Route::post('customers/invite', [CustomerController::class, 'invite'])
                    ->middleware('module:clientes');

                Route::post('customers', [CustomerController::class, 'store'])
                    ->middleware('module:clientes');

                Route::get('customers/{customer}', [CustomerController::class, 'show'])
                    ->middleware('module:clientes');

                Route::put('customers/{customer}', [CustomerController::class, 'update'])
                    ->middleware('module:clientes');

                Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
                    ->middleware('module:clientes');

                Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])
                    ->middleware('module:clientes');

                Route::get('products', [AdProductController::class, 'index'])
                    ->middleware('module:productos');

                Route::post('products', [AdProductController::class, 'store'])
                    ->middleware('module:productos');

                Route::get('products/{product}', [AdProductController::class, 'show'])
                    ->middleware('module:productos');

                Route::post('products/{product}', [AdProductController::class, 'update'])
                    ->middleware('module:productos');

                Route::put('products/{product}', [AdProductController::class, 'update'])
                    ->middleware('module:productos');

                Route::patch('products/{product}', [AdProductController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}', [AdProductController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/status', [AdProductController::class, 'updateStatus'])
                    ->middleware('module:productos');

                Route::get('products/{product}/price-scales', [ProductPriceScaleController::class, 'show'])
                    ->middleware('module:productos');

                Route::put('products/{product}/price-scales', [ProductPriceScaleController::class, 'update'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/price-scales', [ProductPriceScaleController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}/price-scales', [ProductPriceScaleController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::get('products/{product}/gallery', [ProductGalleryItemController::class, 'index'])
                    ->middleware('module:productos');

                Route::post('products/{product}/gallery', [ProductGalleryItemController::class, 'store'])
                    ->middleware('module:productos');

                Route::post('products/{product}/gallery/reorder', [ProductGalleryItemController::class, 'reorder'])
                    ->middleware('module:productos');

                Route::get('products/{product}/gallery/{galleryItem}', [ProductGalleryItemController::class, 'show'])
                    ->middleware('module:productos');

                Route::post('products/{product}/gallery/{galleryItem}', [ProductGalleryItemController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}/gallery/{galleryItem}', [ProductGalleryItemController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/gallery/{galleryItem}/toggle', [ProductGalleryItemController::class, 'toggle'])
                    ->middleware('module:productos');

                Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variants/reorder', [ProductVariantController::class, 'reorder'])
                    ->middleware('module:productos');

                Route::get('products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/variants/{variant}/status', [ProductVariantController::class, 'updateStatus'])
                    ->middleware('module:productos');

                Route::get('products/{product}/variant-attributes', [VariantAttributeController::class, 'index'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variant-attributes', [VariantAttributeController::class, 'store'])
                    ->middleware('module:productos');

                Route::get('products/{product}/variant-attributes/{variantAttribute}', [VariantAttributeController::class, 'show'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variant-attributes/{variantAttribute}', [VariantAttributeController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}/variant-attributes/{variantAttribute}', [VariantAttributeController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/variant-attributes/{variantAttribute}/toggle', [VariantAttributeController::class, 'toggle'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variant-attributes/{variantAttribute}/values', [VariantAttributeValueController::class, 'store'])
                    ->middleware('module:productos');

                Route::post('products/{product}/variant-attributes/{variantAttribute}/values/{attributeValue}', [VariantAttributeValueController::class, 'update'])
                    ->middleware('module:productos');

                Route::delete('products/{product}/variant-attributes/{variantAttribute}/values/{attributeValue}', [VariantAttributeValueController::class, 'destroy'])
                    ->middleware('module:productos');

                Route::patch('products/{product}/variant-attributes/{variantAttribute}/values/{attributeValue}/toggle', [VariantAttributeValueController::class, 'toggle'])
                    ->middleware('module:productos');


                /*
                |--------------------------------------------------------------------------
                | Finanzas
                |--------------------------------------------------------------------------
                */
                Route::apiResource('credit', CreditController::class)
                    ->middleware('module:credito');

                Route::apiResource('collections', CollectionController::class)
                    ->middleware('module:cobranza');

                /*
                |--------------------------------------------------------------------------
                | Marketing
                |--------------------------------------------------------------------------
                */
                Route::apiResource('marketing', MarketingController::class)
                    ->middleware('module:marketing');

                Route::get('coupons/form-options', [AdminCouponController::class, 'formOptions'])
                    ->middleware('module:promociones');

                Route::patch('coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])
                    ->middleware('module:promociones');

                Route::post('coupons/{coupon}/send', [AdminCouponController::class, 'send'])
                    ->middleware('module:promociones');

                Route::apiResource('coupons', AdminCouponController::class)
                    ->middleware('module:promociones');

                Route::patch('banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])
                    ->middleware('module:marketing');

                Route::post('banners/reorder', [AdminBannerController::class, 'reorder'])
                    ->middleware('module:marketing');

                Route::apiResource('banners', AdminBannerController::class)
                    ->middleware('module:marketing');

                Route::patch('brand-banners/{brandBanner}/toggle', [AdminBrandBannerController::class, 'toggle'])
                    ->middleware('module:marketing');

                Route::post('brand-banners/reorder', [AdminBrandBannerController::class, 'reorder'])
                    ->middleware('module:marketing');

                Route::post('brand-banners/{brandBanner}', [AdminBrandBannerController::class, 'update'])
                    ->middleware('module:marketing');

                Route::apiResource('brand-banners', AdminBrandBannerController::class)
                    ->parameters(['brand-banners' => 'brandBanner'])
                    ->middleware('module:marketing');

                Route::patch('monthly-promotions/{monthlyPromotion}/toggle', [AdminMonthlyPromotionController::class, 'toggle'])
                    ->middleware('module:marketing');

                Route::post('monthly-promotions/reorder', [AdminMonthlyPromotionController::class, 'reorder'])
                    ->middleware('module:marketing');

                Route::post('monthly-promotions/{monthlyPromotion}', [AdminMonthlyPromotionController::class, 'update'])
                    ->middleware('module:marketing');

                Route::apiResource('monthly-promotions', AdminMonthlyPromotionController::class)
                    ->parameters(['monthly-promotions' => 'monthlyPromotion'])
                    ->middleware('module:marketing');

                Route::get('promotions/form-options', [PromotionController::class, 'formOptions'])
                    ->middleware('module:promociones');

                Route::apiResource('promotions', PromotionController::class)
                    ->middleware('module:promociones');

                Route::patch('gift-items/{giftItem}/toggle', [GiftItemController::class, 'toggle'])
                    ->middleware('module:promociones');

                Route::post('gift-items/{giftItem}', [GiftItemController::class, 'update'])
                    ->middleware('module:promociones');

                Route::apiResource('gift-items', GiftItemController::class)
                    ->parameters(['gift-items' => 'giftItem'])
                    ->middleware('module:promociones');
                    
                /*
                |--------------------------------------------------------------------------
                | Rutas extra de promociones admin
                |--------------------------------------------------------------------------
                | Estas me sirven para activar/desactivar promociones,
                | asignar productos y hacer acciones puntuales sin forzar
                | todo por update general.
                |--------------------------------------------------------------------------
                */
                Route::patch('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])
                    ->middleware('module:promociones');

                Route::post('/promotions/{promotion}/sync-products', [PromotionController::class, 'syncProducts'])
                    ->middleware('module:promociones');

                /*
                |--------------------------------------------------------------------------
                | Control
                |--------------------------------------------------------------------------
                */
                Route::apiResource('logs', LogController::class)
                    ->middleware('module:logs');

                Route::post('sync/products', [SyncController::class, 'products'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/categories', [SyncController::class, 'categories'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/families', [SyncController::class, 'families'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/customers', [SyncController::class, 'customers'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/dirs-clientes', [SyncController::class, 'dirsClientes'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/claves-articulos', [SyncController::class, 'clavesArticulos'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/claves-clientes', [SyncController::class, 'clavesClientes'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/precios-articulos', [SyncController::class, 'preciosArticulos'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/precios-empresas', [SyncController::class, 'preciosEmpresas'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/precios-cli-cli', [SyncController::class, 'preciosCliCli'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/tipos-impuestos', [SyncController::class, 'tiposImpuestos'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/impuestos', [SyncController::class, 'impuestos'])
                    ->middleware('module:sincronizacion');

                Route::post('sync/impuestos-articulos', [SyncController::class, 'impuestosArticulos'])
                    ->middleware('module:sincronizacion');

                Route::get('sync/doctos-ve', [SyncController::class, 'doctosVe'])
                    ->middleware('module:sincronizacion');

                Route::get('sync/doctos-ve-encabezados', [SyncController::class, 'doctosVeEncabezados'])
                    ->middleware('module:sincronizacion');

                Route::get('sync/doctos-ve-detalles', [SyncController::class, 'doctosVeDetalles'])
                    ->middleware('module:sincronizacion');

                Route::patch('sync/doctos-ve/{doctoVe}/sincronizado', [SyncController::class, 'marcarDoctoVeSincronizado'])
                    ->middleware('module:sincronizacion');

                Route::apiResource('sync', SyncController::class)
                    ->middleware('module:sincronizacion');

                /*
                |--------------------------------------------------------------------------
                | Configuración
                |--------------------------------------------------------------------------
                */
                Route::get('settings', [SettingController::class, 'index'])
                    ->middleware('module:configuracion_ecommerce');

                Route::post('settings', [SettingController::class, 'store'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/nav-title', [AdminEcommerceSettingController::class, 'navTitle'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/nav-title', [AdminEcommerceSettingController::class, 'updateNavTitle'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/nav-title', [AdminEcommerceSettingController::class, 'updateNavTitle'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/general-logo', [AdminEcommerceSettingController::class, 'generalLogo'])
                    ->middleware('module:configuracion_ecommerce');

                Route::post('ecommerce-settings/general-logo', [AdminEcommerceSettingController::class, 'updateGeneralLogo'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/general-logo', [AdminEcommerceSettingController::class, 'updateGeneralLogo'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/general-logo', [AdminEcommerceSettingController::class, 'updateGeneralLogo'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/contact-faq-image', [AdminEcommerceSettingController::class, 'contactFaqImage'])
                    ->middleware('module:configuracion_ecommerce');

                Route::post('ecommerce-settings/contact-faq-image', [AdminEcommerceSettingController::class, 'updateContactFaqImage'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/contact-faq-image', [AdminEcommerceSettingController::class, 'updateContactFaqImage'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/contact-faq-image', [AdminEcommerceSettingController::class, 'updateContactFaqImage'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/contact-map-url', [AdminEcommerceSettingController::class, 'contactMapUrl'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/contact-map-url', [AdminEcommerceSettingController::class, 'updateContactMapUrl'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/contact-map-url', [AdminEcommerceSettingController::class, 'updateContactMapUrl'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/meta-pixel', [AdminEcommerceSettingController::class, 'metaPixel'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/meta-pixel', [AdminEcommerceSettingController::class, 'updateMetaPixel'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/meta-pixel', [AdminEcommerceSettingController::class, 'updateMetaPixel'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/abandoned-cart', [AdminEcommerceSettingController::class, 'abandonedCart'])
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/abandoned-cart', [AdminEcommerceSettingController::class, 'updateAbandonedCart'])
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/abandoned-cart', [AdminEcommerceSettingController::class, 'updateAbandonedCart'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/home-benefits', [AdminEcommerceSettingController::class, 'homeBenefits'])
                    ->middleware('module:configuracion_ecommerce');

                Route::get('ecommerce-settings/home-benefits/{benefit}', [AdminEcommerceSettingController::class, 'homeBenefit'])
                    ->whereNumber('benefit')
                    ->middleware('module:configuracion_ecommerce');

                Route::post('ecommerce-settings/home-benefits/{benefit}', [AdminEcommerceSettingController::class, 'updateHomeBenefit'])
                    ->whereNumber('benefit')
                    ->middleware('module:configuracion_ecommerce');

                Route::put('ecommerce-settings/home-benefits/{benefit}', [AdminEcommerceSettingController::class, 'updateHomeBenefit'])
                    ->whereNumber('benefit')
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('ecommerce-settings/home-benefits/{benefit}', [AdminEcommerceSettingController::class, 'updateHomeBenefit'])
                    ->whereNumber('benefit')
                    ->middleware('module:configuracion_ecommerce');

                Route::patch('contact-faqs/{contactFaq}/toggle', [AdminContactFaqController::class, 'toggle'])
                    ->middleware('module:configuracion_ecommerce');

                Route::post('contact-faqs/reorder', [AdminContactFaqController::class, 'reorder'])
                    ->middleware('module:configuracion_ecommerce');

                Route::post('contact-faqs/{contactFaq}', [AdminContactFaqController::class, 'update'])
                    ->middleware('module:configuracion_ecommerce');

                Route::apiResource('contact-faqs', AdminContactFaqController::class)
                    ->parameters(['contact-faqs' => 'contactFaq'])
                    ->middleware('module:configuracion_ecommerce');

                Route::apiResource('settings', SettingController::class)
                    ->except(['index', 'store'])
                    ->middleware('module:configuracion_ecommerce');
            });
    });
});
