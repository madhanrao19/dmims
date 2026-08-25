<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\LocationResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class LocationResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Location::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    // Platform Customer 360 Design Review, item 10 (extended 25 Aug 2026):
    // platform users reach this via Customer 360's Locations tab instead of
    // a standalone top-level entry that mixed every customer's locations
    // into one list. Tenant users are unaffected by this flag — they keep
    // their own existing top-level "Locations" nav (same as Categories/
    // Products/Stock Movements), which was already correctly scoped to
    // their own company via BelongsToCustomer.
    protected static bool $consolidatedViaCustomer360 = true;

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
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required()
                    // BelongsToCustomer forces this to the tenant's own
                    // company regardless of what's submitted, so showing it
                    // to a tenant is only ever a confusing single-option
                    // picker — same precedent as BillingRecordResource's
                    // own customer_id field.
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'location_name')
                    ->searchable(),
                Forms\Components\Select::make('location_type_id')
                    ->relationship('locationType', 'type_name')
                    ->searchable(),
                Forms\Components\TextInput::make('location_code')->required()->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This location code is already in use for the selected customer.']),
                Forms\Components\TextInput::make('location_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('barcode')->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This barcode is already in use for the selected customer.']),
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
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;

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
