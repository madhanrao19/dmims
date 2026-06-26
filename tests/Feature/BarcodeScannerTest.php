<?php

namespace Tests\Feature;

use App\Models\BarcodeRegistry;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\ScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarcodeScannerTest extends TestCase
{
    use RefreshDatabase;

    private function product(Customer $customer, string $sku = 'SKU1'): Product
    {
        return Product::create([
            'customer_id' => $customer->id,
            'sku' => $sku,
            'product_name' => 'Widget',
            'status' => 'active',
        ]);
    }

    public function test_register_generates_formatted_barcode_and_is_idempotent(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACME', 'status' => 'active']);
        $product = $this->product($customer);

        $registry = app(BarcodeService::class)->registerFor($product);

        $this->assertSame('PRD-ACME-000001', $registry->barcode);
        $this->assertSame('product', $registry->barcode_type);
        $this->assertSame('products', $registry->reference_table);
        $this->assertSame($product->id, $registry->reference_id);
        $this->assertSame('PRD-ACME-000001', $product->fresh()->barcode);

        // Calling again must not create a second registry entry.
        $again = app(BarcodeService::class)->registerFor($product->fresh());
        $this->assertSame($registry->id, $again->id);
        $this->assertSame(1, BarcodeRegistry::withoutGlobalScopes()->count());
    }

    public function test_detect_barcode_type_from_prefix(): void
    {
        $service = app(BarcodeService::class);

        $this->assertSame('product', $service->detectBarcodeType('PRD-ACME-000001'));
        $this->assertSame('box', $service->detectBarcodeType('BOX-ACME-000009'));
        $this->assertNull($service->detectBarcodeType('ZZZ-NOPE'));
    }

    public function test_scan_resolves_record_and_logs(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACME', 'status' => 'active']);
        $product = $this->product($customer);
        $registry = app(BarcodeService::class)->registerFor($product);

        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $outcome = app(ScannerService::class)->scan($registry->barcode, $user);

        $this->assertSame('found', $outcome['result']);
        $this->assertTrue($outcome['record']->is($product));
        $this->assertDatabaseHas('barcode_scan_logs', [
            'barcode' => $registry->barcode,
            'scan_result' => 'found',
            'scanned_by' => $user->id,
        ]);
        $this->assertNotNull($registry->fresh()->last_scanned_at);
    }

    public function test_scan_unknown_barcode_is_logged_as_unknown(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACME', 'status' => 'active']);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $outcome = app(ScannerService::class)->scan('PRD-ACME-999999', $user);

        $this->assertSame('unknown', $outcome['result']);
        $this->assertNull($outcome['record']);
        $this->assertDatabaseHas('barcode_scan_logs', ['barcode' => 'PRD-ACME-999999', 'scan_result' => 'unknown']);
    }

    public function test_replace_retires_old_barcode_and_issues_a_new_one(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACME', 'status' => 'active']);
        $product = $this->product($customer);
        $service = app(BarcodeService::class);
        $original = $service->registerFor($product);

        $replacement = $service->replace($original);

        $this->assertNotSame($original->barcode, $replacement->barcode);
        $this->assertSame('retired', $original->fresh()->status);
        $this->assertSame('active', $replacement->status);
        $this->assertSame($replacement->barcode, $product->fresh()->barcode);

        // registerFor() now sees the new active registration, not the retired one.
        $this->assertSame($replacement->id, $service->registerFor($product->fresh())->id);
    }
}
