<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerModuleResource\Pages;
use App\Models\CustomerModule;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CustomerModuleResource extends BaseResource
{
    protected static ?string $model = CustomerModule::class;

    protected static bool $applyCustomerScope = true;

    // Security & Access Control Matrix §5: customer users reach this via
    // the My Company > Enabled Modules tab instead of a standalone nav entry.
    protected static bool $customerFacingViaMyCompany = true;

    protected static ?string $permission = 'manage subscriptions';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('module_id')
                    ->relationship('module', 'module_name')
                    ->searchable()
                    ->required()
                    // customer_modules has a unique(customer_id, module_id)
                    // constraint the form never validated — submitting a
                    // duplicate combination crashed with a raw
                    // UniqueConstraintViolationException instead of a normal
                    // inline validation error.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages([
                        'unique' => 'Module access already exists for this customer.',
                    ]),
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
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;

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
