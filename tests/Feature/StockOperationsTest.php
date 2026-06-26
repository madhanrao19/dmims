<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    private Location $a;

    private Location $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $this->product = Product::create(['customer_id' => $this->customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'unit_cost' => 2, 'status' => 'active']);
        $this->a = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'A', 'location_name' => 'A', 'status' => 'active']);
        $this->b = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'B', 'location_name' => 'B', 'status' => 'active']);
    }

    private function qtyAt(Location $location): float
    {
        return (float) (ProductLocationStock::where('product_id', $this->product->id)
            ->where('location_id', $location->id)
            ->value('quantity_on_hand') ?? 0);
    }

    public function test_receive_in_increases_stock(): void
    {
        app(StockMovementService::class)->receiveIn($this->product->id, $this->a->id, 10);

        $this->assertSame(10.0, $this->qtyAt($this->a));
    }

    public function test_stock_out_decreases_stock(): void
    {
        app(StockMovementService::class)->receiveIn($this->product->id, $this->a->id, 10);
        app(StockMovementService::class)->stockOut($this->product->id, $this->a->id, 4);

        $this->assertSame(6.0, $this->qtyAt($this->a));
    }

    public function test_transfer_moves_stock_between_locations(): void
    {
        app(StockMovementService::class)->receiveIn($this->product->id, $this->a->id, 10);
        app(StockMovementService::class)->transfer($this->product->id, $this->a->id, $this->b->id, 7);

        $this->assertSame(3.0, $this->qtyAt($this->a));
        $this->assertSame(7.0, $this->qtyAt($this->b));
    }

    public function test_adjust_handles_positive_and_negative_deltas(): void
    {
        app(StockMovementService::class)->adjust($this->product->id, $this->a->id, 5);
        $this->assertSame(5.0, $this->qtyAt($this->a));

        app(StockMovementService::class)->adjust($this->product->id, $this->a->id, -2);
        $this->assertSame(3.0, $this->qtyAt($this->a));
    }

    public function test_movement_numbers_are_generated(): void
    {
        $movement = app(StockMovementService::class)->receiveIn($this->product->id, $this->a->id, 1);

        $this->assertStringStartsWith('MV-', $movement->movement_no);
        $this->assertSame($this->customer->id, $movement->customer_id);
    }

    public function test_movement_numbers_are_unique_across_calls(): void
    {
        $service = app(StockMovementService::class);
        $first = $service->receiveIn($this->product->id, $this->a->id, 1);
        $second = $service->receiveIn($this->product->id, $this->a->id, 1);

        $this->assertNotSame($first->movement_no, $second->movement_no);
    }

    public function test_stock_out_beyond_available_quantity_throws_and_rolls_back(): void
    {
        app(StockMovementService::class)->receiveIn($this->product->id, $this->a->id, 5);

        $this->expectException(InsufficientStockException::class);

        try {
            app(StockMovementService::class)->stockOut($this->product->id, $this->a->id, 10);
        } finally {
            // the failed movement must not have been committed, and stock
            // must still reflect only the successful receive-in.
            $this->assertSame(5.0, $this->qtyAt($this->a));
            $this->assertSame(1, StockMovement::withoutGlobalScopes()->count());
        }
    }
}
