<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LicenseLogResource\Pages;
use App\Models\LicenseLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class LicenseLogResource extends BaseResource
{
    protected static ?string $model = LicenseLog::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage licensing';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('license_id')->numeric()->required(),
                Forms\Components\TextInput::make('action')->required()->maxLength(100),
                Forms\Components\Textarea::make('old_value')->rows(3),
                Forms\Components\Textarea::make('new_value')->rows(3),
                Forms\Components\Textarea::make('remarks')->rows(3),
                Forms\Components\TextInput::make('performed_by')->maxLength(100),
                Forms\Components\DateTimePicker::make('performed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('license_id')->sortable(),
                Tables\Columns\TextColumn::make('performed_by')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('performed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('performed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenseLogs::route('/'),
            'create' => Pages\CreateLicenseLog::route('/create'),
            'edit' => Pages\EditLicenseLog::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\LicenseLogResource\Pages;

use App\Filament\Resources\LicenseLogResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListLicenseLogs extends ListRecords
{
    protected static string $resource = LicenseLogResource::class;
}

class CreateLicenseLog extends CreateRecord
{
    protected static string $resource = LicenseLogResource::class;
}

class EditLicenseLog extends EditRecord
{
    protected static string $resource = LicenseLogResource::class;
}
