<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends BaseResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $permission = 'manage customers';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('company_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('company_code')->required()->maxLength(50),
                Forms\Components\TextInput::make('contact_person')->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->maxLength(50),
                Forms\Components\Textarea::make('address')->rows(3),
                Forms\Components\Select::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'archived' => 'Archived',
                    ])
                    ->default('trial')
                    ->required(),
                Forms\Components\Textarea::make('notes')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('company_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('phone')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'archived' => 'Archived',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
