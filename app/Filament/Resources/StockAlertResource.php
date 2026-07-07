<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAlertResource\Pages;
use App\Models\StockAlert;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockAlertResource extends BaseResource
{
    protected static ?string $model = StockAlert::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\TextInput::make('product_id')->numeric()->required(),
                Forms\Components\TextInput::make('location_id')->numeric()->required(),
                Forms\Components\Select::make('alert_type')
                    ->options([
                        'low_stock' => 'Low Stock',
                        'out_of_stock' => 'Out of Stock',
                        'overstock' => 'Overstock',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('threshold_quantity')->numeric()->required(),
                Forms\Components\TextInput::make('current_quantity')->numeric()->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'acknowledged' => 'Acknowledged',
                        'closed' => 'Closed',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product_id')->sortable(),
                Tables\Columns\TextColumn::make('location_id')->sortable(),
                Tables\Columns\TextColumn::make('alert_type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('threshold_quantity')->sortable(),
                Tables\Columns\TextColumn::make('current_quantity')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'acknowledged' => 'Acknowledged',
                        'closed' => 'Closed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAlerts::route('/'),
            'create' => Pages\CreateStockAlert::route('/create'),
            'edit' => Pages\EditStockAlert::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\StockAlertResource\Pages;

use App\Filament\Resources\StockAlertResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;
}

class CreateStockAlert extends CreateRecord
{
    protected static string $resource = StockAlertResource::class;
}

class EditStockAlert extends EditRecord
{
    protected static string $resource = StockAlertResource::class;
}
