<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerModuleResource\Pages;
use App\Models\CustomerModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerModuleResource extends BaseResource
{
    protected static ?string $model = CustomerModule::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage subscriptions';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('module_id')
                    ->relationship('module', 'module_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),
                Forms\Components\DatePicker::make('enabled_at'),
                Forms\Components\DatePicker::make('disabled_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('module.module_name')->label('Module')->sortable()->searchable(),
                Tables\Columns\BooleanColumn::make('is_enabled')->label('Enabled'),
                Tables\Columns\TextColumn::make('enabled_at')->date(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerModules::route('/'),
            'create' => Pages\CreateCustomerModule::route('/create'),
            'edit' => Pages\EditCustomerModule::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CustomerModuleResource\Pages;

use App\Filament\Resources\CustomerModuleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListCustomerModules extends ListRecords
{
    protected static string $resource = CustomerModuleResource::class;
}

class CreateCustomerModule extends CreateRecord
{
    protected static string $resource = CustomerModuleResource::class;
}

class EditCustomerModule extends EditRecord
{
    protected static string $resource = CustomerModuleResource::class;
}
