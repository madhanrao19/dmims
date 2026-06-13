<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends BaseResource
{
    protected static ?string $model = Notification::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage settings';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('user_id')->numeric()->required(),
                Forms\Components\TextInput::make('notification_type')->required()->maxLength(100),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Textarea::make('message')->required()->maxLength(65535),
                Forms\Components\Toggle::make('is_read'),
                Forms\Components\DateTimePicker::make('read_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('notification_type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user_id')->sortable(),
                Tables\Columns\IconColumn::make('is_read')->boolean()->label('Read'),
                Tables\Columns\TextColumn::make('read_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('notification_type')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'alert' => 'Alert',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;
}

class CreateNotification extends CreateRecord
{
    protected static string $resource = NotificationResource::class;
}

class EditNotification extends EditRecord
{
    protected static string $resource = NotificationResource::class;
}
