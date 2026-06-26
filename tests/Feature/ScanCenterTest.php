<?php

namespace Tests\Feature;

use App\Filament\Pages\BarcodeScanner;
use App\Models\BarcodeRegistry;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScanCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_a_known_barcode_redirects_to_its_record(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'barcode' => 'PRD-ACM-000001', 'status' => 'active']);
        BarcodeRegistry::create([
            'customer_id' => $customer->id,
            'barcode' => 'PRD-ACM-000001',
            'barcode_type' => 'product',
            'reference_table' => 'products',
            'reference_id' => $product->id,
            'status' => 'active',
        ]);
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($platformUser)
            ->test(BarcodeScanner::class)
            ->set('data.barcode', 'PRD-ACM-000001')
            ->call('scan')
            ->assertRedirect();

        $this->assertDatabaseHas('barcode_scan_logs', ['barcode' => 'PRD-ACM-000001', 'scan_result' => 'found']);
    }

    public function test_bulk_mode_does_not_redirect_on_a_found_scan(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        BarcodeRegistry::create([
            'customer_id' => $customer->id,
            'barcode' => 'PRD-ACM-000001',
            'barcode_type' => 'product',
            'reference_table' => 'products',
            'reference_id' => $product->id,
            'status' => 'active',
        ]);
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($platformUser)
            ->test(BarcodeScanner::class)
            ->set('bulkMode', true)
            ->set('data.barcode', 'PRD-ACM-000001')
            ->call('scan')
            ->assertNoRedirect();
    }

    public function test_unknown_barcode_shows_smart_detection_prompt(): void
    {
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($platformUser)
            ->test(BarcodeScanner::class)
            ->set('data.barcode', 'UNKNOWN-999')
            ->call('scan')
            ->assertNoRedirect();

        $this->assertDatabaseHas('barcode_scan_logs', ['barcode' => 'UNKNOWN-999', 'scan_result' => 'unknown']);
    }
}
