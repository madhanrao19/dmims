<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BarcodeService::resolveTitle() — added to fix "Show Name" being a silent
 * no-op on Barcode Center's Preview/Print and Batch Print (CONFORMANCE_GAP_
 * ANALYSIS §37): those actions only ever had a BarcodeRegistry row, never
 * the underlying Box/Location/Product/DocumentFile record HasBarcodeAction's
 * title fields (title/box_number/location_name/product_name) read from.
 */
class BarcodeServiceResolveTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_locations_own_name(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($location);

        $this->assertSame('Shelf 1', app(BarcodeService::class)->resolveTitle($registry));
    }

    public function test_resolves_a_products_own_name(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU-1', 'product_name' => 'Widget', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($product);

        $this->assertSame('Widget', app(BarcodeService::class)->resolveTitle($registry));
    }

    public function test_resolves_a_boxs_own_number(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $box = Box::create(['customer_id' => $customer->id, 'box_barcode' => 'BC-1', 'box_number' => 'BOX-1', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($box);

        $this->assertSame('BOX-1', app(BarcodeService::class)->resolveTitle($registry));
    }

    public function test_returns_null_for_a_reserved_but_unclaimed_label(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $registry = app(BarcodeService::class)->reserve($customer->id, 'location', 1)->first();

        $this->assertNull(app(BarcodeService::class)->resolveTitle($registry));
    }
}
