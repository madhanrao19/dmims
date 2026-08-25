<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's audit trail, embedding
 * AuditLogResource's own table constrained to the selected customer.
 *
 * This is additive alongside AuditLogResource's existing platform-wide
 * "Platform Audit Logs" top-level nav item (cross-customer view) — the
 * approved-target doc's "no longer needs separate primary nav" list names
 * Users/Modules/Subscriptions/Licenses/Billing/Payments but explicitly
 * keeps "Platform Audit Logs" under platform-wide master administration,
 * so that top-level entry is NOT hidden by this feature.
 */
class AuditLogs extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?string $title = 'Customer Audit Logs';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return AuditLogResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->customerScopedResourceTable($table);
    }
}
