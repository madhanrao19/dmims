<?php

namespace App\Providers;

use App\Models\CustomerSubscription;
use App\Models\StockMovement;
use App\Observers\CustomerSubscriptionObserver;
use App\Observers\StockMovementObserver;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        if (class_exists(Filament::class)) {
            Filament::serving(function () {
                Filament::registerNavigationGroups([
                    NavigationGroup::make('Platform')->label('Platform'),
                    NavigationGroup::make('Subscription')->label('Subscription'),
                    NavigationGroup::make('Billing')->label('Billing'),
                    NavigationGroup::make('Locations')->label('Locations'),
                    NavigationGroup::make('Documents')->label('Documents'),
                    NavigationGroup::make('Document Tracking')->label('Document Tracking'),
                    NavigationGroup::make('Stock Inventory')->label('Stock Inventory'),
                    NavigationGroup::make('Shared Services')->label('Shared Services'),
                ]);

                Filament::registerUserMenuItems([
                    NavigationItem::make('Return to Site')
                        ->url(config('app.url'))
                        ->icon('heroicon-o-arrow-left'),
                ]);
            });
        }

        // Register model observers
        CustomerSubscription::observe(CustomerSubscriptionObserver::class);
        StockMovement::observe(StockMovementObserver::class);

        // routes/api.php's v1 group is lightweight and read-only; 60/min per
        // token (falling back to IP) is generous headroom, not a hard cap.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) env('API_RATE_LIMIT_PER_MINUTE', 60))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
