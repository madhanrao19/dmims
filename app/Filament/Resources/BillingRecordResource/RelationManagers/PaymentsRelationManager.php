<?php

namespace App\Filament\Resources\BillingRecordResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_no')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('myr')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->badge(),
                Tables\Columns\TextColumn::make('payment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('reference_no')->searchable(),
                Tables\Columns\TextColumn::make('recorded_by')->label('Recorded by'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            // Payments are recorded via the invoice "Record Payment" action so
            // invoice payment status stays in sync; this view is read-only.
            ->defaultSort('created_at', 'desc');
    }
}
