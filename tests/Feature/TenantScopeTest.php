<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $this->customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);

        Product::create(['customer_id' => $this->customerA->id, 'sku' => 'A1', 'product_name' => 'Alpha Widget', 'status' => 'active']);
        Product::create(['customer_id' => $this->customerB->id, 'sku' => 'B1', 'product_name' => 'Beta Widget', 'status' => 'active']);
    }

    private function customerUser(Customer $customer): User
    {
        return User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
    }

    public function test_customer_user_only_sees_their_own_records(): void
    {
        $this->actingAs($this->customerUser($this->customerA));

        $skus = Product::pluck('sku')->all();

        $this->assertContains('A1', $skus);
        $this->assertNotContains('B1', $skus);
    }

    public function test_platform_user_sees_all_records(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));

        $this->assertSame(2, Product::count());
    }

    public function test_unauthenticated_context_is_not_scoped(): void
    {
        // No authenticated user (console, queue, seeders) — full visibility.
        $this->assertSame(2, Product::count());
    }

    public function test_customer_id_is_auto_assigned_on_create(): void
    {
        $this->actingAs($this->customerUser($this->customerB));

        $product = Product::create(['sku' => 'B2', 'product_name' => 'Auto Tenant', 'status' => 'active']);

        $this->assertSame($this->customerB->id, $product->customer_id);
    }

    public function test_customer_user_cannot_write_into_another_tenant(): void
    {
        // customer_id is mass-assignable; a crafted create must not be able to
        // plant a record in another customer's tenant. The creating hook forces
        // it back to the acting user's customer.
        $this->actingAs($this->customerUser($this->customerB));

        $product = Product::create([
            'customer_id' => $this->customerA->id, // attempt to write into tenant A
            'sku' => 'B3',
            'product_name' => 'Spoofed Tenant',
            'status' => 'active',
        ]);

        $this->assertSame($this->customerB->id, $product->customer_id);
    }
}
