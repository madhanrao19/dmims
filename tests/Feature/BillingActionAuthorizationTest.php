<?php

namespace Tests\Feature;

use App\Filament\Resources\BillingRecordResource\Pages\ListBillingRecords;
use App\Models\BillingRecord;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression: recordPayment/issue/cancel on BillingRecordResource had no
 * ->authorize() at all — a Company Admin (who legitimately holds "view
 * billing" to see their own invoices, per Security & Access Control Matrix
 * §9) could still record a fabricated payment, issue a draft, or cancel any
 * invoice, all of which the matrix reserves for Datamation Super Admin only.
 * Found reviewing the Billing module.
 */
class BillingActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function invoice(Customer $customer): BillingRecord
    {
        return BillingRecord::create([
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-1',
            'invoice_date' => now()->toDateString(),
            'amount' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'billing_status' => 'draft',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_company_admin_cannot_record_payment_issue_or_cancel(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $module = Module::create(['module_code' => 'billing_view', 'module_name' => 'Billing View', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $admin = User::factory()->create(['is_platform_user' => false, 'customer_id' => $customer->id, 'status' => 'active']);
        $admin->assignRole('Company Admin');
        $this->actingAs($admin);

        $invoice = $this->invoice($customer);

        Livewire::test(ListBillingRecords::class)
            ->assertTableActionHidden('recordPayment', $invoice)
            ->assertTableActionHidden('issue', $invoice)
            ->assertTableActionHidden('cancel', $invoice);
    }

    public function test_super_admin_can_record_payment_issue_and_cancel(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $sa = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $sa->assignRole('Datamation Super Admin');
        $this->actingAs($sa);

        $invoice = $this->invoice($customer);

        Livewire::test(ListBillingRecords::class)
            ->assertTableActionVisible('recordPayment', $invoice)
            ->assertTableActionVisible('issue', $invoice)
            ->assertTableActionVisible('cancel', $invoice);
    }
}
