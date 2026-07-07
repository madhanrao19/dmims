<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationTypeResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\LocationType;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LocationTypeResource extends BaseResource
{
    protected static ?string $model = LocationType::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 2;

    /**
     * LocationType is global reference data (no customer_id) shared by every
     * tenant. Tenants keep read access via `manage/view inventory` so they can
     * assign location types, but only platform staff may create/edit/delete
     * them — otherwise one tenant could rename or delete types other tenants'
     * locations depend on.
     */
    public static function can(string|\UnitEnum $action, ?Model $record = null): bool
    {
        if (in_array($action, self::WRITE_ACTIONS, true) && ! auth()->user()?->is_platform_user) {
            return false;
        }

        return parent::can($action, $record);
    }

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
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

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
