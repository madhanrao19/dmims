<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends BaseResource
{
    protected static ?string $model = Product::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Stock Inventory';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('sku')->required()->maxLength(100),
                Forms\Components\TextInput::make('barcode')->maxLength(150),
                Forms\Components\TextInput::make('product_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\BelongsToSelect::make('category_id')
                    ->relationship('category', 'category_name')
                    ->searchable(),
                Forms\Components\BelongsToSelect::make('default_location_id')
                    ->relationship('defaultLocation', 'location_name')
                    ->searchable(),
                Forms\Components\TextInput::make('reorder_level')->numeric()->default(0),
                Forms\Components\TextInput::make('unit_cost')->numeric()->numericStep('0.01')->default(0),
                Forms\Components\TextInput::make('unit_price')->numeric()->numericStep('0.01')->default(0),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'discontinued' => 'Discontinued',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('category.category_name')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('defaultLocation.location_name')->label('Default Location')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ])
            ->defaultSort('product_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
}
