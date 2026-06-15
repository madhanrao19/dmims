<?php

namespace App\Filament\Resources\BillingRecordResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_no')->searchable(),
                TextColumn::make('amount')->money('myr')->sortable(),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('reference_no')->searchable(),
                TextColumn::make('recorded_by')->label('Recorded by'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            // Payments are recorded via the invoice "Record Payment" action so
            // invoice payment status stays in sync; this view is read-only.
            ->defaultSort('created_at', 'desc');
    }
}
