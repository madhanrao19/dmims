<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends BaseResource
{
    protected static ?string $model = Setting::class;

    protected static bool $applyCustomerScope = true;

    // Security & Access Control Matrix §3.3: explicitly approved global
    // reference settings (customer_id = null) remain visible alongside the
    // tenant's own overrides. No customer role currently holds `manage
    // settings`/`view settings`, so this resource is platform-only in
    // practice regardless of this flag.
    protected static bool $includeGlobalCustomerDefaults = true;

    protected static ?string $permission = 'manage settings';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('setting_group')->required()->maxLength(100),
                Forms\Components\TextInput::make('setting_key')->required()->maxLength(100),
                Forms\Components\Textarea::make('setting_value')->rows(3)->required(),
                Forms\Components\Select::make('setting_type')
                    ->options([
                        'string' => 'String',
                        'json' => 'JSON',
                        'boolean' => 'Boolean',
                        'number' => 'Number',
                    ])
                    ->default('string')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('setting_group')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('setting_key')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('setting_type')->sortable(),
                Tables\Columns\TextColumn::make('setting_value')->limit(60),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\SettingResource;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;
}

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;
}

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;
}
