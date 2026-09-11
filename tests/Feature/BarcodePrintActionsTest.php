<?php

namespace Tests\Feature;

use App\Filament\Resources\BarcodeRegistryResource\Pages\ListBarcodeRegistries;
use App\Filament\Resources\BoxResource\Pages\ListBoxes;
use App\Filament\Resources\DocumentFileResource\Pages\ListDocumentFiles;
use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\User;
use App\Services\BarcodeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the "Print Barcode" redesign (remove "Mark as
 * printed", add a Print button + label-size select, print only the label).
 * Specifically exercises changing the live "size" select inside the modal —
 * the exact interaction that previously crashed BarcodeRegistryResource's
 * preview/batchPrint actions with "Call to a member function
 * makeGetUtility() on null" (CONFORMANCE_GAP_ANALYSIS §14, root-caused to a
 * modalContent() closure typed with `Get $get` instead of `array $data`).
 * These actions all now use `array $data`, but the failure mode is easy to
 * reintroduce by copy-paste, so it stays under a real test rather than only
 * a code-review note.
 */
class BarcodePrintActionsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);
    }

    public function test_box_print_barcode_action_survives_a_label_size_change(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-1', 'box_number' => 'BOX-1', 'current_location_id' => $location->id, 'status' => 'active']);

        Livewire::test(ListBoxes::class)
            ->mountTableAction('barcode', $box)
            ->setTableActionData(['size' => 'large'])
            ->assertHasNoTableActionErrors();

        $this->assertGreaterThan(0, app(BarcodeService::class)->registerFor($box->fresh())->printed_count);
    }

    public function test_box_bulk_print_barcode_survives_a_label_size_change(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-2', 'box_number' => 'BOX-2', 'current_location_id' => $location->id, 'status' => 'active']);

        Livewire::test(ListBoxes::class)
            ->callTableBulkAction('bulkBarcode', [$box], data: ['size' => 'large'])
            ->assertHasNoTableBulkActionErrors();
    }

    public function test_document_file_print_barcode_action_survives_a_label_size_change(): void
    {
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active']);

        Livewire::test(ListDocumentFiles::class)
            ->mountTableAction('barcode', $file)
            ->setTableActionData(['size' => 'small'])
            ->assertHasNoTableActionErrors();
    }

    public function test_location_print_barcode_action_survives_a_label_size_change(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active', 'barcode' => 'LOC-BC-1']);

        Livewire::test(ListLocations::class)
            ->mountTableAction('barcode', $location)
            ->setTableActionData(['size' => 'medium'])
            ->assertHasNoTableActionErrors();
    }

    public function test_barcode_registry_preview_action_survives_a_label_size_change(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-3', 'box_number' => 'BOX-3', 'current_location_id' => $location->id, 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($box);

        Livewire::test(ListBarcodeRegistries::class)
            ->mountTableAction('preview', $registry)
            ->setTableActionData(['size' => 'large'])
            ->assertHasNoTableActionErrors();
    }

    /**
     * Regression: printed_count previously incremented inside
     * ->modalContent(), which Filament re-evaluates on every render —
     * including the label-size Select's own ->live() update — so changing
     * the size twice while the modal stayed open counted as three prints
     * instead of the one real preview/open. It's now incremented in
     * ->mountUsing(), which runs exactly once per modal open.
     */
    public function test_print_barcode_action_only_increments_printed_count_once_despite_live_size_changes(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-5', 'box_number' => 'BOX-5', 'current_location_id' => $location->id, 'status' => 'active']);

        Livewire::test(ListBoxes::class)
            ->mountTableAction('barcode', $box)
            ->setTableActionData(['size' => 'large'])
            ->setTableActionData(['size' => 'small'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, app(BarcodeService::class)->registerFor($box->fresh())->printed_count);
    }

    /** BarcodeService::incrementPrinted()'s $by param, which the "Copies"
     *  field on every print action now feeds — see barcodeAction()'s own
     *  mountUsing() comment for why this is exercised at the service layer
     *  rather than through the mount-time-only Filament test API. */
    public function test_increment_printed_accepts_a_copies_count(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-6', 'box_number' => 'BOX-6', 'current_location_id' => $location->id, 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($box);

        app(BarcodeService::class)->incrementPrinted($registry, 3);

        $this->assertSame(3, $registry->fresh()->printed_count);
    }

    public function test_barcode_registry_batch_print_survives_a_label_size_change(): void
    {
        $location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_barcode' => 'BC-4', 'box_number' => 'BOX-4', 'current_location_id' => $location->id, 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($box);

        Livewire::test(ListBarcodeRegistries::class)
            ->callTableBulkAction('batchPrint', [$registry], data: ['size' => 'large'])
            ->assertHasNoTableBulkActionErrors();
    }
}
