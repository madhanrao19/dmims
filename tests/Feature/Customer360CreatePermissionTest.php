<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Filament\Resources\UserResource;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Governance: "Within Customer 360 and corresponding customer-management
 * pages, only Super Admin may Create/Add any records, including users."
 * Covers the server-side gate directly (CustomerResource::can('create') and
 * HasCustomerScopedEmbeddedTable's shared authorize closure) so a direct
 * Livewire/action call — not just the hidden UI button — is denied too.
 */
class Customer360CreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function platformUser(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'customer_id' => null,
            'is_platform_user' => true,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function companyAdmin(Customer $customer): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Admin');

        return $user;
    }

    public function test_super_admin_can_create_customers_and_embedded_records(): void
    {
        $superAdmin = $this->platformUser('Datamation Super Admin');
        $this->actingAs($superAdmin);
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);

        $this->assertTrue(CustomerResource::can('create'));
        $this->assertTrue($this->customerScopedAuthorized($customer));
    }

    public function test_view_only_platform_role_cannot_create(): void
    {
        $viewOnly = $this->platformUser('Datamation Management');
        $this->actingAs($viewOnly);
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);

        $this->assertFalse(CustomerResource::can('create'));
        $this->assertFalse($this->customerScopedAuthorized($customer));
    }

    /**
     * Regression: BaseResource::usageLimitReached() derives the customer
     * from the ACTOR's own customer_id, which is always null for a
     * platform user — so a Super Admin's Customer 360 "Add User" silently
     * skipped the target customer's max_users seat limit entirely. Fixed
     * via BaseResource::usageLimitReachedForCustomer($customer->getKey()),
     * called explicitly with the target customer in
     * HasCustomerScopedEmbeddedTable::customerScopedCreateAction().
     */
    public function test_super_admin_cannot_add_user_once_customer_seat_limit_reached(): void
    {
        $superAdmin = $this->platformUser('Datamation Super Admin');
        $this->actingAs($superAdmin);

        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-'.$customer->id,
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'max_users' => 1,
        ]);
        User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        // Seat limit (1) already met by the one existing user above.
        $this->assertTrue(CustomerResource::can('create'), 'Customer-level create gate is unrelated to per-resource seat limits');
        $this->assertFalse($this->customerScopedAuthorized($customer));
    }

    /**
     * Company Admin already can't reach Customer 360 at all
     * (canAccessCustomer360() requires is_platform_user), but this asserts
     * the create gate itself denies them too — defence in depth against a
     * direct action/request call that bypasses the page-level gate.
     */
    public function test_company_admin_cannot_create_via_customer_360_gate(): void
    {
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $this->actingAs($admin);

        $this->assertFalse(CustomerResource::can('create'));
        $this->assertFalse(CustomerResource::canAccessCustomer360());
        $this->assertFalse($this->customerScopedAuthorized(Customer::create([
            'company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active',
        ])));
    }

    /** @return bool The exact closure HasCustomerScopedEmbeddedTable::customerScopedCreateAction() passes to ->authorize(). */
    private function customerScopedAuthorized(Customer $customer): bool
    {
        $trait = new class($customer)
        {
            use HasCustomerScopedEmbeddedTable;

            public function __construct(private Customer $customer) {}

            protected static function sourceResource(): string
            {
                return UserResource::class;
            }

            public function getRecord(): Customer
            {
                return $this->customer;
            }

            public function makeCreateAction(): CreateAction
            {
                return $this->customerScopedCreateAction('Add');
            }
        };

        return $trait->makeCreateAction()->isAuthorized();
    }
}
