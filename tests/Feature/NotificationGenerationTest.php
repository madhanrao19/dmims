<?php

namespace Tests\Feature;

use App\Models\BillingRecord;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductLocationStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    private function location(Customer $customer): Location
    {
        return Location::create([
            'customer_id' => $customer->id,
            'location_code' => 'LOC1',
            'location_name' => 'Main Store',
            'status' => 'active',
        ]);
    }

    public function test_it_generates_a_low_stock_notification_once(): void
    {
        $customer = $this->customer();
        $product = Product::create([
            'customer_id' => $customer->id,
            'sku' => 'SKU1',
            'product_name' => 'Widget',
            'reorder_level' => 10,
            'status' => 'active',
        ]);
        ProductLocationStock::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $this->location($customer)->id,
            'available_quantity' => 3,
        ]);

        $this->artisan('dmims:generate-notifications')->assertSuccessful();
        $this->artisan('dmims:generate-notifications')->assertSuccessful(); // second run must not duplicate

        $this->assertSame(1, Notification::where('notification_type', 'low_stock')->count());
        $this->assertDatabaseHas('notifications', [
            'notification_type' => 'low_stock',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_it_generates_a_billing_overdue_notification(): void
    {
        $customer = $this->customer();
        BillingRecord::create([
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-TEST-1',
            'invoice_date' => now()->subMonth(),
            'due_date' => now()->subWeek(),
            'amount' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'billing_status' => 'issued',
            'payment_status' => 'unpaid',
        ]);

        $this->artisan('dmims:generate-notifications')->assertSuccessful();

        $this->assertSame(1, Notification::where('notification_type', 'billing_overdue')->count());
    }

    public function test_well_stocked_product_creates_no_notification(): void
    {
        $customer = $this->customer();
        $product = Product::create([
            'customer_id' => $customer->id,
            'sku' => 'SKU2',
            'product_name' => 'Plenty',
            'reorder_level' => 5,
            'status' => 'active',
        ]);
        ProductLocationStock::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $this->location($customer)->id,
            'available_quantity' => 50,
        ]);

        $this->artisan('dmims:generate-notifications')->assertSuccessful();

        $this->assertSame(0, Notification::where('notification_type', 'low_stock')->count());
    }
}
