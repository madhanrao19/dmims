<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Filament\Resources\UserResource;
use App\Models\Customer;
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

        $this->assertTrue(CustomerResource::can('create'));
        $this->assertTrue($this->customerScopedAuthorized());
    }

    public function test_view_only_platform_role_cannot_create(): void
    {
        $viewOnly = $this->platformUser('Datamation Management');
        $this->actingAs($viewOnly);

        $this->assertFalse(CustomerResource::can('create'));
        $this->assertFalse($this->customerScopedAuthorized());
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
        $this->assertFalse($this->customerScopedAuthorized());
    }

    /** @return bool The exact closure HasCustomerScopedEmbeddedTable::customerScopedCreateAction() passes to ->authorize(). */
    private function customerScopedAuthorized(): bool
    {
        $trait = new class
        {
            use HasCustomerScopedEmbeddedTable;

            protected static function sourceResource(): string
            {
                return UserResource::class;
            }

            public function getRecord(): Customer
            {
                return new Customer;
            }

            public function makeCreateAction(): CreateAction
            {
                return $this->customerScopedCreateAction('Add');
            }
        };

        return $trait->makeCreateAction()->isAuthorized();
    }
}
