<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

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

    protected static string|\BackedEnum|null $navigationIcon = null;

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
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user_id')->label('User ID')->sortable(),
                Tables\Columns\TextColumn::make('module')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('action')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                // Security review finding (24 August 2026): AuditLog::query()
                // is unscoped (no BelongsToCustomer — see §3.2's note that
                // audit logs are scoped via BaseResource, not the model), so
                // this must go through static::getEloquentQuery() explicitly
                // or the filter's own option list leaks which modules every
                // other tenant on the platform uses.
                Tables\Filters\SelectFilter::make('module')
                    ->options(static::getEloquentQuery()->distinct()->pluck('module', 'module')->toArray()),
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
