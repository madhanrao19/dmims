<?php

namespace Tests\Feature;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\AuditLogs;
use App\Filament\Clusters\MyCompany\Pages\Billing;
use App\Filament\Clusters\MyCompany\Pages\CompanyUsers;
use App\Filament\Clusters\MyCompany\Pages\EnabledModules;
use App\Filament\Clusters\MyCompany\Pages\LicenseStatus;
use App\Filament\Clusters\MyCompany\Pages\Locations as MyCompanyLocations;
use App\Filament\Clusters\MyCompany\Pages\Overview;
use App\Filament\Clusters\MyCompany\Pages\Subscription;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\BillingRecordResource;
use App\Filament\Resources\CustomerModuleResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerSubscriptionResource;
use App\Filament\Resources\LicenseResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\UserResource;
use App\Models\BillingRecord;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\License;
use App\Models\Location;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Security & Access Control Matrix §5 / Business Rules §5.1 / TDD §7.3:
 * My Company consolidates customer-facing company administration. Each tab
 * delegates to its underlying resource's own can()/table(), so this suite
 * mainly proves the wiring is correct — the authorization logic itself is
 * already covered by each resource's own tests.
 */
class MyCompanyClusterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * business-access middleware (EnsureSubscriptionActive) 403s every /admin
     * request without an active subscription — HTTP-level assertions in this
     * suite need one, unlike the resource-level ::can()/::getEloquentQuery()
     * assertions elsewhere that don't go through the middleware stack.
     */
    /**
     * AccessControlService::modeFromLicense() degrades a customer with no
     * license row at all to MODE_VIEW_ONLY by design ("full access must be
     * earned by an actual license, not its absence") — BaseResource::can()
     * blocks every write action, including create, under that mode. Needed
     * only by tests that actually exercise a write/create path.
     */
    private function activeLicense(Customer $customer): void
    {
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);
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

    private function companySupervisor(Customer $customer): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeSubscription($customer);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Supervisor');

        return $user;
    }

    /** Holds only `view inventory` (RolesAndPermissionsSeeder.php) — the genuine "view, not manage" role. */
    private function viewer(Customer $customer): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeSubscription($customer);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Viewer');

        return $user;
    }

    private function enableStockInventory(Customer $customer): void
    {
        $module = Module::firstOrCreate(['module_code' => 'stock_inventory'], ['module_name' => 'Stock Inventory', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);
    }

    /**
     * My Company's "Locations" tab is a pure view surface — unlike every
     * other tab (HasEmbeddedResourceTable embeds its resource's table
     * verbatim, actions included), this one strips Add/Edit/Delete/Batch
     * Generate/bulk actions regardless of the viewer's own permission level.
     * Tenant users keep full management via the standalone Locations menu
     * (test_location_navigation_stays_for_tenant_users_but_hides_for_platform_users
     * above) — this tab is a read-only convenience alongside it, not a
     * replacement.
     */
    public function test_locations_tab_is_read_only_even_for_a_role_that_can_manage_locations(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->enableStockInventory($customer);
        Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Warehouse One', 'status' => 'active']);

        $this->actingAs($admin);

        $this->assertTrue(MyCompanyLocations::canAccess());

        $response = $this->get(MyCompanyLocations::getUrl());
        $response->assertOk();
        $response->assertSee('Warehouse One');
        $response->assertDontSee('Add Location');
        $response->assertDontSee('Batch Generate');
        $response->assertDontSee('Print Barcode');
    }

    public function test_locations_tab_is_accessible_to_a_genuine_view_only_role(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $viewer = $this->viewer($customer);
        $this->enableStockInventory($customer);
        Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Warehouse One', 'status' => 'active']);

        $this->actingAs($viewer);

        $this->assertFalse(LocationResource::can('create'), 'Viewer must not be able to manage locations');
        $this->assertTrue(MyCompanyLocations::canAccess());
        $this->get(MyCompanyLocations::getUrl())->assertOk()->assertSee('Warehouse One');
    }

    /**
     * Real-world regression (17 September 2026): "Madhan Inc" — a real
     * customer with `stock_inventory` NOT enabled — saw no Locations tab in
     * My Company at all. `LocationResource::can('viewAny')` (used by every
     * other tab's canAccess() via HasEmbeddedResourceTable) also requires
     * the module enabled; this tab must not, since it's a passive view, not
     * the operational feature the module gates.
     */
    public function test_locations_tab_shows_even_when_stock_inventory_module_is_disabled(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        // Deliberately no enableStockInventory() call here.
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Warehouse One', 'status' => 'active']);

        $this->actingAs($admin);

        $this->assertFalse(LocationResource::can('viewAny'), 'sanity check: the module really is disabled');
        $this->assertTrue(MyCompanyLocations::canAccess());

        $response = $this->get(MyCompanyLocations::getUrl());
        $response->assertOk();
        $response->assertSee('Warehouse One');

        // The module still gates the row's own View Location link — a
        // customer without the module sees the list but can't drill in.
        $response->assertDontSee(LocationResource::getUrl('view', ['record' => $location]), false);
    }

    public function test_locations_tab_shows_only_own_customer_locations(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $admin = $this->companyAdmin($customerA);
        $this->enableStockInventory($customerA);
        Location::create(['customer_id' => $customerA->id, 'location_code' => 'L1', 'location_name' => 'Own Warehouse', 'status' => 'active']);
        Location::create(['customer_id' => $customerB->id, 'location_code' => 'L2', 'location_name' => 'Other Warehouse', 'status' => 'active']);

        $this->actingAs($admin);

        $response = $this->get(MyCompanyLocations::getUrl());
        $response->assertOk();
        $response->assertSee('Own Warehouse');
        $response->assertDontSee('Other Warehouse');
    }

    /**
     * Real-browser regression (24 August 2026): a role with no accessible
     * tab could still see the "My Company" nav item, because
     * MyLicenseStatusWidget::canView() had no permission check — any
     * non-platform user with a customer_id passed, so
     * canAccessClusteredComponents() found License Status accessible even
     * though every other tab correctly denied the role.
     *
     * Uses Document Tracking User, not Stock Inventory User — since the
     * Locations tab's `canAccess()` intentionally checks `manage
     * inventory`/`view inventory` directly (see MyCompanyLocations's own
     * doc-comment: it must show regardless of the `stock_inventory` module),
     * Stock Inventory User now legitimately has one accessible tab
     * (Locations) and would fail this "nothing is accessible" premise.
     * Document Tracking User holds no inventory/users/billing/customers
     * permission at all, so every tab — Locations included — stays hidden.
     */
    public function test_cluster_is_hidden_from_a_role_with_no_accessible_tab(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeSubscription($customer);

        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole('Document Tracking User');
        $this->actingAs($user);

        $this->assertFalse(MyCompany::shouldRegisterNavigation());
        $this->assertFalse(LicenseStatus::canAccess());
        $this->assertFalse(MyCompanyLocations::canAccess());
    }

    /**
     * Companion to the above: a Stock Inventory User (holds `manage
     * inventory`) now legitimately sees the Locations tab even with no
     * other My Company tab accessible — the cluster itself must therefore
     * become visible for them too (canAccessClusteredComponents() finds one
     * accessible child).
     */
    public function test_stock_inventory_user_sees_only_the_locations_tab(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->activeSubscription($customer);

        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole('Stock Inventory User');
        $this->actingAs($user);

        $this->assertTrue(MyCompany::shouldRegisterNavigation());
        $this->assertTrue(MyCompanyLocations::canAccess());
        $this->assertFalse(LicenseStatus::canAccess());
        $this->assertFalse(CompanyUsers::canAccess());
    }

    public function test_cluster_is_hidden_from_platform_users(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));

        $this->assertFalse(MyCompany::shouldRegisterNavigation());
    }

    public function test_cluster_is_visible_to_a_tenant_user_with_access(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->companyAdmin($customer));

        $this->assertTrue(MyCompany::shouldRegisterNavigation());
    }

    public function test_every_tab_renders_for_company_admin(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);

        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-1',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        foreach ([Overview::class, CompanyUsers::class, EnabledModules::class, Subscription::class, LicenseStatus::class, AuditLogs::class] as $page) {
            $this->assertTrue($page::canAccess(), "{$page} should be accessible to Company Admin");
            $this->get($page::getUrl())->assertOk();
        }
    }

    public function test_billing_tab_requires_billing_view_module(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->actingAs($admin);

        // Billing View module not enabled for this customer.
        $this->assertFalse(Billing::canAccess());

        $module = Module::firstOrCreate(['module_code' => 'billing_view'], ['module_name' => 'Billing View', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $this->assertTrue(Billing::canAccess());
        $this->get(Billing::getUrl())->assertOk();
    }

    public function test_audit_logs_tab_is_hidden_from_company_supervisor(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $supervisor = $this->companySupervisor($customer);
        $this->actingAs($supervisor);

        $this->assertFalse(AuditLogs::canAccess());
        $this->get(AuditLogs::getUrl())->assertForbidden();
    }

    public function test_users_tab_shows_only_own_customer_users(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $admin = $this->companyAdmin($customerA);
        User::factory()->create(['customer_id' => $customerB->id, 'is_platform_user' => false, 'status' => 'active', 'name' => 'Other Tenant User']);

        $this->actingAs($admin);

        $response = $this->get(CompanyUsers::getUrl());
        $response->assertOk();
        $response->assertSee($admin->name);
        $response->assertDontSee('Other Tenant User');
    }

    /**
     * Regression: "My Company > Users" had no "Add User" button at all — a
     * Company Admin holding UserResource's `manage users` permission (full
     * CRUD, RolesAndPermissionsSeeder.php) had no way to reach create from
     * this tab, only the unlinked standalone /admin/users/create route.
     */
    public function test_users_tab_shows_add_user_action_for_company_admin(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->activeLicense($customer);
        $this->actingAs($admin);

        $response = $this->get(CompanyUsers::getUrl());
        $response->assertOk();
        $response->assertSee('Add User');
        $response->assertSee(UserResource::getUrl('create'), false);
    }

    /**
     * Company Supervisor holds only `view users` (RolesAndPermissionsSeeder.php)
     * — the Add User action's ->authorize() must hide it, not just its
     * absence from nav, since the link would otherwise point at a create
     * page this role has no business reaching.
     */
    public function test_users_tab_hides_add_user_action_for_company_supervisor(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $supervisor = $this->companySupervisor($customer);
        $this->actingAs($supervisor);

        $response = $this->get(CompanyUsers::getUrl());
        $response->assertOk();
        $response->assertDontSee('Add User');
    }

    public function test_tabs_reuse_the_matching_resource_authorization(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->actingAs($admin);

        $this->assertSame(UserResource::can('viewAny'), CompanyUsers::canAccess());
        $this->assertSame(CustomerModuleResource::can('viewAny'), EnabledModules::canAccess());
        $this->assertSame(CustomerSubscriptionResource::can('viewAny'), Subscription::canAccess());
        $this->assertSame(AuditLogResource::can('viewAny'), AuditLogs::canAccess());
    }

    public function test_standalone_resource_navigation_is_hidden_for_tenant_users_and_for_platform_users_via_customer_360(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);

        $this->actingAs($admin);
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerModuleResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerSubscriptionResource::shouldRegisterNavigation());
        $this->assertFalse(BillingRecordResource::shouldRegisterNavigation());
        $this->assertFalse(AuditLogResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerResource::shouldRegisterNavigation());

        // Platform Customer 360 Design Review, item 10 (25 Aug 2026): these
        // five now consolidate into Customer 360's record sub-navigation for
        // platform users too, same as they already do for tenant users via
        // My Company — see CustomerProfileTest for the Customer-360-side
        // assertions. Audit Logs and Customers themselves are unaffected.
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerModuleResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerSubscriptionResource::shouldRegisterNavigation());
        $this->assertFalse(BillingRecordResource::shouldRegisterNavigation());
        $this->assertFalse(LicenseResource::shouldRegisterNavigation());
        $this->assertTrue(AuditLogResource::shouldRegisterNavigation());
        $this->assertTrue(CustomerResource::shouldRegisterNavigation());
    }

    /**
     * LocationResource is the one deliberate exception: it consolidates into
     * Customer 360 for PLATFORM users only (same $consolidatedViaCustomer360
     * flag as the five resources above), while tenant users keep their own
     * existing standalone "Locations" nav unchanged — the opposite direction
     * from every other resource in this test class, worth locking in
     * explicitly so it can't regress silently either way.
     */
    public function test_location_navigation_stays_for_tenant_users_but_hides_for_platform_users(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $module = Module::firstOrCreate(['module_code' => 'stock_inventory'], ['module_name' => 'Stock Inventory', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $this->actingAs($admin);
        $this->assertTrue(LocationResource::shouldRegisterNavigation());

        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));
        $this->assertFalse(LocationResource::shouldRegisterNavigation());
    }

    public function test_standalone_resource_routes_remain_functional_for_edit_actions(): void
    {
        // Hiding the nav entry must not break the underlying route — My
        // Company's Users table still links to UserResource's own edit page.
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        // The edit page's authorization requires a non-view-only license
        // (writes only) — unrelated to what this test is checking (that the
        // route itself still resolves once nav is hidden), so give it one.
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);
        $this->actingAs($admin);

        $this->get(UserResource::getUrl('edit', ['record' => $admin]))->assertOk();
    }

    /**
     * Platform Customer 360 Design Review, item 10: hiding these five
     * resources' standalone top-level nav for platform users must not break
     * the routes themselves — Customer 360's embedded tables and "Add X"
     * actions still link straight to them.
     */
    public function test_standalone_resource_routes_remain_functional_for_platform_users_despite_hidden_nav(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $platformUser->assignRole('Datamation Super Admin');
        $this->actingAs($platformUser);

        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->get(UserResource::getUrl('index'))->assertOk();
        $this->get(UserResource::getUrl('create'))->assertOk();

        $this->assertFalse(BillingRecordResource::shouldRegisterNavigation());
        $this->get(BillingRecordResource::getUrl('index'))->assertOk();

        $this->assertFalse(LicenseResource::shouldRegisterNavigation());
        $this->get(LicenseResource::getUrl('index'))->assertOk();
    }

    public function test_overview_shows_only_own_company_data(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active', 'email' => 'alpha@example.com']);
        Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $admin = $this->companyAdmin($customerA);

        $this->actingAs($admin);

        $response = $this->get(Overview::getUrl());
        $response->assertOk();
        $response->assertSee('Alpha');
        $response->assertDontSee('Beta');
    }

    /**
     * Security review regression (24 August 2026): Overview::mount() used to
     * fill the public Livewire $data property from the customer's full
     * attributesToArray(), exposing `notes` (internal Datamation commentary)
     * and `deployment_type` in the page's serialised state regardless of
     * which fields the disabled form actually renders.
     */
    public function test_overview_does_not_expose_undisplayed_fields(): void
    {
        $customer = Customer::create([
            'company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active',
            'notes' => 'INTERNAL-DATAMATION-NOTE-CHASING-PAYMENT',
            'deployment_type' => 'on_prem_hosted',
        ]);
        $admin = $this->companyAdmin($customer);
        $this->actingAs($admin);

        $response = $this->get(Overview::getUrl());
        $response->assertOk();
        $response->assertDontSee('INTERNAL-DATAMATION-NOTE-CHASING-PAYMENT');
        $response->assertDontSee('on_prem_hosted');
    }

    /**
     * Security review regression (24 August 2026): BillingRecordResource's
     * ViewAction/EditAction carry no explicit ->authorize(), so on a plain
     * embedding Page (not a Filament\Resources\Pages\Page) Filament's
     * framework default authorized them regardless of the resource's own
     * can() — a Company Admin (view billing only, not manage billing) could
     * reach Edit on any invoice through this tab. HasEmbeddedResourceTable::
     * getDefaultActionAuthorizationResponse() restores the mapping.
     */
    public function test_billing_tab_row_actions_match_resource_authorization(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $module = Module::firstOrCreate(['module_code' => 'billing_view'], ['module_name' => 'Billing View', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $invoice = BillingRecord::create([
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-1',
            'invoice_date' => now()->toDateString(),
            'amount' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'billing_status' => 'issued',
            'payment_status' => 'unpaid',
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAs($admin);

        Livewire::test(Billing::class)
            ->assertTableActionVisible('view', $invoice)
            ->assertTableActionHidden('edit', $invoice);
    }

    public function test_platform_user_cannot_access_the_cluster_root(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));

        $this->assertFalse(MyCompany::canAccess());
    }

    public function test_company_admin_can_access_the_cluster_root(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->actingAs($this->companyAdmin($customer));

        $this->assertTrue(MyCompany::canAccess());
    }
}
