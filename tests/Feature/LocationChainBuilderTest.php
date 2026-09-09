<?php

namespace Tests\Feature;

use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationChainBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function rackType(): int
    {
        return LocationType::where('type_code', 'rack')->value('id');
    }

    private function buildingType(): int
    {
        return LocationType::where('type_code', 'building')->value('id');
    }

    public function test_chain_builder_creates_a_parented_sequence_of_locations(): void
    {
        $this->platformAdmin();

        Livewire::test(ListLocations::class)
            ->callTableAction('createChain', data: [
                'customer_id' => $this->customer->id,
                'levels' => [
                    ['location_type_id' => $this->buildingType(), 'location_code' => 'BLD-A', 'location_name' => 'Building A', 'barcode' => null],
                    ['location_type_id' => $this->rackType(), 'location_code' => 'RACK-A1', 'location_name' => 'Rack A1', 'barcode' => 'BC-RACK-A1'],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $building = Location::where('customer_id', $this->customer->id)->where('location_code', 'BLD-A')->firstOrFail();
        $rack = Location::where('customer_id', $this->customer->id)->where('location_code', 'RACK-A1')->firstOrFail();

        $this->assertNull($building->parent_id);
        $this->assertSame($building->id, $rack->parent_id);
        $this->assertSame('BC-RACK-A1', $rack->barcode);
    }

    public function test_chain_builder_reuses_an_existing_sibling_instead_of_duplicating(): void
    {
        $this->platformAdmin();
        $existingBuilding = Location::create([
            'customer_id' => $this->customer->id,
            'location_type_id' => $this->buildingType(),
            'location_code' => 'BLD-A',
            'location_name' => 'Building A',
            'status' => 'active',
        ]);

        Livewire::test(ListLocations::class)
            ->callTableAction('createChain', data: [
                'customer_id' => $this->customer->id,
                'levels' => [
                    ['location_type_id' => $this->buildingType(), 'location_code' => 'BLD-A', 'location_name' => 'Building A', 'barcode' => null],
                    ['location_type_id' => $this->rackType(), 'location_code' => 'RACK-A1', 'location_name' => 'Rack A1', 'barcode' => null],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Location::where('customer_id', $this->customer->id)->where('location_code', 'BLD-A')->count());
        $rack = Location::where('customer_id', $this->customer->id)->where('location_code', 'RACK-A1')->firstOrFail();
        $this->assertSame($existingBuilding->id, $rack->parent_id);
    }

    public function test_chain_builder_rolls_back_entirely_on_a_mid_chain_collision(): void
    {
        $this->platformAdmin();
        // A pre-existing location with the same code as the chain's second
        // level, but under a DIFFERENT parent — collides with the
        // per-customer unique(customer_id, location_code) constraint.
        Location::create([
            'customer_id' => $this->customer->id,
            'location_type_id' => $this->rackType(),
            'location_code' => 'RACK-A1',
            'location_name' => 'Pre-existing rack',
            'status' => 'active',
        ]);

        Livewire::test(ListLocations::class)
            ->callTableAction('createChain', data: [
                'customer_id' => $this->customer->id,
                'levels' => [
                    ['location_type_id' => $this->buildingType(), 'location_code' => 'BLD-Z', 'location_name' => 'Building Z', 'barcode' => null],
                    ['location_type_id' => $this->rackType(), 'location_code' => 'RACK-A1', 'location_name' => 'Rack A1', 'barcode' => null],
                ],
            ]);

        // The whole transaction rolled back — the first level never
        // committed either, even though its own code didn't collide.
        $this->assertDatabaseMissing('locations', ['customer_id' => $this->customer->id, 'location_code' => 'BLD-Z']);
    }
}
