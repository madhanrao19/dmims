<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's storage locations, embedding
 * LocationResource's own table constrained to the selected customer.
 */
class Locations extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $title = 'Customer Locations';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return LocationResource::class;
    }

    public function table(Table $table): Table
    {
        $customerId = $this->getRecord()->getKey();

        // "Add Location" and "Location Chain Builder" used to be two
        // separate buttons; createChainAction() (labeled "Add Location")
        // now serves as the single create entry point — a chain of one row
        // with no starting parent behaves exactly like the old plain
        // single-location create, and a deeper chain builds a full
        // hierarchy in one submit.
        return $this->customerScopedResourceTable($table)
            ->headerActions([
                LocationResource::createChainAction($customerId),
                LocationResource::batchGenerateAction($customerId),
            ]);
    }
}
