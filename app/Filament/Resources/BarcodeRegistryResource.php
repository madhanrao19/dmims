<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarcodeRegistryResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\BarcodeRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class BarcodeRegistryResource extends BaseResource
{
    protected static ?string $model = BarcodeRegistry::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Shared Services';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('barcode')->required()->maxLength(150),
                Forms\Components\Select::make('barcode_type')
                    ->options([
                        'product' => 'Product',
                        'location' => 'Location',
                        'box' => 'Box',
                        'document_file' => 'Document File',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('reference_table')->required()->maxLength(100),
                Forms\Components\TextInput::make('reference_id')->numeric()->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'retired' => 'Retired',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('barcode_type')->sortable(),
                Tables\Columns\TextColumn::make('reference_table')->sortable(),
                Tables\Columns\TextColumn::make('reference_id')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('last_scanned_at')->dateTime()->sortable(),
            ])
            ->defaultSort('barcode');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBarcodeRegistries::route('/'),
            'create' => Pages\CreateBarcodeRegistry::route('/create'),
            'edit' => Pages\EditBarcodeRegistry::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\BarcodeRegistryResource\Pages;

use App\Filament\Resources\BarcodeRegistryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBarcodeRegistries extends ListRecords
{
    protected static string $resource = BarcodeRegistryResource::class;
}

class CreateBarcodeRegistry extends CreateRecord
{
    protected static string $resource = BarcodeRegistryResource::class;
}

class EditBarcodeRegistry extends EditRecord
{
    protected static string $resource = BarcodeRegistryResource::class;
}
