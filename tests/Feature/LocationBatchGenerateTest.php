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

class LocationBatchGenerateTest extends TestCase
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

    private function shelfType(): int
    {
        return LocationType::where('type_code', 'shelf')->value('id');
    }

    public function test_batch_generate_creates_the_requested_range_with_generated_codes_and_names(): void
    {
        $this->platformAdmin();

        Livewire::test(ListLocations::class)
            ->callTableAction('batchGenerate', data: [
                'customer_id' => $this->customer->id,
                'location_type_id' => $this->shelfType(),
                'code_prefix' => 'SHELF-',
                'name_prefix' => 'Shelf ',
                'barcode_prefix' => 'BC-SHELF-',
                'start_number' => 1,
                'end_number' => 5,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(5, Location::where('customer_id', $this->customer->id)->where('location_code', 'like', 'SHELF-%')->count());
        $this->assertDatabaseHas('locations', [
            'customer_id' => $this->customer->id,
            'location_code' => 'SHELF-01',
            'location_name' => 'Shelf 01',
            'barcode' => 'BC-SHELF-01',
        ]);
    }

    public function test_batch_generate_skips_a_row_whose_code_already_exists_and_reports_the_skip_count(): void
    {
        $this->platformAdmin();
        Location::create([
            'customer_id' => $this->customer->id,
            'location_code' => 'SHELF-03',
            'location_name' => 'Pre-existing',
            'status' => 'active',
        ]);

        Livewire::test(ListLocations::class)
            ->callTableAction('batchGenerate', data: [
                'customer_id' => $this->customer->id,
                'code_prefix' => 'SHELF-',
                'name_prefix' => 'Shelf ',
                'start_number' => 1,
                'end_number' => 5,
            ])
            ->assertHasNoTableActionErrors();

        // 5 requested, 1 pre-existing collision skipped -> 4 newly created,
        // plus the 1 pre-existing = 5 total matching rows.
        $this->assertSame(5, Location::where('customer_id', $this->customer->id)->where('location_code', 'like', 'SHELF-%')->count());
    }

    public function test_batch_generate_rejects_end_number_less_than_start_number(): void
    {
        $this->platformAdmin();

        Livewire::test(ListLocations::class)
            ->callTableAction('batchGenerate', data: [
                'customer_id' => $this->customer->id,
                'code_prefix' => 'SHELF-',
                'name_prefix' => 'Shelf ',
                'start_number' => 10,
                'end_number' => 1,
            ])
            ->assertHasTableActionErrors(['end_number']);

        $this->assertSame(0, Location::where('customer_id', $this->customer->id)->count());
    }
}
