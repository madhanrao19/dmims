<?php

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\BoxResource;
use App\Filament\Resources\Pages\Concerns\HasAuditLogTab;
use App\Models\Box;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Box detail tab: record changes and file linking history for compliance.
 */
class AuditLog extends Page implements HasTable
{
    use HasAuditLogTab;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BoxResource::class;

    protected static ?string $navigationLabel = 'Box Audit Log';

    protected static ?string $title = 'Box Audit Log';

    public static function canAccess(array $parameters = []): bool
    {
        // Security & Access Control Matrix §14: audit logs are restricted
        // to SA/Management/Company Admin — being able to view the box
        // itself (Supervisor/Stock/Document/Viewer all can) is not enough.
        return BoxResource::can('view', $parameters['record'] ?? null)
            && AuditLogResource::can('view', $parameters['record'] ?? null);
    }

    protected function auditableType(): string
    {
        return Box::class;
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
