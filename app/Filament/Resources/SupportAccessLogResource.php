<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportAccessLogResource\Pages;
use App\Models\SupportAccessLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class SupportAccessLogResource extends BaseResource
{
    protected static ?string $model = SupportAccessLog::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage settings';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('support_user_id')->numeric()->required(),
                Forms\Components\TextInput::make('target_user_id')->numeric()->required(),
                Forms\Components\Textarea::make('reason')->required()->maxLength(65535),
                Forms\Components\DateTimePicker::make('started_at')->required(),
                Forms\Components\DateTimePicker::make('ended_at'),
                Forms\Components\TextInput::make('ip_address')->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('support_user_id')->sortable(),
                Tables\Columns\TextColumn::make('target_user_id')->sortable(),
                Tables\Columns\TextColumn::make('reason')->limit(50),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ip_address')->sortable()->searchable(),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportAccessLogs::route('/'),
            'create' => Pages\CreateSupportAccessLog::route('/create'),
            'edit' => Pages\EditSupportAccessLog::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\SupportAccessLogResource\Pages;

use App\Filament\Resources\SupportAccessLogResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListSupportAccessLogs extends ListRecords
{
    protected static string $resource = SupportAccessLogResource::class;
}

class CreateSupportAccessLog extends CreateRecord
{
    protected static string $resource = SupportAccessLogResource::class;
}

class EditSupportAccessLog extends EditRecord
{
    protected static string $resource = SupportAccessLogResource::class;
}
