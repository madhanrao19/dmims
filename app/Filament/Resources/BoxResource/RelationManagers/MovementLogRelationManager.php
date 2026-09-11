<?php

namespace App\Filament\Resources\BoxResource\RelationManagers;

use App\Filament\Resources\DocumentMovementLogResource;
use App\Models\Box;
use App\Models\DocumentMovementLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Box View tab: location transfer and dispatch history, scoped to this box
 * via Box::movementLogs(). Own column set (not DocumentMovementLogResource's
 * — that resource's own standalone list spans both Box and Document File
 * movements and needs separate From Location/From Box columns for that; a
 * Box only ever moves between Locations, so its own tab combines From/To
 * into one column each via DocumentMovementLog::fromLabel()/toLabel()).
 */
class MovementLogRelationManager extends RelationManager
{
    protected static string $relationship = 'movementLogs';

    protected static ?string $title = 'Box Movement Log';

    public function table(Table $table): Table
    {
        /** @var Box $box */
        $box = $this->getOwnerRecord();

        return $table
            ->query($box->movementLogs()->getQuery()->with(['fromLocation', 'toLocation', 'performedBy']))
            ->columns([
                TextColumn::make('performed_at')->label('Date & Time')->dateTime()->sortable(),
                TextColumn::make('action_type')->label('Movement Type')->sortable()->searchable()
                    ->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                TextColumn::make('from')->label('From Location')
                    ->state(fn (DocumentMovementLog $record): string => $record->fromLabel()),
                TextColumn::make('to')->label('To Location / Destination')
                    ->state(fn (DocumentMovementLog $record): string => $record->toLabel()),
                TextColumn::make('performedBy.name')->label('Operator')->sortable()->placeholder('System'),
                TextColumn::make('movement_no')->label('Reference / Tracking No')->sortable()->searchable(),
            ])
            ->recordUrl(function (Model $record): ?string {
                foreach (['view', 'edit'] as $action) {
                    if (! DocumentMovementLogResource::hasPage($action) || ! DocumentMovementLogResource::{'can'.ucfirst($action)}($record)) {
                        continue;
                    }

                    return DocumentMovementLogResource::getUrl($action, ['record' => $record]);
                }

                return null;
            })
            ->defaultSort('performed_at', 'desc');
    }
}
