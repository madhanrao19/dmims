<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentFileResource\Pages\ViewDocumentFile;
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

class DocumentFileViewInfolistTest extends TestCase
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

    public function test_document_file_view_renders_with_infolist_and_shows_physical_location(): void
    {
        $this->platformAdmin();
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'BOX-1', 'box_barcode' => 'BC-BOX-1', 'current_location_id' => $location->id, 'status' => 'active']);
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'FBC-1',
            'title' => 'Vendor Contract',
            'file_reference_no' => 'REF-1',
            'source_origin' => 'Branch B',
            'current_status' => 'active',
            'current_box_id' => $box->id,
        ]);

        Livewire::test(ViewDocumentFile::class, ['record' => $file->id])
            ->assertOk()
            ->assertSee('FBC-1')
            ->assertSee('Vendor Contract')
            ->assertSee('REF-1')
            ->assertSee('Branch B')
            ->assertSee('BC-BOX-1')
            ->assertSee('Shelf 1');
    }

    public function test_document_file_view_handles_an_unboxed_file(): void
    {
        $this->platformAdmin();
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'FBC-2',
            'title' => 'Unboxed File',
            'current_status' => 'active',
        ]);

        Livewire::test(ViewDocumentFile::class, ['record' => $file->id])
            ->assertOk()
            ->assertSee('Unassigned');
    }
}
