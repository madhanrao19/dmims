<?php

namespace App\Filament\Resources\DocumentFileResource\RelationManagers;

use App\Filament\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\DocumentFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Document File View tab: data edits, status changes, and box associations.
 * One row per create/update/delete event recorded by
 * App\Models\Concerns\Auditable. Mirrors BoxResource's own
 * AuditLogRelationManager exactly.
 */
class AuditLogRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'System Activity Log';

    /**
     * Security & Access Control Matrix §14: audit logs are restricted to
     * SA/Management/Company Admin — being able to view the file itself
     * (Supervisor/Stock/Document/Viewer all can) is not enough.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AuditLogResource::can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        /** @var DocumentFile $file */
        $file = $this->getOwnerRecord();

        return $table
            // AuditLog has no BelongsToCustomer scope of its own (it's a
            // platform-wide table, scoped only via BaseResource elsewhere),
            // so this filters explicitly rather than relying solely on
            // auditable_id having come from an already-scoped parent record.
            ->query($file->auditLogs()->getQuery()->where('customer_id', $file->customer_id))
            ->columns([
                TextColumn::make('created_at')->label('Date & Time')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->formatStateUsing(fn (string $state): string => Str::headline(Str::lower($state))),
                TextColumn::make('user.name')->label('Performed By')->placeholder('System'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->wrap()
                    ->state(fn (AuditLog $record): string => $record->changesSummary()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
