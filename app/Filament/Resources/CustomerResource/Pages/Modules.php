<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerModuleResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's enabled modules, embedding
 * CustomerModuleResource's own table constrained to the selected customer.
 */
class Modules extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Modules';

    protected static ?string $title = 'Customer Modules';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return CustomerModuleResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->customerScopedResourceTable($table)
            ->headerActions([$this->customerScopedCreateAction('Add Module')]);
    }
}
