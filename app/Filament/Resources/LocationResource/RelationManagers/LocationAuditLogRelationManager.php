<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Filament\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Location;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Location View tab: record changes for this shelf/rack/room — same
 * inline-relation-manager-tab pattern and column layout as ViewBox's own
 * "Box Audit Log" (BoxResource\RelationManagers\AuditLogRelationManager),
 * replacing the previous separate sub-navigation "Audit Log" page (a
 * different URL/tab-strip mechanism than Box's).
 */
class LocationAuditLogRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Location Audit Log';

    /**
     * Security & Access Control Matrix §14: audit logs are restricted to
     * SA/Management/Company Admin — being able to view the location itself
     * is not enough.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AuditLogResource::can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        /** @var Location $location */
        $location = $this->getOwnerRecord();

        return $table
            // AuditLog has no BelongsToCustomer scope of its own (it's a
            // platform-wide table, scoped only via BaseResource elsewhere),
            // so this filters explicitly rather than relying solely on
            // auditable_id having come from an already-scoped parent record.
            ->query($location->auditLogs()->getQuery()->where('customer_id', $location->customer_id))
            ->columns([
                TextColumn::make('created_at')->label('Date & Time')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                TextColumn::make('user.name')->label('Performed By')->placeholder('System'),
                TextColumn::make('field_name')
                    ->label('Field Name')
                    ->listWithLineBreaks()
                    ->state(fn (AuditLog $record): array => $record->changesFieldNames()),
                TextColumn::make('old_value')
                    ->label('Old Value')
                    ->listWithLineBreaks()
                    ->state(fn (AuditLog $record): array => $record->changesOldValues()),
                TextColumn::make('new_value')
                    ->label('New Value')
                    ->listWithLineBreaks()
                    ->state(fn (AuditLog $record): array => $record->changesNewValues()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
