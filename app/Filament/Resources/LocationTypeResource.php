<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationTypeResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\LocationType;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LocationTypeResource extends BaseResource
{
    protected static ?string $model = LocationType::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    // Security review follow-up (CONFORMANCE_GAP_ANALYSIS §10a H3): the
    // location_types table has no customer_id column at all — it is
    // structurally platform-wide reference data, the same shape as
    // ModuleResource, not tenant-owned. Any tenant holding `manage
    // inventory` (Company Admin, Supervisor, Stock Inventory User) could
    // previously rename/delete a location type other tenants' Location
    // rows reference. Locking this to platform-only does not affect a
    // tenant's ability to *select* an existing location type when creating
    // a Location (LocationResource's relationship() select queries the
    // model directly, not through this resource) — only the admin CRUD
    // screen for the shared catalogue itself.
    protected static bool $platformOnly = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('type_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('type_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocationTypes::route('/'),
            'create' => Pages\CreateLocationType::route('/create'),
            'edit' => Pages\EditLocationType::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\LocationTypeResource\Pages;

use App\Filament\Resources\LocationTypeResource;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;

class ListLocationTypes extends ListRecords
{
    protected static string $resource = LocationTypeResource::class;
}

class CreateLocationType extends CreateRecord
{
    protected static string $resource = LocationTypeResource::class;
}

class EditLocationType extends EditRecord
{
    protected static string $resource = LocationTypeResource::class;
}
