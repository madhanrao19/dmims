<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductLocationStockResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\ProductLocationStock;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductLocationStockResource extends BaseResource
{
    protected static ?string $model = ProductLocationStock::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Stock Inventory';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'product_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'location_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('quantity_on_hand')->numeric()->default(0),
                Forms\Components\TextInput::make('reserved_quantity')->numeric()->default(0),
                Forms\Components\TextInput::make('available_quantity')->numeric()->default(0),
                Forms\Components\DateTimePicker::make('last_movement_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.product_name')->label('Product')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('location.location_name')->label('Location')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('quantity_on_hand')->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')->sortable(),
                Tables\Columns\TextColumn::make('last_movement_at')->dateTime()->sortable(),
            ])
            ->defaultSort('last_movement_at', 'desc');
    }

    public static function getPages(): array
    {
        // List-only: rows here are computed and maintained exclusively by
        // StockMovementObserver::applyMovement() (firstOrCreate + quantity
        // math driven by real movements). A manual create/edit form lets a
        // user overwrite on-hand/reserved/available quantities directly,
        // desyncing this table from the movement history that's supposed to
        // be its source of truth, with no audit trail of the change.
        return [
            'index' => Pages\ListProductLocationStocks::route('/'),
        ];
    }
}

namespace App\Filament\Resources\ProductLocationStockResource\Pages;

use App\Filament\Resources\ProductLocationStockResource;
use Filament\Resources\Pages\ListRecords;

class ListProductLocationStocks extends ListRecords
{
    protected static string $resource = ProductLocationStockResource::class;
}
