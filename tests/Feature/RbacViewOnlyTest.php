<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\Module;
use App\Models\User;
use App\Services\AccessControlService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacViewOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccessControlService::flushCache();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function customerUser(string $role): User
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $module = Module::create(['module_code' => 'stock_inventory', 'module_name' => 'Stock', 'status' => 'active']);
        CustomerModule::create([
            'customer_id' => $customer->id,
            'module_id' => $module->id,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_view_only_role_can_read_but_not_write(): void
    {
        // Viewer holds only "view inventory" (no "manage inventory").
        $this->actingAs($this->customerUser('Viewer'));

        $this->assertTrue(ProductResource::can('viewAny'));
        $this->assertTrue(ProductResource::can('view'));
        $this->assertFalse(ProductResource::can('create'));
        $this->assertFalse(ProductResource::can('update'));
        $this->assertFalse(ProductResource::can('delete'));
    }

    public function test_manage_role_can_read_and_write(): void
    {
        $this->actingAs($this->customerUser('Stock Inventory User'));

        $this->assertTrue(ProductResource::can('viewAny'));
        $this->assertTrue(ProductResource::can('create'));
        $this->assertTrue(ProductResource::can('update'));
    }

    public function test_role_without_the_module_permission_has_no_access(): void
    {
        // Document Tracking User has no inventory permission at all.
        $this->actingAs($this->customerUser('Document Tracking User'));

        $this->assertFalse(ProductResource::can('viewAny'));
        $this->assertFalse(ProductResource::can('create'));
    }
}
