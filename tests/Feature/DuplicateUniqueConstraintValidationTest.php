<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource\Pages\CreateBox;
use App\Filament\Resources\CustomerModuleResource\Pages\CreateCustomerModule;
use App\Filament\Resources\DocumentFileResource\Pages\CreateDocumentFile;
use App\Filament\Resources\DocumentTypeResource\Pages\CreateDocumentType;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\DocumentFile;
use App\Models\DocumentType;
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
 * Regression: submitting a form for a customer_id-scoped table with a
 * composite unique DB constraint (customer_id + some code/barcode) but no
 * matching Filament ->unique() validation crashed with a raw
 * UniqueConstraintViolationException — a Laravel debug page (or, with
 * APP_DEBUG=false, a bare Livewire error toast) instead of a normal inline
 * "already exists" validation message. Found via a live Herd error report
 * on CustomerModuleResource; audited every other resource with the same
 * customer_id + column composite unique constraint pattern and fixed all of
 * them the same way.
 */
class DuplicateUniqueConstraintValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_duplicate_customer_module_shows_inline_error_instead_of_crashing(): void
    {
        $this->platformAdmin();
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $module = Module::create(['module_code' => 'stock_inventory', 'module_name' => 'Stock', 'status' => 'active']);
        $customer->customerModules()->create(['module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        Livewire::test(CreateCustomerModule::class)
            ->fillForm(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true])
            ->call('create')
            ->assertHasFormErrors(['module_id' => 'unique']);
    }

    public function test_duplicate_location_code_shows_inline_error(): void
    {
        $this->platformAdmin();
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);

        Livewire::test(CreateLocation::class)
            ->fillForm(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 2'])
            ->call('create')
            ->assertHasFormErrors(['location_code' => 'unique']);
    }

    public function test_duplicate_product_sku_shows_inline_error(): void
    {
        $this->platformAdmin();
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Other Widget'])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);
    }

    public function test_duplicate_box_number_shows_inline_error(): void
    {
        $this->platformAdmin();
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        Box::create(['customer_id' => $customer->id, 'box_number' => 'BOX-1', 'box_barcode' => 'BC-1', 'current_location_id' => $location->id, 'status' => 'active']);

        Livewire::test(CreateBox::class)
            ->fillForm(['customer_id' => $customer->id, 'box_number' => 'BOX-1', 'box_barcode' => 'BC-2', 'current_location_id' => $location->id])
            ->call('create')
            ->assertHasFormErrors(['box_number' => 'unique']);
    }

    public function test_duplicate_document_file_barcode_shows_inline_error(): void
    {
        $this->platformAdmin();
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        DocumentFile::create(['customer_id' => $customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active']);

        Livewire::test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Other Contract'])
            ->call('create')
            ->assertHasFormErrors(['file_barcode' => 'unique']);
    }

    public function test_duplicate_document_type_code_for_the_same_tenant_shows_inline_error(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $module = Module::create(['module_code' => 'document_tracking', 'module_name' => 'Document Tracking', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $admin = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $admin->assignRole('Company Admin');
        $this->actingAs($admin);

        DocumentType::create(['customer_id' => $customer->id, 'type_code' => 'INV', 'type_name' => 'Invoice', 'status' => 'active']);

        Livewire::test(CreateDocumentType::class)
            ->fillForm(['type_code' => 'INV', 'type_name' => 'Invoice Duplicate'])
            ->call('create')
            ->assertHasFormErrors(['type_code' => 'unique']);
    }

    public function test_creating_a_module_grant_for_a_different_customer_still_succeeds(): void
    {
        $this->platformAdmin();
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $module = Module::create(['module_code' => 'stock_inventory', 'module_name' => 'Stock', 'status' => 'active']);
        $customerA->customerModules()->create(['module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        // Same module, different customer — not a duplicate, must succeed.
        Livewire::test(CreateCustomerModule::class)
            ->fillForm(['customer_id' => $customerB->id, 'module_id' => $module->id, 'is_enabled' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customer_modules', ['customer_id' => $customerB->id, 'module_id' => $module->id]);
    }
}
