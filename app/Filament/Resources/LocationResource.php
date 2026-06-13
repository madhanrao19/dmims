<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends BaseResource
{
    protected static ?string $model = Location::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\BelongsToSelect::make('parent_id')
                    ->relationship('parent', 'location_name')
                    ->searchable(),
                Forms\Components\BelongsToSelect::make('location_type_id')
                    ->relationship('locationType', 'type_name')
                    ->searchable(),
                Forms\Components\TextInput::make('location_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('location_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('barcode')->maxLength(100),
                Forms\Components\Toggle::make('can_store_stock')->default(true),
                Forms\Components\Toggle::make('can_store_boxes')->default(true),
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
                Tables\Columns\TextColumn::make('status')->sortable(),
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
