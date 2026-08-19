<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\User;
use App\Services\ReportExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    public function test_generate_supports_xlsx_and_pdf(): void
    {
        $this->seedInventory();

        $xlsx = app(ReportExportService::class)->generate('inventory_summary', 'xlsx');
        $this->assertSame(200, $xlsx->getStatusCode());
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));

        $pdf = app(ReportExportService::class)->generate('inventory_summary', 'pdf');
        $this->assertSame(200, $pdf->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    /** Every report definition must actually build without error — this
     *  would have caught the 5 documented reports (Outstanding Balance,
     *  Module Usage, Files by Box, Boxes by Location, External Movement)
     *  that existed in MFS §13 but had no build() case at all. */
    public function test_every_defined_report_builds_successfully(): void
    {
        $this->seedInventory();

        foreach (array_keys(ReportExportService::definitions()) as $key) {
            [$headers, $rows] = app(ReportExportService::class)->build($key);

            $this->assertNotEmpty($headers, "Report '{$key}' returned empty headers");
            $this->assertIsIterable($rows, "Report '{$key}' did not return an iterable of rows");
            // Force evaluation of the (possibly lazy) collection to catch
            // errors in the row-mapping closures, not just the headers.
            iterator_to_array((function () use ($rows) {
                foreach ($rows as $row) {
                    yield $row;
                }
            })());
        }
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

    /**
     * Regression: "view reports" (held by Stock Inventory User, Document
     * Tracking User, and Viewer) used to be sufficient on its own to pull
     * Billing Summary / Payment Summary / Outstanding Balance — Security &
     * Access Control Matrix §9 restricts those to SA/Management/Company
     * Admin/Company Supervisor only. Found reviewing the Billing module.
     */
    public function test_billing_reports_require_view_billing_not_just_view_reports(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $stockUser = User::factory()->create(['is_platform_user' => false, 'customer_id' => $customer->id, 'status' => 'active']);
        $stockUser->assignRole('Stock Inventory User');

        $companyAdmin = User::factory()->create(['is_platform_user' => false, 'customer_id' => $customer->id, 'status' => 'active']);
        $companyAdmin->assignRole('Company Admin');

        $stockReports = ReportExportService::availableTo($stockUser);
        $adminReports = ReportExportService::availableTo($companyAdmin);

        $this->assertArrayHasKey('inventory_summary', $stockReports, 'Stock Inventory User should still see inventory reports');
        $this->assertArrayNotHasKey('billing_summary', $stockReports);
        $this->assertArrayNotHasKey('payment_summary', $stockReports);
        $this->assertArrayNotHasKey('outstanding_balance', $stockReports);

        $this->assertArrayHasKey('billing_summary', $adminReports);
        $this->assertArrayHasKey('payment_summary', $adminReports);
        $this->assertArrayHasKey('outstanding_balance', $adminReports);
    }
}
