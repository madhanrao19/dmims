<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingRecordResource\Pages;
use App\Filament\Resources\BillingRecordResource\RelationManagers\PaymentsRelationManager;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\BillingRecord;
use App\Services\BillingService;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BillingRecordResource extends BaseResource
{
    protected static ?string $model = BillingRecord::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':billing_view'];

    protected static ?string $permission = 'manage billing';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $modelLabel = 'Invoice';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'company_name')
                ->searchable()
                ->required()
                ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
            Forms\Components\TextInput::make('invoice_no')
                ->disabled()->dehydrated(false)->visibleOn('edit'),
            Forms\Components\DatePicker::make('invoice_date')->required()->default(now()),
            Forms\Components\DatePicker::make('due_date'),
            Forms\Components\TextInput::make('amount')
                ->numeric()->required()->minValue(0)->default(0)->prefix('RM')->live(onBlur: true),
            Forms\Components\TextInput::make('tax_amount')
                ->numeric()->minValue(0)->default(0)->prefix('RM')->live(onBlur: true),
            Forms\Components\Placeholder::make('total_preview')
                ->label('Total')
                ->content(fn (Get $get): string => 'RM '.number_format((float) $get('amount') + (float) $get('tax_amount'), 2)),
            Forms\Components\Select::make('billing_status')
                ->options(['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Cancelled'])
                ->default('draft')->required(),
            Forms\Components\Textarea::make('notes')->maxLength(65535)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->money('myr')->sortable(),
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->money('myr')
                    ->state(fn (BillingRecord $record): float => $record->outstandingAmount()),
                Tables\Columns\TextColumn::make('billing_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success', 'draft' => 'gray', 'cancelled' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger', default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('billing_status')
                    ->options(['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Cancelled']),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid']),
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (BillingRecord $record): bool => $record->billing_status !== 'cancelled' && $record->payment_status !== 'paid')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()->required()->minValue(0.01)->prefix('RM')
                            ->default(fn (BillingRecord $record): float => $record->outstandingAmount()),
                        Forms\Components\Select::make('payment_method')
                            ->options(['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'other' => 'Other'])
                            ->default('bank_transfer')->required(),
                        Forms\Components\DatePicker::make('payment_date')->required()->default(now()),
                        Forms\Components\TextInput::make('reference_no')->maxLength(255),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (BillingRecord $record, array $data): void {
                        app(PaymentService::class)->recordPayment($record, $data);
                        Notification::make()->title('Payment recorded')->success()->send();
                    }),
                Action::make('issue')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (BillingRecord $record): bool => $record->billing_status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (BillingRecord $record): void {
                        app(BillingService::class)->issue($record);
                        Notification::make()->title('Invoice issued')->success()->send();
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BillingRecord $record): bool => $record->billing_status !== 'cancelled')
                    ->requiresConfirmation()
                    ->action(function (BillingRecord $record): void {
                        app(BillingService::class)->cancel($record);
                        Notification::make()->title('Invoice cancelled')->warning()->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingRecords::route('/'),
            'create' => Pages\CreateBillingRecord::route('/create'),
            'view' => Pages\ViewBillingRecord::route('/{record}'),
            'edit' => Pages\EditBillingRecord::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\BillingRecordResource\Pages;

use App\Filament\Resources\BillingRecordResource;
use App\Services\BillingService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ListBillingRecords extends ListRecords
{
    protected static string $resource = BillingRecordResource::class;
}

class CreateBillingRecord extends CreateRecord
{
    protected static string $resource = BillingRecordResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BillingService::class)->createInvoice($data);
    }
}

class ViewBillingRecord extends ViewRecord
{
    protected static string $resource = BillingRecordResource::class;
}

class EditBillingRecord extends EditRecord
{
    protected static string $resource = BillingRecordResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // total_amount is always derived; never trust a submitted value.
        $data['total_amount'] = round((float) ($data['amount'] ?? 0) + (float) ($data['tax_amount'] ?? 0), 2);

        return $data;
    }

    protected function afterSave(): void
    {
        app(BillingService::class)->recalculatePaymentStatus($this->record);
    }
}
