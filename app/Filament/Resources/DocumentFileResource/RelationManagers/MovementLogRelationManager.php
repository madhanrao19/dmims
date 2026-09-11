<?php

namespace App\Filament\Resources\DocumentFileResource\RelationManagers;

use App\Filament\Resources\DocumentMovementLogResource;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Document File View tab: box assignment/transfer/dispatch history, scoped
 * to this file via DocumentFile::movementLogs(). Own column set — a
 * Document File can move Box-to-Box, Box-to-Location, or out to an external
 * destination, so From/To each combine box/location/destination into one
 * column via DocumentMovementLog::fromLabel()/toLabel()) rather than four
 * separate mostly-empty columns. Mirrors BoxResource's own
 * MovementLogRelationManager.
 */
class MovementLogRelationManager extends RelationManager
{
    protected static string $relationship = 'movementLogs';

    protected static ?string $title = 'Document Movement Log';

    public function table(Table $table): Table
    {
        /** @var DocumentFile $file */
        $file = $this->getOwnerRecord();

        return $table
            ->query($file->movementLogs()->getQuery()->with(['fromBox', 'toBox', 'fromLocation', 'toLocation', 'performedBy']))
            ->columns([
                TextColumn::make('performed_at')->label('Date & Time')->dateTime()->sortable(),
                TextColumn::make('action_type')->label('Movement Type')->sortable()->searchable()
                    ->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                TextColumn::make('from')->label('From Box / Location')
                    ->state(fn (DocumentMovementLog $record): string => $record->fromLabel()),
                TextColumn::make('to')->label('To Box / Destination')
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
