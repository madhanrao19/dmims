<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends BaseResource
{
    protected static ?string $model = StockMovement::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Stock Inventory';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('movement_no')->required()->maxLength(100),
                Forms\Components\BelongsToSelect::make('product_id')
                    ->relationship('product', 'product_name')
                    ->searchable(),
                Forms\Components\BelongsToSelect::make('from_location_id')
                    ->relationship('fromLocation', 'location_name')
                    ->searchable(),
                Forms\Components\BelongsToSelect::make('to_location_id')
                    ->relationship('toLocation', 'location_name')
                    ->searchable(),
                Forms\Components\TextInput::make('quantity')->numeric()->required(),
                Forms\Components\TextInput::make('movement_type')->required()->maxLength(100),
                Forms\Components\TextInput::make('reference_no')->maxLength(100),
                Forms\Components\Textarea::make('reason')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product.product_name')->label('Product')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('movement_type')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('performed_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;
}

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;
}

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;
}
