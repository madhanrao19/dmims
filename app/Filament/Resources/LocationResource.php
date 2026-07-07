<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\LocationResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Location::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['location_code', 'location_name', 'barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'location_name')
                    ->searchable(),
                Forms\Components\Select::make('location_type_id')
                    ->relationship('locationType', 'type_name')
                    ->searchable(),
                Forms\Components\TextInput::make('location_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('location_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('barcode')->maxLength(100),
                Forms\Components\Toggle::make('can_store_stock')->default(true),
                Forms\Components\Toggle::make('can_store_boxes')->default(true),
                Forms\Components\TextInput::make('box_capacity')->numeric()->helperText('Maximum number of boxes this shelf/rack can hold (optional).'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('location_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('locationType.type_name')->label('Type')->sortable(),
                Tables\Columns\TextColumn::make('parent.location_name')->label('Parent')->sortable(),
                Tables\Columns\TextColumn::make('box_capacity')
                    ->label('Box capacity')
                    ->state(fn (Location $record): string => $record->box_capacity
                        ? "{$record->boxes_used_count}/{$record->box_capacity} boxes ({$record->box_capacity_percent}%)"
                        : "{$record->boxes_used_count} boxes")
                    ->badge()
                    ->color(fn (Location $record): string => match (true) {
                        $record->box_capacity_percent === null => 'gray',
                        $record->box_capacity_percent >= 100 => 'danger',
                        $record->box_capacity_percent >= 80 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make(),
                static::barcodeAction(),
            ])
            ->defaultSort('location_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;
}

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;
}

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;
}
