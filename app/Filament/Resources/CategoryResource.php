<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends BaseResource
{
    protected static ?string $model = Category::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Stock Inventory';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('category_code')->maxLength(100),
                Forms\Components\TextInput::make('category_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
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
                Tables\Columns\TextColumn::make('category_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('category_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;
}

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
}
