<?php

namespace App\Filament\Widgets;

use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use App\Models\Location;
use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Day-to-day operational KPIs for the dashboard (production-readiness
 * roadmap #6): shelves, today's movements, dispatched, overdue returns,
 * storage capacity, and recently added documents.
 */
class OperationalStatsOverview extends StatsOverviewWidget
{
    protected function getHeading(): ?string
    {
        return 'Operations Today';
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        // ponytail: heuristic match on location type name (no dedicated
        // "is_shelf" flag exists); revisit if location types get a proper
        // category column.
        $shelfCount = Location::whereHas('locationType', fn ($q) => $q->where('type_name', 'like', '%shelf%'))->count();
        $todaysMovements = DocumentMovementLog::where('performed_at', '>=', $today)->count()
            + StockMovement::where('performed_at', '>=', $today)->count();
        $dispatched = Box::where('status', 'moved_out')->count() + DocumentFile::where('current_status', 'moved_out')->count();
        $overdueReturns = DocumentFile::where('current_status', 'moved_out')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();
        $recentlyAdded = DocumentFile::where('created_at', '>=', $today)->count();

        $boxesWithCapacity = Box::whereNotNull('capacity_limit')->get();
        $avgCapacity = $boxesWithCapacity->isEmpty()
            ? null
            : (int) round($boxesWithCapacity->avg('capacity_percent'));

        return [
            Stat::make('Shelves', $shelfCount)
                ->description('Storage shelves/racks')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('gray'),
            Stat::make("Today's Movements", $todaysMovements)
                ->description('Stock + document movements')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),
            Stat::make('Dispatched', $dispatched)
                ->description('Boxes/files currently out')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('warning'),
            Stat::make('Overdue Returns', $overdueReturns)
                ->description($overdueReturns > 0 ? 'Out for 30+ days' : 'None overdue')
                ->descriptionIcon($overdueReturns > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueReturns > 0 ? 'danger' : 'success'),
            Stat::make('Storage Capacity', $avgCapacity === null ? '—' : "{$avgCapacity}%")
                ->description('Average box fill level')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color(match (true) {
                    $avgCapacity === null => 'gray',
                    $avgCapacity >= 90 => 'danger',
                    $avgCapacity >= 75 => 'warning',
                    default => 'success',
                }),
            Stat::make('Recently Added', $recentlyAdded)
                ->description('Documents created today')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color('primary'),
        ];
    }
}
