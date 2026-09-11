<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AuditLogResource extends BaseResource
{
    protected static ?string $model = AuditLog::class;

    protected static bool $applyCustomerScope = true;

    // Security & Access Control Matrix §14: only SA (all), Management
    // (summarized), and Company Admin (own company) may view audit logs —
    // not Supervisor/Stock/Document/Viewer, who all hold the generic
    // "view reports" permission this used to be gated on.
    protected static ?string $permission = 'view audit logs';

    // Security & Access Control Matrix §5: customer users reach this via
    // the My Company > Audit Logs tab instead of a standalone nav entry.
    protected static bool $customerFacingViaMyCompany = true;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Date & Time')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Performed By')->sortable()->placeholder('System'),
                Tables\Columns\TextColumn::make('module')->sortable()->searchable()->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                Tables\Columns\TextColumn::make('action')->badge()->searchable()->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                Tables\Columns\TextColumn::make('changes')
                    ->label('Changes')
                    ->wrap()
                    ->toggleable()
                    ->state(fn (AuditLog $record): string => $record->changesSummary()),
            ])
            ->filters([
                // Security review finding (24 August 2026): AuditLog::query()
                // is unscoped (no BelongsToCustomer — see §3.2's note that
                // audit logs are scoped via BaseResource, not the model), so
                // this must go through static::getEloquentQuery() explicitly
                // or the filter's own option list leaks which modules every
                // other tenant on the platform uses.
                Tables\Filters\SelectFilter::make('module')
                    ->options(static::getEloquentQuery()->distinct()->pluck('module', 'module')
                        ->map(fn (string $module): string => Str::headline($module))
                        ->toArray()),
            ])
            ->defaultSort('created_at', 'desc');
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
