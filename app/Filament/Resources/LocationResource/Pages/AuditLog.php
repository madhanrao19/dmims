<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\Pages\Concerns\HasAuditLogTab;
use App\Models\Location;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Location detail tab: record changes for this shelf/rack/room, matching
 * the original demo script's "create a shelf via scan, review its audit
 * history" step.
 */
class AuditLog extends Page implements HasTable
{
    use HasAuditLogTab;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = LocationResource::class;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $title = 'Location Audit Log';

    public static function canAccess(array $parameters = []): bool
    {
        // Security & Access Control Matrix §14: audit logs are restricted
        // to SA/Management/Company Admin — being able to view the location
        // itself is not enough.
        return LocationResource::can('view', $parameters['record'] ?? null)
            && AuditLogResource::can('view', $parameters['record'] ?? null);
    }

    protected function auditableType(): string
    {
        return Location::class;
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
