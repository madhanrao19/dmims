<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Location::children()/getAncestryPathAttribute() assume a well-formed,
 * single-tenant tree — nothing previously stopped a parent_id edit from
 * creating a cycle or crossing into another customer's location.
 */
class LocationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_location_cannot_become_its_own_ancestor(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $warehouse = Location::create([
            'customer_id' => $customer->id,
            'location_code' => 'WH',
            'location_name' => 'Warehouse',
        ]);
        $rack = Location::create([
            'customer_id' => $customer->id,
            'parent_id' => $warehouse->id,
            'location_code' => 'RACK-A',
            'location_name' => 'Rack A',
        ]);

        $warehouse->parent_id = $rack->id;
        $saved = $warehouse->save();

        $this->assertFalse($saved);
        $this->assertNull($warehouse->fresh()->parent_id);
    }

    public function test_a_location_cannot_be_reparented_under_another_customers_location(): void
    {
        $customerA = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);

        $locationA = Location::create([
            'customer_id' => $customerA->id,
            'location_code' => 'WH-A',
            'location_name' => 'Warehouse A',
        ]);
        $locationB = Location::create([
            'customer_id' => $customerB->id,
            'location_code' => 'WH-B',
            'location_name' => 'Warehouse B',
        ]);

        $locationB->parent_id = $locationA->id;
        $saved = $locationB->save();

        $this->assertFalse($saved);
        $this->assertNull($locationB->fresh()->parent_id);
    }
}
