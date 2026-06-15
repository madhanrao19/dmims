<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LicenseResource\Pages;
use App\Models\License;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LicenseResource extends BaseResource
{
    protected static ?string $model = License::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage licensing';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('license_no')->required()->maxLength(100),
                Forms\Components\TextInput::make('deployment_mode')->default('DatamationOnPremHosted')->required()->maxLength(100),
                Forms\Components\TextInput::make('license_mode')->default('InternalSubscription')->required()->maxLength(100),
                Forms\Components\TextInput::make('installation_id')->maxLength(255),
                Forms\Components\TextInput::make('server_fingerprint')->maxLength(255),
                Forms\Components\DatePicker::make('valid_from')->required(),
                Forms\Components\DatePicker::make('valid_to')->required(),
                Forms\Components\TextInput::make('grace_period_days')->numeric()->default(0),
                Forms\Components\TextInput::make('max_users')->numeric(),
                Forms\Components\TextInput::make('max_products')->numeric(),
                Forms\Components\TextInput::make('max_document_files')->numeric(),
                Forms\Components\TextInput::make('max_boxes')->numeric(),
                Forms\Components\Textarea::make('enabled_modules')->helperText('Enter JSON array of enabled module codes.'),
                Forms\Components\Textarea::make('allowed_reports')->helperText('Enter JSON array of allowed reports.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired' => 'Expired',
                        'restricted' => 'Restricted',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('trial')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('license_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('deployment_mode')->sortable(),
                Tables\Columns\TextColumn::make('license_mode')->sortable(),
                Tables\Columns\TextColumn::make('valid_to')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ])
            ->defaultSort('valid_to', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenses::route('/'),
            'create' => Pages\CreateLicense::route('/create'),
            'edit' => Pages\EditLicense::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\LicenseResource\Pages;

use App\Filament\Resources\LicenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;
}

class CreateLicense extends CreateRecord
{
    protected static string $resource = LicenseResource::class;
}

class EditLicense extends EditRecord
{
    protected static string $resource = LicenseResource::class;
}
