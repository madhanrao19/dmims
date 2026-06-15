<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends BaseResource
{
    protected static ?string $model = StockMovement::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Stock Inventory';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('movement_no')->required()->maxLength(100),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'product_name')
                    ->searchable(),
                Forms\Components\Select::make('from_location_id')
                    ->relationship('fromLocation', 'location_name')
                    ->searchable(),
                Forms\Components\Select::make('to_location_id')
                    ->relationship('toLocation', 'location_name')
                    ->searchable(),
                Forms\Components\TextInput::make('quantity')->numeric()->required(),
                Forms\Components\Select::make('movement_type')
                    ->options([
                        'opening_balance' => 'Opening Balance',
                        'stock_in' => 'Receive In',
                        'stock_out' => 'Stock Out',
                        'transfer' => 'Transfer',
                        'adjustment' => 'Adjustment',
                        'return' => 'Return',
                        'disposal' => 'Disposal',
                    ])
                    ->required(),
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
            ])
            ->headerActions([
                Action::make('receiveIn')
                    ->label('Receive In')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->schema([
                        static::productSelect(),
                        Forms\Components\Select::make('to_location_id')->label('To location')->options(static::locationOptions())->searchable()->required(),
                        static::quantityInput(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (array $data): void {
                        app(StockMovementService::class)->receiveIn((int) $data['product_id'], (int) $data['to_location_id'], (float) $data['quantity'], $data);
                        Notification::make()->title('Stock received')->success()->send();
                    }),
                Action::make('stockOut')
                    ->label('Stock Out')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->schema([
                        static::productSelect(),
                        Forms\Components\Select::make('from_location_id')->label('From location')->options(static::locationOptions())->searchable()->required(),
                        static::quantityInput(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (array $data): void {
                        app(StockMovementService::class)->stockOut((int) $data['product_id'], (int) $data['from_location_id'], (float) $data['quantity'], $data);
                        Notification::make()->title('Stock removed')->success()->send();
                    }),
                Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        static::productSelect(),
                        Forms\Components\Select::make('from_location_id')->label('From location')->options(static::locationOptions())->searchable()->required(),
                        Forms\Components\Select::make('to_location_id')->label('To location')->options(static::locationOptions())->searchable()->required()->different('from_location_id'),
                        static::quantityInput(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (array $data): void {
                        app(StockMovementService::class)->transfer((int) $data['product_id'], (int) $data['from_location_id'], (int) $data['to_location_id'], (float) $data['quantity'], $data);
                        Notification::make()->title('Stock transferred')->success()->send();
                    }),
                Action::make('adjust')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->schema([
                        static::productSelect(),
                        Forms\Components\Select::make('location_id')->label('Location')->options(static::locationOptions())->searchable()->required(),
                        Forms\Components\TextInput::make('delta')->label('Adjustment (+/-)')->numeric()->required()
                            ->helperText('Positive adds stock, negative removes it.'),
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data): void {
                        app(StockMovementService::class)->adjust((int) $data['product_id'], (int) $data['location_id'], (float) $data['delta'], $data);
                        Notification::make()->title('Stock adjusted')->success()->send();
                    }),
            ])
            ->defaultSort('performed_at', 'desc');
    }

    protected static function productSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('product_id')
            ->label('Product')
            ->options(fn () => Product::query()->pluck('product_name', 'id')->all())
            ->searchable()
            ->required();
    }

    /** @return array<int, string> */
    protected static function locationOptions(): array
    {
        return Location::query()->pluck('location_name', 'id')->all();
    }

    protected static function quantityInput(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required();
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
