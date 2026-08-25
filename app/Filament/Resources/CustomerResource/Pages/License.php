<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Filament\Resources\LicenseResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's licenses, embedding
 * LicenseResource's own table constrained to the selected customer.
 *
 * LicenseResource::$platformOnly = true only blocks NON-platform users in
 * BaseResource::getEloquentQuery()/can() — Customer 360 is platform-user
 * only (see canAccess() below), so embedding it here is safe and is new
 * exposure not available anywhere else (My Company has a separate,
 * simplified LicenseStatus summary page instead of this resource).
 */
class License extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'License';

    protected static ?string $title = 'Customer License';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return LicenseResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->customerScopedResourceTable($table);
    }
}
