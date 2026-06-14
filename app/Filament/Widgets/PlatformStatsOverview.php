<?php

namespace App\Filament\Widgets;

use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\Product;
use App\Models\StockAlert;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected function getHeading(): ?string
    {
        return auth()->user()?->is_platform_user
            ? 'Platform Summary'
            : 'Organisation Summary';
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        // Tenant-owned models are scoped to the user's customer automatically
        // (see App\Models\Concerns\BelongsToCustomer), so these counts reflect
        // the current organisation for non-platform users and totals for staff.
        $products = Product::where('status', 'active')->count();
        $documents = DocumentFile::count();
        $boxes = Box::count();
        $openAlerts = StockAlert::where('status', 'open')->count();

        $stats = [
            Stat::make('Active Products', $products)
                ->description('In-catalogue items')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
            Stat::make('Documents', $documents)
                ->description('Files under management')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
            Stat::make('Boxes', $boxes)
                ->description('Storage containers')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray'),
            Stat::make('Open Stock Alerts', $openAlerts)
                ->description($openAlerts > 0 ? 'Need attention' : 'All clear')
                ->descriptionIcon($openAlerts > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($openAlerts > 0 ? 'warning' : 'success'),
        ];

        // Platform staff additionally see cross-tenant metrics.
        if ($user?->is_platform_user) {
            array_unshift(
                $stats,
                Stat::make('Customers', Customer::count())
                    ->description('Organisations')
                    ->descriptionIcon('heroicon-m-building-office-2')
                    ->color('primary'),
                Stat::make('Active Subscriptions', CustomerSubscription::whereIn('status', ['active', 'near_expiry'])->count())
                    ->description('Currently billable')
                    ->descriptionIcon('heroicon-m-credit-card')
                    ->color('success'),
            );
        }

        return $stats;
    }
}
