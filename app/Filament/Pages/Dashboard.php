<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PlatformStatsOverview;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Platform Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
        ];
    }
}
