<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource\Pages\CreateBox;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\DocumentFileResource\Pages\CreateDocumentFile;
use App\Livewire\BarcodeScannerListener;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\User;
use App\Services\BarcodeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeScannerListenerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACME', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);
    }

    public function test_scanning_a_registered_barcode_redirects_to_its_view_page(): void
    {
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-ACME-000001',
            'title' => 'Contract',
            'current_status' => 'active',
        ]);
        $registry = app(BarcodeService::class)->registerFor($file);

        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', $registry->barcode)
            ->assertRedirect(DocumentFileResource::getUrl('view', ['record' => $file->id]));
    }

    public function test_scanning_an_unregistered_barcode_offers_new_box_document_and_rack_actions(): void
    {
        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', 'UNKNOWN-000999');

        Notification::assertNotified('Unregistered Barcode Scanned');
    }

    public function test_blank_scan_is_a_noop(): void
    {
        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', '   ')
            ->assertNoRedirect();

        Notification::assertNotNotified('Unregistered Barcode Scanned');
    }

    public function test_create_document_file_saves_with_every_field_left_blank_except_customer(): void
    {
        Livewire::test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('document_files', 1);
    }

    public function test_scan_to_create_document_file_is_scannable_again_immediately(): void
    {
        $barcode = 'DOC-SCAN-CREATED-1';

        Livewire::withQueryParams(['file_barcode' => $barcode])
            ->test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $this->customer->id, 'file_barcode' => $barcode])
            ->call('create')
            ->assertHasNoFormErrors();

        $file = DocumentFile::where('file_barcode', $barcode)->firstOrFail();

        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', $barcode)
            ->assertRedirect(DocumentFileResource::getUrl('view', ['record' => $file->id]));
    }

    public function test_manually_typed_barcode_outside_the_scan_flow_is_not_registered(): void
    {
        Livewire::test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $this->customer->id, 'file_barcode' => 'HAND-TYPED-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', 'HAND-TYPED-1');

        Notification::assertNotified('Unregistered Barcode Scanned');
    }

    public function test_create_box_saves_with_every_field_left_blank_except_customer(): void
    {
        Livewire::test(CreateBox::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('boxes', 1);
    }

    public function test_scanning_while_logged_out_is_a_noop_since_the_listener_mounts_on_the_login_page_too(): void
    {
        auth()->logout();

        Livewire::test(BarcodeScannerListener::class)
            ->call('scan', 'DOC-ACME-000001')
            ->assertNoRedirect();

        Notification::assertNotNotified('Unregistered Barcode Scanned');
    }
}
