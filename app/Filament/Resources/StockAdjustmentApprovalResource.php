<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentApprovalResource\Pages;
use App\Models\StockAdjustmentApproval;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StockAdjustmentApprovalResource extends BaseResource
{
    protected static ?string $model = StockAdjustmentApproval::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('stock_movement_id')->numeric()->required(),
                Forms\Components\Select::make('approval_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('requested_by')->maxLength(100),
                Forms\Components\TextInput::make('approved_by')->maxLength(100),
                Forms\Components\DateTimePicker::make('approved_at'),
                Forms\Components\Textarea::make('remarks')->maxLength(65535),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('stock_movement_id')->sortable(),
                Tables\Columns\TextColumn::make('approval_status')->sortable(),
                Tables\Columns\TextColumn::make('requested_by')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('approved_by')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustmentApprovals::route('/'),
            'create' => Pages\CreateStockAdjustmentApproval::route('/create'),
            'edit' => Pages\EditStockAdjustmentApproval::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\StockAdjustmentApprovalResource\Pages;

use App\Filament\Resources\StockAdjustmentApprovalResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustmentApprovals extends ListRecords
{
    protected static string $resource = StockAdjustmentApprovalResource::class;
}

class CreateStockAdjustmentApproval extends CreateRecord
{
    protected static string $resource = StockAdjustmentApprovalResource::class;
}

class EditStockAdjustmentApproval extends EditRecord
{
    protected static string $resource = StockAdjustmentApprovalResource::class;
}
