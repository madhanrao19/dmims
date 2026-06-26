<?php

namespace Tests\Feature;

use App\Models\BarcodeRegistry;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Export;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(Customer $customer): User
    {
        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);

        return User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products/1/stock')->assertStatus(401);
    }

    public function test_stock_inquiry_returns_totals_for_own_tenant_product(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $user = $this->tenantUser($customer);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'WH-A', 'location_name' => 'Warehouse A', 'status' => 'active']);
        ProductLocationStock::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'reserved_quantity' => 0,
            'available_quantity' => 10,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/products/{$product->id}/stock");

        $response->assertOk();
        $response->assertJsonPath('sku', 'SKU1');
        $response->assertJsonPath('total_available', 10);
    }

    public function test_stock_inquiry_404s_for_another_tenants_product(): void
    {
        $customerA = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $userA = $this->tenantUser($customerA);
        $productB = Product::create(['customer_id' => $customerB->id, 'sku' => 'SKU2', 'product_name' => 'Gadget', 'status' => 'active']);

        $this->actingAs($userA)->getJson("/api/v1/products/{$productB->id}/stock")->assertStatus(404);
    }

    public function test_barcode_resolution_returns_found_or_unknown(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $user = $this->tenantUser($customer);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'barcode' => 'PRD-ACM-000001', 'status' => 'active']);
        BarcodeRegistry::create([
            'customer_id' => $customer->id,
            'barcode' => 'PRD-ACM-000001',
            'barcode_type' => 'product',
            'reference_table' => 'products',
            'reference_id' => $product->id,
            'status' => 'active',
        ]);

        $found = $this->actingAs($user)->getJson('/api/v1/barcodes/PRD-ACM-000001');
        $found->assertOk()->assertJsonPath('result', 'found');

        $unknown = $this->actingAs($user)->getJson('/api/v1/barcodes/PRD-ACM-999999');
        $unknown->assertStatus(404)->assertJsonPath('result', 'unknown');
    }

    public function test_export_status_is_tenant_scoped(): void
    {
        $customerA = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $userA = $this->tenantUser($customerA);
        $exportB = Export::create([
            'customer_id' => $customerB->id,
            'export_no' => 'EXP-OTHER',
            'export_type' => 'products',
            'file_name' => 'EXP-OTHER.csv',
            'status' => 'completed',
        ]);

        $this->actingAs($userA)->getJson('/api/v1/exports/EXP-OTHER')->assertStatus(403);
    }
}
