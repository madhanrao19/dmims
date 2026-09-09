<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource\Pages\ViewBox;
use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoxViewInfolistTest extends TestCase
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

    public function test_box_view_renders_with_infolist_and_shows_physical_path(): void
    {
        $this->platformAdmin();
        $warehouse = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'WH', 'location_name' => 'Main Warehouse', 'status' => 'active']);
        $rack = Location::create(['customer_id' => $this->customer->id, 'parent_id' => $warehouse->id, 'location_code' => 'RACK-A', 'location_name' => 'Rack A', 'barcode' => 'BC-RACK-A', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'B1', 'box_barcode' => 'BC-B1', 'current_location_id' => $rack->id, 'status' => 'active']);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertOk()
            ->assertSee('Main Warehouse')
            ->assertSee('Rack A')
            ->assertSee('BC-RACK-A')
            ->assertSee('BC-B1');
    }

    public function test_box_view_shows_correct_content_counts_by_status(): void
    {
        $this->platformAdmin();
        $box = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'B1', 'box_barcode' => 'BC-B1', 'status' => 'active']);
        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'One', 'current_status' => 'active', 'current_box_id' => $box->id]);
        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-2', 'title' => 'Two', 'current_status' => 'active', 'current_box_id' => $box->id]);
        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-3', 'title' => 'Three', 'current_status' => 'moved_out', 'current_box_id' => $box->id]);

        Livewire::test(ViewBox::class, ['record' => $box->id])->assertOk();

        $this->assertSame(3, $box->files()->count());
        $this->assertSame(2, $box->files()->where('current_status', 'active')->count());
        $this->assertSame(1, $box->files()->where('current_status', 'moved_out')->count());
    }

    public function test_box_view_handles_a_box_with_no_current_location(): void
    {
        $this->platformAdmin();
        $box = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'B1', 'box_barcode' => 'BC-B1', 'current_location_id' => null, 'status' => 'moved_out']);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertOk()
            ->assertSee('Dispatched');
    }
}
