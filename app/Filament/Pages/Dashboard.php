<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OperationalStatsOverview;
use App\Filament\Widgets\PlatformStatsOverview;
use App\Filament\Widgets\RecentActivityWidget;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Platform Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
            OperationalStatsOverview::class,
            RecentActivityWidget::class,
        ];
    }
}
