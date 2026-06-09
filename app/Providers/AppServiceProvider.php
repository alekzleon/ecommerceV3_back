<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\CustomerPfrProfile;
use App\Models\CustomerProfile;
use App\Models\Family;
use App\Models\GiftItem;
use App\Models\MonthlyPromotion;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ActivityLogObserver::class);
        Promotion::observe(ActivityLogObserver::class);
        GiftItem::observe(ActivityLogObserver::class);
        MonthlyPromotion::observe(ActivityLogObserver::class);
        Banner::observe(ActivityLogObserver::class);
        User::observe(ActivityLogObserver::class);
        CustomerProfile::observe(ActivityLogObserver::class);
        CustomerPfrProfile::observe(ActivityLogObserver::class);
        UserAddress::observe(ActivityLogObserver::class);
        Role::observe(ActivityLogObserver::class);
        Category::observe(ActivityLogObserver::class);
        Family::observe(ActivityLogObserver::class);
    }
}
