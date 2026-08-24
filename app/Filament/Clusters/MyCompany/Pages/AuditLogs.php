<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\AuditLogResource;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Security & Access Control Matrix §14: only a Company Admin (the sole
 * customer role holding `view audit logs`) sees this tab — enforced
 * automatically since canAccess() delegates to AuditLogResource::can().
 */
class AuditLogs extends Page implements HasTable
{
    use HasEmbeddedResourceTable;
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?string $title = 'Audit Logs';

    protected static function sourceResource(): string
    {
        return AuditLogResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->embeddedResourceTable($table);
    }
}
