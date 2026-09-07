<?php

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\Pages\Concerns\HasAuditLogTab;
use App\Models\DocumentFile;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Document File detail tab: data edits, status changes, and box
 * associations.
 */
class AuditLog extends Page implements HasTable
{
    use HasAuditLogTab;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = DocumentFileResource::class;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $title = 'Document Audit Log';

    public static function canAccess(array $parameters = []): bool
    {
        // Security & Access Control Matrix §14: audit logs are restricted
        // to SA/Management/Company Admin — being able to view the file
        // itself (Supervisor/Stock/Document/Viewer all can) is not enough.
        return DocumentFileResource::can('view', $parameters['record'] ?? null)
            && AuditLogResource::can('view', $parameters['record'] ?? null);
    }

    protected function auditableType(): string
    {
        return DocumentFile::class;
    }

    protected function auditableId(): int|string
    {
        return $this->getRecord()->getKey();
    }

    public function table(Table $table): Table
    {
        return $this->auditLogTable($table);
    }
}
