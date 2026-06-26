<?php

namespace App\Filament\Widgets;

use App\Models\DocumentMovementLog;
use App\Services\MovementTimelineService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Recently created, recent activity" feed for the dashboard (production-
 * readiness roadmap #6 / #30), reusing the same human-readable labels as the
 * per-record Activity Timeline.
 */
class RecentActivityWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $timeline = app(MovementTimelineService::class);

        return $table
            ->heading('Recent Activity')
            ->query(DocumentMovementLog::query()->latest('performed_at'))
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('When')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->formatStateUsing(fn (DocumentMovementLog $record): string => $timeline->title($record)),
                Tables\Columns\TextColumn::make('detail')
                    ->label('Detail')
                    ->state(fn (DocumentMovementLog $record): string => $timeline->detail($record)),
                Tables\Columns\TextColumn::make('performedBy.name')->label('By')->default('System'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }
}
