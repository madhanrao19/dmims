<?php

namespace App\Providers;

use App\Models\CustomerSubscription;
use App\Models\StockMovement;
use App\Observers\CustomerSubscriptionObserver;
use App\Observers\StockMovementObserver;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
    }
}
