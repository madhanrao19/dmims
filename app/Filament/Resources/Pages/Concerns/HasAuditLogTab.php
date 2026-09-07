<?php

namespace App\Filament\Resources\Pages\Concerns;

use App\Models\AuditLog;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Shared "Audit Log" detail tab (Box and Document File both use it): one row
 * per create/update/delete event recorded by App\Models\Concerns\Auditable,
 * with a single "Changes" column summarizing old_values/new_values as
 * "field: old -> new" lines. audit_logs stores one JSON blob per event, not
 * one row per field, so this is a formatted summary rather than a literal
 * one-row-per-field table (see plan decision) — only fields that were
 * actually recorded are shown; nothing is backfilled or guessed.
 */
trait HasAuditLogTab
{
    abstract protected function auditableType(): string;

    abstract protected function auditableId(): int|string;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function auditLogTable(Table $table): Table
    {
        return $table
            // AuditLog has no BelongsToCustomer scope of its own (it's a
            // platform-wide table, scoped only via BaseResource elsewhere),
            // so this filters explicitly rather than relying solely on
            // auditable_id having come from an already-scoped parent record.
            ->query(AuditLog::query()
                ->where('auditable_type', $this->auditableType())
                ->where('auditable_id', $this->auditableId())
                ->where('customer_id', $this->getRecord()->getAttribute('customer_id')))
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
