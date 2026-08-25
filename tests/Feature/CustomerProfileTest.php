<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\BillingRecordResource;
use App\Filament\Resources\CustomerModuleResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\AuditLogs;
use App\Filament\Resources\CustomerResource\Pages\BillingAndPayments;
use App\Filament\Resources\CustomerResource\Pages\License as LicensePage;
use App\Filament\Resources\CustomerResource\Pages\Modules;
use App\Filament\Resources\CustomerResource\Pages\Subscription;
use App\Filament\Resources\CustomerResource\Pages\Users;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerSubscriptionResource;
use App\Filament\Resources\LicenseResource;
use App\Filament\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\License;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Platform Customer 360 (docs/CONFORMANCE_GAP_ANALYSIS.md, 25 Aug 2026
 * design review). Mirrors tests/Feature/MyCompanyClusterTest.php's
 * conventions for the platform-facing counterpart.
 */
class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    private function platformUser(): User
    {
        return User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
    }

    private function platformSuperAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $user->assignRole('Datamation Super Admin');

        return $user;
    }

    private function activeSubscription(Customer $customer): void
    {
        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-'.$customer->id,
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);
    }

    private function companyAdmin(Customer $customer): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeSubscription($customer);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Admin');

        return $user;
    }

    /**
     * The critical tenant-isolation assertion: each tab's embedded query,
     * scoped to Customer A, must never surface Customer B's rows — even
     * though the acting platform user's own BaseResource::getEloquentQuery()
     * is unfiltered across all customers.
     */
    public function test_each_tab_shows_only_the_selected_customers_rows(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);

        User::factory()->create(['customer_id' => $customerA->id, 'is_platform_user' => false, 'status' => 'active', 'name' => 'Alpha User']);
        User::factory()->create(['customer_id' => $customerB->id, 'is_platform_user' => false, 'status' => 'active', 'name' => 'Beta User']);

        $module = Module::firstOrCreate(['module_code' => 'billing_view'], ['module_name' => 'Billing View', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customerA->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);
        CustomerModule::create(['customer_id' => $customerB->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        CustomerSubscription::create(['customer_id' => $customerA->id, 'subscription_no' => 'SUB-A', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(), 'status' => 'active']);
        CustomerSubscription::create(['customer_id' => $customerB->id, 'subscription_no' => 'SUB-B', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(), 'status' => 'active']);

        License::create(['customer_id' => $customerA->id, 'license_no' => 'LIC-A', 'valid_from' => now()->subDay(), 'valid_to' => now()->addYear(), 'status' => 'active']);
        License::create(['customer_id' => $customerB->id, 'license_no' => 'LIC-B', 'valid_from' => now()->subDay(), 'valid_to' => now()->addYear(), 'status' => 'active']);

        BillingRecord::create(['customer_id' => $customerA->id, 'invoice_no' => 'INV-A', 'invoice_date' => now()]);
        BillingRecord::create(['customer_id' => $customerB->id, 'invoice_no' => 'INV-B', 'invoice_date' => now()]);

        AuditLog::create(['customer_id' => $customerA->id, 'module' => 'test', 'action' => 'ACTION-A']);
        AuditLog::create(['customer_id' => $customerB->id, 'module' => 'test', 'action' => 'ACTION-B']);

        $this->actingAs($this->platformUser());

        $cases = [
            [Users::class, UserResource::class, 'Alpha User', 'Beta User'],
            [Modules::class, CustomerModuleResource::class, 'Billing View', null],
            [Subscription::class, CustomerSubscriptionResource::class, 'SUB-A', 'SUB-B'],
            [LicensePage::class, LicenseResource::class, 'LIC-A', 'LIC-B'],
            [BillingAndPayments::class, BillingRecordResource::class, 'INV-A', 'INV-B'],
            [AuditLogs::class, AuditLogResource::class, 'ACTION-A', 'ACTION-B'],
        ];

        foreach ($cases as [$page, $resource, $expectedNeedle, $forbiddenNeedle]) {
            $response = $this->get($page::getUrl(['record' => $customerA]));
            $response->assertOk();
            $response->assertSee($expectedNeedle);

            if ($forbiddenNeedle !== null) {
                $response->assertDontSee($forbiddenNeedle);
            }
        }
    }

    public function test_non_platform_user_is_denied_even_for_their_own_customer(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->actingAs($admin);

        // CustomerResource::can('view', $customer) legitimately returns true
        // for this user (it's their own record — My Company depends on
        // that) — canAccessCustomer360()'s platform-only check is what must
        // block them here, not resource-level ownership.
        $this->assertTrue(CustomerResource::can('view', $customer));
        $this->assertFalse(CustomerResource::canAccessCustomer360($customer));

        foreach ([ViewCustomer::class, Users::class, Modules::class, Subscription::class, LicensePage::class, BillingAndPayments::class, AuditLogs::class] as $page) {
            $this->get($page::getUrl(['record' => $customer]))->assertForbidden();
        }
    }

    public function test_platform_user_can_open_every_tab_for_an_arbitrary_customer(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->platformUser());

        foreach ([ViewCustomer::class, Users::class, Modules::class, Subscription::class, LicensePage::class, BillingAndPayments::class, AuditLogs::class] as $page) {
            $this->assertTrue($page::canAccess(['record' => $customer]), "{$page} should be accessible to a platform user");
            $this->get($page::getUrl(['record' => $customer]))->assertOk();
        }
    }

    /**
     * LicenseResource::$platformOnly = true blocks non-platform users only
     * — regression guard for the reasoning in the implementation plan that
     * this doesn't also block the platform user Customer 360 is for.
     */
    public function test_license_tab_is_reachable_by_platform_user_despite_platform_only_flag(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->platformUser());

        $this->assertTrue(LicensePage::canAccess(['record' => $customer]));
        $this->get(LicensePage::getUrl(['record' => $customer]))->assertOk();
    }

    /**
     * The "Add X" buttons: security acceptance requires the new record's
     * customer_id come from the selected Customer 360 parent, never from a
     * browser-submitted value — even a deliberately tampered one aimed at
     * another customer must be silently overridden, not merely rejected.
     */
    public function test_create_action_forces_the_selected_customers_id_and_ignores_a_tampered_value(): void
    {
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $otherCustomer = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $module = Module::firstOrCreate(['module_code' => 'billing_view'], ['module_name' => 'Billing View', 'status' => 'active']);
        $this->actingAs($this->platformSuperAdmin());

        $cases = [
            [Users::class, User::class, [
                'name' => 'New Guy', 'email' => 'newguy@example.com', 'password' => 'password123', 'status' => 'active',
            ], 'email', 'newguy@example.com'],
            [Modules::class, CustomerModule::class, [
                'module_id' => $module->id, 'is_enabled' => true,
            ], 'module_id', $module->id],
            [Subscription::class, CustomerSubscription::class, [
                'subscription_no' => 'SUB-NEW', 'valid_from' => now()->toDateString(), 'valid_to' => now()->addYear()->toDateString(), 'status' => 'trial',
            ], 'subscription_no', 'SUB-NEW'],
            [LicensePage::class, License::class, [
                'license_no' => 'LIC-NEW', 'deployment_mode' => 'DatamationOnPremHosted', 'license_mode' => 'InternalSubscription',
                'valid_from' => now()->toDateString(), 'valid_to' => now()->addYear()->toDateString(), 'status' => 'trial',
            ], 'license_no', 'LIC-NEW'],
            [BillingAndPayments::class, BillingRecord::class, [
                // invoice_no is generated by BillingService::createInvoice(),
                // not a submittable create field (BillingRecordResource's own
                // form only shows it ->visibleOn('edit')) — look up by notes.
                'invoice_date' => now()->toDateString(), 'notes' => 'BILL-NEW-MARKER',
            ], 'notes', 'BILL-NEW-MARKER'],
        ];

        foreach ($cases as [$page, $modelClass, $data, $lookupField, $lookupValue]) {
            Livewire::test($page, ['record' => $customer->getKey()])
                ->callTableAction('create', data: [...$data, 'customer_id' => $otherCustomer->getKey()])
                ->assertHasNoTableActionErrors();

            $created = $modelClass::where($lookupField, $lookupValue)->first();
            $this->assertNotNull($created, "{$page} should have created a {$modelClass} record");
            $this->assertSame($customer->id, $created->customer_id, "{$page} must force customer_id to the selected customer, not the tampered value submitted for {$modelClass}");
        }
    }

    /**
     * Mirrors UserResource\Pages\CreateUser::afterCreate() — without
     * calling enforcePlatformRoleConsistency() after this embedded create,
     * assigning a platform role here would leave is_platform_user out of
     * sync (BaseResource::can()/shouldRegisterNavigation() key off
     * is_platform_user alone, independent of the actual role set).
     */
    public function test_add_user_action_enforces_platform_role_consistency(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->platformSuperAdmin());

        $platformRole = Role::where('name', 'Datamation Management')->firstOrFail();

        Livewire::test(Users::class, ['record' => $customer->getKey()])
            ->callTableAction('create', data: [
                'name' => 'Promoted Guy',
                'email' => 'promoted@example.com',
                'password' => 'password123',
                'status' => 'active',
                'roles' => [$platformRole->id],
            ])
            ->assertHasNoTableActionErrors();

        $created = User::where('email', 'promoted@example.com')->firstOrFail();
        $this->assertTrue($created->is_platform_user, 'is_platform_user must be re-derived from the assigned platform role');
    }

    /**
     * Audit logs are system-generated, not manually created — the tab must
     * not offer a create action at all.
     */
    public function test_audit_logs_tab_has_no_create_action(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->platformSuperAdmin());

        Livewire::test(AuditLogs::class, ['record' => $customer->getKey()])
            ->assertTableActionDoesNotExist('create');
    }
}
