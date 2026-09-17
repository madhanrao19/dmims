<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Filament\Resources\BoxResource;
use App\Models\Box;
use App\Models\Location;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Location View tab: boxes placed directly at this location
 * (Location::boxes(), keyed on Box::current_location_id) — same
 * inline-relation-manager-tab pattern as ViewBox's own "Documents Inside"
 * (BoxResource\RelationManagers\DocumentFilesRelationManager). No header or
 * row actions defined, same as that sibling — this tab is a pure view
 * surface for every viewer regardless of role, not gated by permission.
 */
class BoxesInsideRelationManager extends RelationManager
{
    protected static string $relationship = 'boxes';

    protected static ?string $title = 'Boxes Inside';

    public function table(Table $table): Table
    {
        /** @var Location $location */
        $location = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('box_barcode')->label('Barcode')->sortable()->searchable(),
                TextColumn::make('remarks')->label('Label')->placeholder('—'),
                TextColumn::make('placement')
                    ->label('Exact Placement')
                    ->state(fn (): string => 'Directly on '.($location->locationType?->type_name ?? 'Location'))
                    ->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('files_count')->label('Files')->counts('files'),
            ])
            ->recordUrl(fn (Box $record): string => BoxResource::getUrl('view', ['record' => $record]));
    }
}
