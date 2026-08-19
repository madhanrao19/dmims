<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms the Box/Location tenant scoping a security review flagged as
 * possibly missing is actually already enforced — both models use the
 * BelongsToCustomer trait (app/Models/Concerns/BelongsToCustomer.php),
 * whose global scope applies to any Box::query()/Location::query() call,
 * including the raw queries behind the Transfer/Return action dropdowns in
 * DocumentFileResource and BoxResource. This test exists so that guarantee
 * is verified, not assumed.
 */
class DocumentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_user_cannot_see_another_companys_boxes_via_box_query(): void
    {
        $companyA = Customer::create(['company_name' => 'A', 'company_code' => 'CA', 'status' => 'active']);
        $companyB = Customer::create(['company_name' => 'B', 'company_code' => 'CB', 'status' => 'active']);

        $boxA = Box::create(['customer_id' => $companyA->id, 'box_barcode' => 'BA1', 'box_number' => 'BA1', 'status' => 'active']);
        $boxB = Box::create(['customer_id' => $companyB->id, 'box_barcode' => 'BB1', 'box_number' => 'BB1', 'status' => 'active']);

        $userA = User::factory()->create(['is_platform_user' => false, 'customer_id' => $companyA->id, 'status' => 'active']);
        $this->actingAs($userA);

        $visible = Box::query()->pluck('box_number', 'id')->all();

        $this->assertArrayHasKey($boxA->id, $visible);
        $this->assertArrayNotHasKey($boxB->id, $visible, 'Box::query() must not leak another tenant\'s boxes into the Transfer/Return dropdown.');
    }

    public function test_a_tenant_user_cannot_see_another_companys_locations_via_location_query(): void
    {
        $companyA = Customer::create(['company_name' => 'A', 'company_code' => 'CA', 'status' => 'active']);
        $companyB = Customer::create(['company_name' => 'B', 'company_code' => 'CB', 'status' => 'active']);

        $locA = Location::create(['customer_id' => $companyA->id, 'location_code' => 'LA1', 'location_name' => 'LA1', 'status' => 'active']);
        $locB = Location::create(['customer_id' => $companyB->id, 'location_code' => 'LB1', 'location_name' => 'LB1', 'status' => 'active']);

        $userA = User::factory()->create(['is_platform_user' => false, 'customer_id' => $companyA->id, 'status' => 'active']);
        $this->actingAs($userA);

        $visible = Location::query()->pluck('location_name', 'id')->all();

        $this->assertArrayHasKey($locA->id, $visible);
        $this->assertArrayNotHasKey($locB->id, $visible, 'Location::query() must not leak another tenant\'s locations into the Transfer/Return dropdown.');
    }
}
