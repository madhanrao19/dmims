<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource\Pages\CreateBox;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\License;
use App\Models\Location;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for hiding the redundant "Customer" dropdown from
 * tenant users app-wide (it only ever offered their own company) while
 * still forcing the correct customer_id server-side, and for uniqueness
 * rules that read the (now hidden) field's value via Get::get('customer_id')
 * — those need a ->default() on the hidden field or they silently stop
 * scoping for tenant users.
 */
class CustomerFieldAutoSelectTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        License::create([
            'customer_id' => $this->customer->id,
            'license_no' => 'LIC-'.$this->customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);
    }

    private function tenantUser(string $role, string $moduleCode): User
    {
        $module = Module::firstOrCreate(['module_code' => $moduleCode], ['module_name' => $moduleCode, 'status' => 'active']);
        CustomerModule::create(['customer_id' => $this->customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $user = User::factory()->create(['customer_id' => $this->customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_customer_field_is_hidden_for_a_tenant_user_creating_a_product(): void
    {
        $this->tenantUser('Stock Inventory User', 'stock_inventory');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldIsHidden('customer_id')
            ->fillForm(['sku' => 'SKU-1', 'product_name' => 'Widget'])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'SKU-1')->firstOrFail();
        $this->assertSame($this->customer->id, $product->customer_id);
    }

    public function test_duplicate_sku_still_shows_inline_error_for_a_tenant_user(): void
    {
        $this->tenantUser('Stock Inventory User', 'stock_inventory');
        Product::create(['customer_id' => $this->customer->id, 'sku' => 'SKU-DUP', 'product_name' => 'Widget', 'status' => 'active']);

        // The uniqueness rule reads Get::get('customer_id') — with the field
        // hidden, this only works if it still carries a default value.
        // Without one this silently stops scoping and a raw
        // UniqueConstraintViolationException would surface instead.
        Livewire::test(CreateProduct::class)
            ->fillForm(['sku' => 'SKU-DUP', 'product_name' => 'Other Widget'])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);
    }

    public function test_customer_field_is_hidden_for_a_tenant_user_creating_a_box(): void
    {
        $this->tenantUser('Document Tracking User', 'document_tracking');
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);

        Livewire::test(CreateBox::class)
            ->assertFormFieldIsHidden('customer_id')
            ->fillForm(['box_number' => 'BOX-1', 'box_barcode' => 'BC-1', 'current_location_id' => $location->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $box = Box::where('box_number', 'BOX-1')->firstOrFail();
        $this->assertSame($this->customer->id, $box->customer_id);
    }
}
