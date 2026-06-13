<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends BaseResource
{
    protected static ?string $model = AuditLog::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'view reports';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user_id')->label('User ID')->sortable(),
                Tables\Columns\TextColumn::make('module')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('action')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->options(AuditLog::query()->distinct()->pluck('module', 'module')->toArray()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
