<?php

namespace App\Filament\Resources\BoxResource\RelationManagers;

use App\Filament\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Box;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Box View tab: record changes and file linking history for compliance.
 * One row per create/update/delete event recorded by
 * App\Models\Concerns\Auditable — Field Name/Old Value/New Value each list
 * every changed field on its own line within that one row (not one table
 * row per field), since old_values/new_values store one JSON diff per
 * event, not one row per field.
 */
class AuditLogRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Box Audit Log';

    /**
     * Security & Access Control Matrix §14: audit logs are restricted to
     * SA/Management/Company Admin — being able to view the box itself
     * (Supervisor/Stock/Document/Viewer all can) is not enough.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AuditLogResource::can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        /** @var Box $box */
        $box = $this->getOwnerRecord();

        return $table
            // AuditLog has no BelongsToCustomer scope of its own (it's a
            // platform-wide table, scoped only via BaseResource elsewhere),
            // so this filters explicitly rather than relying solely on
            // auditable_id having come from an already-scoped parent record.
            ->query($box->auditLogs()->getQuery()->where('customer_id', $box->customer_id))
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
