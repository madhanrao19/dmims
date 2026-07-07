<?php

namespace Tests\Feature;

use App\Filament\Resources\LocationTypeResource;
use App\Filament\Resources\StockAdjustmentApprovalResource;
use App\Filament\Resources\UserResource;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockAdjustmentApproval;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression coverage for the tenant-isolation / privilege-escalation hardening
 * (security review C1/H1/H2/H3/M1). Each test pins a boundary that, before the
 * fix, a tenant user could cross by trusting a write-time form field.
 */
class TenantIsolationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $this->customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
    }

    private function tenantUser(Customer $customer): User
    {
        return User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
    }

    private function platformUser(): User
    {
        return User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
    }

    // --- H1: no cross-tenant reassignment on update -------------------------

    public function test_tenant_user_cannot_reassign_customer_id_on_update(): void
    {
        $this->actingAs($this->tenantUser($this->customerA));

        $product = Product::create(['sku' => 'A1', 'product_name' => 'Widget', 'status' => 'active']);
        $this->assertSame($this->customerA->id, $product->customer_id);

        // Attempt to move the record into tenant B on edit.
        $product->customer_id = $this->customerB->id;
        $product->save();

        // The saving hook forces it back; the raw row still belongs to A.
        $raw = Product::withoutGlobalScope('customer')->find($product->id);
        $this->assertSame($this->customerA->id, $raw->customer_id);
    }

    public function test_platform_user_update_can_set_customer_id(): void
    {
        $this->actingAs($this->platformUser());

        $product = Product::create(['customer_id' => $this->customerA->id, 'sku' => 'P1', 'product_name' => 'Plat', 'status' => 'active']);
        $product->customer_id = $this->customerB->id;
        $product->save();

        $this->assertSame($this->customerB->id, $product->fresh()->customer_id);
    }

    // --- C1: UserResource privilege escalation ------------------------------

    public function test_tenant_actor_user_data_is_forced_to_own_tenant_and_non_platform(): void
    {
        $this->actingAs($this->tenantUser($this->customerA));

        $data = UserResource::enforceTenantUserData([
            'name' => 'Mallory',
            'customer_id' => $this->customerB->id, // attempt cross-tenant
            'is_platform_user' => true,            // attempt self-escalation
        ]);

        $this->assertSame($this->customerA->id, $data['customer_id']);
        $this->assertFalse($data['is_platform_user']);
    }

    public function test_platform_actor_user_data_is_left_untouched(): void
    {
        $this->actingAs($this->platformUser());

        $data = UserResource::enforceTenantUserData([
            'name' => 'Admin',
            'customer_id' => $this->customerB->id,
            'is_platform_user' => true,
        ]);

        $this->assertSame($this->customerB->id, $data['customer_id']);
        $this->assertTrue($data['is_platform_user']);
    }

    public function test_assignable_roles_exclude_platform_roles_for_tenant_but_not_platform(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs($this->tenantUser($this->customerA));
        $tenantRoles = UserResource::assignableRoleQuery(Role::query())->pluck('name')->all();
        $this->assertNotContains('Datamation Super Admin', $tenantRoles);
        $this->assertNotContains('Datamation Management', $tenantRoles);
        $this->assertContains('Company Supervisor', $tenantRoles);

        $this->actingAs($this->platformUser());
        $platformRoles = UserResource::assignableRoleQuery(Role::query())->pluck('name')->all();
        $this->assertContains('Datamation Super Admin', $platformRoles);
    }

    public function test_platform_roles_are_stripped_from_tenant_owned_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $target = User::factory()->create(['customer_id' => $this->customerA->id, 'is_platform_user' => false, 'status' => 'active']);
        $target->assignRole('Datamation Super Admin');

        $this->actingAs($this->tenantUser($this->customerA));
        UserResource::stripPlatformRoles($target);

        $this->assertFalse($target->fresh()->hasRole('Datamation Super Admin'));
    }

    // --- H3: global LocationType not writable by tenants --------------------

    public function test_tenant_cannot_write_location_types_platform_can(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = $this->tenantUser($this->customerA);
        $tenant->assignRole('Stock Inventory User'); // holds manage inventory
        $this->actingAs($tenant);

        $this->assertFalse(LocationTypeResource::can('create'));
        $this->assertFalse(LocationTypeResource::can('delete'));

        $this->actingAs($this->platformUser());
        $this->assertTrue(LocationTypeResource::can('create'));
    }

    // --- M1: defense-in-depth scopes ---------------------------------------

    public function test_setting_and_department_are_tenant_scoped(): void
    {
        // Seed rows for both tenants without an authenticated user (unscoped).
        Setting::create(['customer_id' => $this->customerA->id, 'setting_group' => 'g', 'setting_key' => 'k', 'setting_value' => 'v', 'setting_type' => 'string']);
        Setting::create(['customer_id' => $this->customerB->id, 'setting_group' => 'g', 'setting_key' => 'k', 'setting_value' => 'v', 'setting_type' => 'string']);
        Department::create(['customer_id' => $this->customerA->id, 'name' => 'Ops A']);
        Department::create(['customer_id' => $this->customerB->id, 'name' => 'Ops B']);

        $this->actingAs($this->tenantUser($this->customerA));

        $this->assertSame(1, Setting::count());
        $this->assertSame(1, Department::count());
        $this->assertSame('Ops A', Department::first()->name);
    }

    // --- M2: stock-adjustment approval segregation of duties ----------------

    public function test_requester_cannot_approve_their_own_adjustment(): void
    {
        $requester = $this->tenantUser($this->customerA);
        $requester->forceFill(['name' => 'Requester Rita'])->save();
        $this->actingAs($requester);

        // isSelfApproval only reads requested_by; no persistence needed.
        $approval = new StockAdjustmentApproval(['requested_by' => 'Requester Rita']);

        $this->assertTrue(
            StockAdjustmentApprovalResource::isSelfApproval(['approval_status' => 'approved'], $approval)
        );
    }

    public function test_a_different_user_may_approve_and_is_stamped_server_side(): void
    {
        $approver = $this->tenantUser($this->customerA);
        $approver->forceFill(['name' => 'Approver Al'])->save();
        $this->actingAs($approver);

        $approval = new StockAdjustmentApproval(['requested_by' => 'Requester Rita']);

        $this->assertFalse(
            StockAdjustmentApprovalResource::isSelfApproval(['approval_status' => 'approved'], $approval)
        );

        // Approver identity is recorded from the authenticated user, not input.
        $stamped = StockAdjustmentApprovalResource::stampApprover([
            'approval_status' => 'approved',
            'approved_by' => 'forged name', // must be ignored
        ]);

        $this->assertSame('Approver Al', $stamped['approved_by']);
        $this->assertNotNull($stamped['approved_at']);
    }
}
