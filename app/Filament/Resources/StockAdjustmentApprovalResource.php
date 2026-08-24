<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentApprovalResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\StockAdjustmentApproval;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockAdjustmentApprovalResource extends BaseResource
{
    protected static ?string $model = StockAdjustmentApproval::class;

    protected static bool $applyCustomerScope = true;

    // Business Rules §10: stock adjustment approvals are a Stock Inventory
    // module feature, same gate as StockMovementResource/ProductResource.
    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static ?string $permission = 'manage inventory';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
        // List-only: no other code in the app reads or writes this table, so
        // create/edit only let a user fabricate "approved" records that are
        // never actually checked before a stock adjustment applies — pure
        // audit theater. Wiring real approval enforcement into
        // StockMovementService::adjust() is an undocumented business-rule
        // change, not something to add unprompted; this closes the
        // fabrication hole without inventing that workflow.
        return [
            'index' => Pages\ListStockAdjustmentApprovals::route('/'),
        ];
    }
}

namespace App\Filament\Resources\StockAdjustmentApprovalResource\Pages;

use App\Filament\Resources\StockAdjustmentApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustmentApprovals extends ListRecords
{
    protected static string $resource = StockAdjustmentApprovalResource::class;
}
