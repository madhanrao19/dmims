<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentApprovalResource\Pages;
use App\Models\StockAdjustmentApproval;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockAdjustmentApprovalResource extends BaseResource
{
    protected static ?string $model = StockAdjustmentApproval::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    /**
     * Segregation of duties: the user who raised an adjustment may not be the
     * one who approves or rejects it. Compared by the authenticated user's name,
     * which is also what {@see stampApprover()} records — never trusted input.
     */
    public static function isSelfApproval(array $data, StockAdjustmentApproval $record): bool
    {
        return in_array($data['approval_status'] ?? null, ['approved', 'rejected'], true)
            && $record->requested_by !== null
            && auth()->user()?->name === $record->requested_by;
    }

    /**
     * Record the approver server-side from the authenticated user when the
     * request is decided; clear it while the request is still pending.
     */
    public static function stampApprover(array $data): array
    {
        if (in_array($data['approval_status'] ?? null, ['approved', 'rejected'], true)) {
            $data['approved_by'] = auth()->user()?->name;
            $data['approved_at'] = now();
        } else {
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\TextInput::make('stock_movement_id')->numeric()->required(),
                Forms\Components\Select::make('approval_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                // Requester and approver identity are recorded server-side from
                // the authenticated user, never from form input — this is the
                // segregation-of-duties control. Shown read-only for context.
                Forms\Components\TextInput::make('requested_by')->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('approved_by')->disabled()->dehydrated(false),
                Forms\Components\DateTimePicker::make('approved_at')->disabled()->dehydrated(false),
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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;

class ListStockAdjustmentApprovals extends ListRecords
{
    protected static string $resource = StockAdjustmentApprovalResource::class;
}

class CreateStockAdjustmentApproval extends CreateRecord
{
    protected static string $resource = StockAdjustmentApprovalResource::class;

    /**
     * The creator is the requester, and a new request is always pending — an
     * adjustment can never be created pre-approved (that would let one person
     * both raise and approve it). Approval happens on edit, by someone else.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->user()?->name;
        $data['approval_status'] = 'pending';
        $data['approved_by'] = null;
        $data['approved_at'] = null;

        return $data;
    }
}

class EditStockAdjustmentApproval extends EditRecord
{
    protected static string $resource = StockAdjustmentApprovalResource::class;

    /**
     * Stamp the approver server-side and enforce segregation of duties: the
     * requester may not approve or reject their own adjustment.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (StockAdjustmentApprovalResource::isSelfApproval($data, $this->record)) {
            Notification::make()
                ->danger()
                ->title('You cannot approve or reject your own adjustment request.')
                ->persistent()
                ->send();

            throw new Halt;
        }

        return StockAdjustmentApprovalResource::stampApprover($data);
    }
}
