<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\BillingRecordResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Models\BillingRecord;
use App\Services\BillingService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's billing records, embedding
 * BillingRecordResource's own table constrained to the selected customer.
 * Payments themselves stay reachable via BillingRecordResource's own
 * ViewBillingRecord page (its existing PaymentsRelationManager) when a row
 * is opened — no separate Payments resource exists to embed.
 */
class BillingAndPayments extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Billing & Payments';

    protected static ?string $title = 'Customer Billing & Payments';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return BillingRecordResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->customerScopedResourceTable($table)
            ->headerActions([
                // BillingRecordResource's own create page (CreateBillingRecord)
                // routes through BillingService::createInvoice() to generate
                // invoice_no/total_amount rather than a plain model create —
                // reuse that same call here instead of bypassing it.
                $this->customerScopedCreateAction(
                    'Add Billing Record',
                    using: fn (array $data): BillingRecord => app(BillingService::class)->createInvoice($data),
                ),
            ]);
    }
}
