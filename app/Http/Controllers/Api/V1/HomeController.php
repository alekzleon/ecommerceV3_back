<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Banner\BannerResource;
use App\Http\Resources\BrandBanner\BrandBannerResource;
use App\Http\Resources\SiteSettingResource;
use App\Models\Banner;
use App\Models\BrandBanner;
use App\Models\Category;
use App\Models\EcommerceSetting;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function storefront(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->storefrontPayload(),
        ]);
    }

    public function home(): JsonResponse
    {
        $storefront = $this->storefrontPayload();

        if (! (bool) data_get($storefront, 'is_published', false)) {
            return response()->json([
                'ok' => true,
                'message' => 'El ecommerce todavía no está publicado.',
                'data' => [
                    'storefront' => $storefront,
                    'sections' => [],
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'storefront' => $storefront,
                'sections' => $this->homeSections(),
            ],
        ]);
    }

    protected function storefrontPayload(): array
    {
        $storefront = EcommerceSetting::storefrontSettings();
        $template = EcommerceSetting::homeTemplateSettings();

        return [
            'is_published' => (bool) data_get($storefront, 'is_published', false),
            'construction' => [
                'title' => data_get($storefront, 'construction_title'),
                'message' => data_get($storefront, 'construction_message'),
            ],
            'active_template' => data_get($template, 'active_template', EcommerceSetting::HOME_TEMPLATE_CLASSIC),
            'available_templates' => EcommerceSetting::availableTemplates(),
        ];
    }

    protected function homeSections(): array
    {
        return [
            'hero_banners' => BannerResource::collection(
                Banner::query()->active()->currentWindow()->ordered()->limit(5)->get()
            )->resolve(),
            'hero_brand_banners' => BrandBannerResource::collection(
                BrandBanner::query()->active()->currentWindow()->ordered()->limit(5)->get()
            )->resolve(),
            'benefits' => $this->homeBenefitsValue(),
            'featured_categories' => $this->featuredCategories(),
            'promotions' => $this->promotions(),
            'daily_offers' => $this->promotions(2),
            'featured_products' => $this->featuredProducts(),
            'recent_purchase_products' => $this->featuredProducts(8),
            'brand_banners' => BrandBannerResource::collection(
                BrandBanner::query()->active()->currentWindow()->ordered()->limit(6)->get()
            )->resolve(),
            'footer_settings' => (new SiteSettingResource(SiteSetting::current()))->resolve(),
        ];
    }

    protected function homeBenefitsValue(): array
    {
        return collect([1, 2, 3])
            ->map(function (int $benefit) {
                $value = EcommerceSetting::homeBenefitValue($benefit);
                $path = data_get($value, 'icon_path');
                $disk = data_get($value, 'icon_disk', 'public') ?: 'public';

                return [
                    'benefit' => $benefit,
                    'title' => data_get($value, 'title'),
                    'text' => data_get($value, 'text'),
                    'icon_disk' => $disk,
                    'icon_path' => $path,
                    'icon_url' => $path ? Storage::disk($disk)->url($path) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function featuredCategories(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(8)
            ->get([
                'id',
                'grupo_linea_id',
                'code',
                'name',
                'slug',
                'image_path',
            ])
            ->map(fn (Category $category) => [
                'id' => $category->grupo_linea_id ?? $category->id,
                'local_id' => $category->id,
                'grupo_linea_id' => $category->grupo_linea_id,
                'code' => $category->code,
                'name' => $category->name,
                'slug' => $category->slug,
                'image_path' => $category->image_path,
                'image_url' => $category->image_url,
            ])
            ->values()
            ->all();
    }

    protected function promotions(int $limit = 6): array
    {
        return Promotion::query()
            ->with(['products:id,name,slug,sku'])
            ->withCount('products')
            ->active()
            ->currentWindow()
            ->where('is_general', true)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Promotion $promotion) => [
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
                'products_count' => (int) ($promotion->products_count ?? 0),
                'product_ids' => $promotion->products->pluck('id')->values(),
            ])
            ->values()
            ->all();
    }

    protected function featuredProducts(int $limit = 12): array
    {
        return Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug',
            ])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'family_id' => $product->family_id,
                'category' => $product->category ? [
                    'id' => $product->category->grupo_linea_id ?? $product->category->id,
                    'local_id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'family' => $product->family ? [
                    'id' => $product->family->linea_articulo_id ?? $product->family->id,
                    'local_id' => $product->family->id,
                    'name' => $product->family->name,
                    'slug' => $product->family->slug,
                ] : null,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'short_description' => $product->short_description,
                'image_path' => $product->image_path,
                'image_url' => $product->image_url,
                'default_price' => (float) $product->default_price,
                'stock' => $product->stock !== null ? (float) $product->stock : null,
            ])
            ->values()
            ->all();
    }
}
