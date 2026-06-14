<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedInventory(): Customer
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Main', 'status' => 'active']);

        $low = Product::create(['customer_id' => $customer->id, 'sku' => 'LOW', 'product_name' => 'Low item', 'reorder_level' => 10, 'unit_cost' => 2, 'status' => 'active']);
        ProductLocationStock::create(['customer_id' => $customer->id, 'product_id' => $low->id, 'location_id' => $location->id, 'available_quantity' => 3]);

        $ok = Product::create(['customer_id' => $customer->id, 'sku' => 'OK', 'product_name' => 'Fine item', 'reorder_level' => 5, 'unit_cost' => 1, 'status' => 'active']);
        ProductLocationStock::create(['customer_id' => $customer->id, 'product_id' => $ok->id, 'location_id' => $location->id, 'available_quantity' => 100]);

        return $customer;
    }

    public function test_inventory_summary_lists_all_products(): void
    {
        $this->seedInventory();

        [$headers, $rows] = app(ReportExportService::class)->build('inventory_summary');

        $this->assertSame(['SKU', 'Product', 'Reorder Level', 'Unit Cost', 'Unit Price', 'Status'], $headers);
        $this->assertCount(2, $rows);
    }

    public function test_low_stock_report_only_includes_below_threshold(): void
    {
        $this->seedInventory();

        [, $rows] = app(ReportExportService::class)->build('low_stock');

        $this->assertCount(1, $rows);
        $this->assertSame('LOW', $rows->first()[0]);
    }

    public function test_generate_returns_a_csv_download(): void
    {
        $this->seedInventory();

        $response = app(ReportExportService::class)->generate('stock_value');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_platform_reports_are_hidden_from_customer_users(): void
    {
        $customerUser = User::factory()->create(['is_platform_user' => false, 'status' => 'active']);
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        $customerReports = ReportExportService::availableTo($customerUser);
        $platformReports = ReportExportService::availableTo($platformUser);

        $this->assertArrayNotHasKey('customer_summary', $customerReports);
        $this->assertArrayHasKey('inventory_summary', $customerReports);
        $this->assertArrayHasKey('customer_summary', $platformReports);
    }
}
