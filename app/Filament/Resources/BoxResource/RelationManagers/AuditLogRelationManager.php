<?php

namespace App\Filament\Resources\BoxResource\RelationManagers;

use App\Filament\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Box;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Box View tab: record changes and file linking history for compliance.
 * One row per create/update/delete event recorded by
 * App\Models\Concerns\Auditable, with a single "Changes" column summarizing
 * old_values/new_values — audit_logs stores one JSON blob per event, not
 * one row per field.
 */
class AuditLogRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'System Activity Log';

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
                TextColumn::make('action')->badge(),
                TextColumn::make('user.name')->label('Performed By')->placeholder('System'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->wrap()
                    ->state(function (AuditLog $record): string {
                        $old = $record->old_values ?? [];
                        $new = $record->new_values ?? [];
                        $fields = array_unique([...array_keys($old), ...array_keys($new)]);

                        if ($fields === []) {
                            return '—';
                        }

                        return collect($fields)
                            ->map(fn (string $field): string => sprintf(
                                '%s: %s → %s',
                                $field,
                                $old[$field] ?? '—',
                                $new[$field] ?? '—',
                            ))
                            ->implode("\n");
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
