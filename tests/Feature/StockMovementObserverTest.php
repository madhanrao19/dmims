<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_updates_location_stocks(): void
    {
        $customer = Customer::create([
            'company_name' => 'ACME Ltd',
            'company_code' => 'ACME',
            'status' => 'active',
        ]);

        $product = Product::create([
            'customer_id' => $customer->id,
            'sku' => 'SKU-1',
            'product_name' => 'Test Product',
            'status' => 'active',
        ]);

        $from = Location::create([
            'customer_id' => $customer->id,
            'location_code' => 'WH-A',
            'location_name' => 'Warehouse A',
            'status' => 'active',
        ]);

        $to = Location::create([
            'customer_id' => $customer->id,
            'location_code' => 'WH-B',
            'location_name' => 'Warehouse B',
            'status' => 'active',
        ]);

        ProductLocationStock::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $from->id,
            'quantity_on_hand' => 10,
            'reserved_quantity' => 0,
            'available_quantity' => 10,
        ]);

        $movement = StockMovement::create([
            'customer_id' => $customer->id,
            'movement_no' => 'MV-1',
            'product_id' => $product->id,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'quantity' => 3,
            'movement_type' => 'transfer',
            'performed_by' => null,
        ]);

        $this->assertDatabaseHas('product_location_stocks', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $from->id,
            'quantity_on_hand' => 7,
        ]);

        $this->assertDatabaseHas('product_location_stocks', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $to->id,
            'quantity_on_hand' => 3,
        ]);
    }
}
