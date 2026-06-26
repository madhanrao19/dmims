<?php

namespace Tests\Feature;

use App\Filament\Pages\WarehouseMap;
use App\Models\Box;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_drilling_into_a_location_shows_its_children_and_boxes(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $warehouse = Location::create(['customer_id' => $customer->id, 'location_code' => 'WH', 'location_name' => 'Warehouse A', 'status' => 'active']);
        $rack = Location::create(['customer_id' => $customer->id, 'parent_id' => $warehouse->id, 'location_code' => 'RK', 'location_name' => 'Rack B', 'status' => 'active']);
        Box::create(['customer_id' => $customer->id, 'box_number' => 'BX-1', 'box_barcode' => 'BC-1', 'current_location_id' => $rack->id, 'status' => 'active']);

        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        $component = Livewire::actingAs($admin)->test(WarehouseMap::class);

        $component->assertSet('locationId', null);
        $this->assertTrue($component->get('childLocations')->contains($warehouse));

        $component->call('selectLocation', $warehouse->id);
        $this->assertTrue($component->get('childLocations')->contains($rack));

        $component->call('selectLocation', $rack->id);
        $this->assertCount(1, $component->get('boxesHere'));
        $this->assertCount(2, $component->get('breadcrumb'));
    }
}
