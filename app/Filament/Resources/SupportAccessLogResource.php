<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportAccessLogResource\Pages;
use App\Models\SupportAccessLog;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SupportAccessLogResource extends BaseResource
{
    protected static ?string $model = SupportAccessLog::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('support_user_id')->numeric()->required()->exists('users', 'id'),
                Forms\Components\TextInput::make('target_user_id')->numeric()->required()->exists('users', 'id'),
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
        // No edit page: this table is nowhere else in the codebase written
        // programmatically, so create is the only way support access gets
        // logged at all — but editing an existing entry (backdating
        // started_at/ended_at, rewriting reason) would let the exact staff
        // being tracked erase their own trail. List/create only.
        return [
            'index' => Pages\ListSupportAccessLogs::route('/'),
            'create' => Pages\CreateSupportAccessLog::route('/create'),
        ];
    }
}

namespace App\Filament\Resources\SupportAccessLogResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\SupportAccessLogResource;
use Filament\Resources\Pages\CreateRecord;

class ListSupportAccessLogs extends ListRecords
{
    protected static string $resource = SupportAccessLogResource::class;
}

class CreateSupportAccessLog extends CreateRecord
{
    protected static string $resource = SupportAccessLogResource::class;
}
