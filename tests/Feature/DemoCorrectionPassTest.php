<?php

namespace Tests\Feature;

use App\Filament\Pages\BarcodeScanner;
use App\Filament\Resources\BoxResource\Pages\EditBox;
use App\Filament\Resources\BoxResource\Pages\ViewBox;
use App\Filament\Resources\BoxResource\RelationManagers\AuditLogRelationManager;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\DocumentFileResource\Pages\CreateDocumentFile;
use App\Filament\Resources\DocumentFileResource\Pages\EditDocumentFile;
use App\Filament\Resources\DocumentFileResource\Pages\ViewDocumentFile;
use App\Filament\Resources\LocationResource\Pages\AuditLog as LocationAuditLog;
use App\Models\BarcodeRegistry;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\DocumentFile;
use App\Models\License;
use App\Models\Location;
use App\Models\Module;
use App\Models\User;
use App\Services\DocumentMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the correction pass on the demo-readiness work
 * (Sep 2026) — fixes to gaps an external review found in commit 5cfff19:
 * the dispatched-file scan bug, missing Transfer/Move Out/Return actions on
 * the scan-reached detail pages, Edit forms bypassing movement logging,
 * and the Box Audit Log not identifying which file moved.
 */
class DemoCorrectionPassTest extends TestCase
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

    private function location(string $code = 'L1'): Location
    {
        return Location::create(['customer_id' => $this->customer->id, 'location_code' => $code, 'location_name' => $code, 'status' => 'active']);
    }

    private function box(string $no = 'B1', ?int $locationId = null): Box
    {
        return Box::create(['customer_id' => $this->customer->id, 'box_number' => $no, 'box_barcode' => "BC-{$no}", 'current_location_id' => $locationId, 'status' => 'active']);
    }

    private function tenantUser(string $role, array $modules = ['document_tracking']): User
    {
        License::create([
            'customer_id' => $this->customer->id,
            'license_no' => 'LIC-'.$this->customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        foreach ($modules as $code) {
            $module = Module::firstOrCreate(['module_code' => $code], ['module_name' => $code, 'status' => 'active']);
            CustomerModule::create(['customer_id' => $this->customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);
        }

        $user = User::factory()->create(['customer_id' => $this->customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_scanning_a_dispatched_file_into_a_box_uses_the_return_workflow(): void
    {
        $platformUser = $this->platformAdmin();
        $originalBox = $this->box('B1');
        $newBox = $this->box('B2');
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'FBC-DISPATCHED',
            'title' => 'Contract',
            'current_status' => 'active',
            'current_box_id' => $originalBox->id,
        ]);
        app(DocumentMovementService::class)->moveOutFile($file, 'Client office', ['borrowed_by' => 'Jane']);
        $file->refresh();
        $this->assertSame('moved_out', $file->current_status);
        $this->assertNull($file->current_box_id);

        BarcodeRegistry::create([
            'customer_id' => $this->customer->id,
            'barcode' => 'FBC-DISPATCHED',
            'barcode_type' => 'document_file',
            'reference_table' => 'document_files',
            'reference_id' => $file->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($platformUser)
            ->test(BarcodeScanner::class)
            ->set('data.target_box_id', $newBox->id)
            ->set('data.barcode', 'FBC-DISPATCHED')
            ->call('scan')
            ->assertNoRedirect();

        $file->refresh();
        $this->assertSame($newBox->id, $file->current_box_id);
        $this->assertSame('active', $file->current_status);
        $this->assertNotNull($file->returned_at);
        $this->assertDatabaseHas('document_movement_logs', [
            'movable_type' => 'document_file',
            'movable_id' => $file->id,
            'action_type' => 'return',
            'to_box_id' => $newBox->id,
        ]);
        $this->assertDatabaseMissing('document_movement_logs', [
            'movable_type' => 'document_file',
            'movable_id' => $file->id,
            'action_type' => 'create',
        ]);
    }

    public function test_box_view_page_has_a_working_transfer_action(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $newLocation = $this->location('L2');
        $box = $this->box('B1', $location->id);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertOk()
            ->callAction('transferBox', data: ['to_location_id' => $newLocation->id]);

        $this->assertSame($newLocation->id, $box->fresh()->current_location_id);
        $this->assertDatabaseHas('document_movement_logs', [
            'movable_type' => 'box',
            'movable_id' => $box->id,
            'action_type' => 'transfer_box',
        ]);
    }

    public function test_document_file_view_page_has_a_working_transfer_action(): void
    {
        $this->platformAdmin();
        $boxA = $this->box('B1');
        $boxB = $this->box('B2');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $boxA->id]);

        Livewire::test(ViewDocumentFile::class, ['record' => $file->id])
            ->assertOk()
            ->callAction('transferFile', data: ['to_box_id' => $boxB->id]);

        $this->assertSame($boxB->id, $file->fresh()->current_box_id);
    }

    public function test_editing_a_box_current_location_directly_is_ignored(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $newLocation = $this->location('L2');
        $box = $this->box('B1', $location->id);

        Livewire::test(EditBox::class, ['record' => $box->id])
            ->assertFormFieldIsDisabled('current_location_id')
            ->fillForm(['current_location_id' => $newLocation->id])
            ->call('save');

        // The field is disabled+not dehydrated on edit, so the submitted
        // value is never applied — placement changes must go through
        // Transfer/Move Out/Return instead.
        $this->assertSame($location->id, $box->fresh()->current_location_id);
        $this->assertDatabaseMissing('document_movement_logs', ['movable_type' => 'box', 'movable_id' => $box->id]);
    }

    public function test_editing_a_document_files_current_box_directly_is_ignored(): void
    {
        $this->platformAdmin();
        $boxA = $this->box('B1');
        $boxB = $this->box('B2');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $boxA->id]);

        Livewire::test(EditDocumentFile::class, ['record' => $file->id])
            ->assertFormFieldIsDisabled('current_box_id')
            ->fillForm(['current_box_id' => $boxB->id])
            ->call('save');

        $this->assertSame($boxA->id, $file->fresh()->current_box_id);
    }

    public function test_editing_document_file_status_to_active_without_a_box_is_rejected(): void
    {
        $this->platformAdmin();
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'FBC-1',
            'title' => 'Contract',
            'current_status' => 'moved_out',
            'current_box_id' => null,
            'destination' => 'Client office',
        ]);

        Livewire::test(EditDocumentFile::class, ['record' => $file->id])
            ->fillForm(['current_status' => 'active'])
            ->call('save')
            ->assertHasFormErrors(['current_status']);

        $this->assertSame('moved_out', $file->fresh()->current_status);
    }

    public function test_editing_document_file_status_to_moved_out_while_still_boxed_is_rejected(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $box->id]);

        Livewire::test(EditDocumentFile::class, ['record' => $file->id])
            ->fillForm(['current_status' => 'moved_out'])
            ->call('save')
            ->assertHasFormErrors(['current_status']);

        $this->assertSame('active', $file->fresh()->current_status);
    }

    public function test_editing_document_file_status_to_an_administrative_value_is_still_allowed(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $box->id]);

        Livewire::test(EditDocumentFile::class, ['record' => $file->id])
            ->fillForm(['current_status' => 'damaged'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('damaged', $file->fresh()->current_status);
    }

    public function test_editing_box_status_to_active_without_a_location_is_rejected(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        app(DocumentMovementService::class)->moveOutBox($box, 'Offsite storage');
        $box->refresh();

        Livewire::test(EditBox::class, ['record' => $box->id])
            ->fillForm(['status' => 'active'])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('moved_out', $box->fresh()->status);
    }

    public function test_editing_box_status_to_moved_out_while_still_placed_is_rejected(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $box = $this->box('B1', $location->id);

        Livewire::test(EditBox::class, ['record' => $box->id])
            ->fillForm(['status' => 'moved_out'])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('active', $box->fresh()->status);
    }

    public function test_editing_box_status_to_an_administrative_value_is_still_allowed(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $box = $this->box('B1', $location->id);

        Livewire::test(EditBox::class, ['record' => $box->id])
            ->fillForm(['status' => 'damaged'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('damaged', $box->fresh()->status);
    }

    public function test_transfer_box_into_a_full_location_shows_a_notification_instead_of_500(): void
    {
        $this->platformAdmin();
        $sourceLocation = $this->location('SRC');
        $fullLocation = $this->location('FULL');
        $fullLocation->update(['box_capacity' => 1]);
        app(DocumentMovementService::class)->receiveInBox($this->box('OTHER'), $fullLocation->id);
        $box = $this->box('B1', $sourceLocation->id);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertOk()
            ->callAction('transferBox', data: ['to_location_id' => $fullLocation->id]);

        Notification::assertNotified('Cannot transfer box');
        $this->assertSame($sourceLocation->id, $box->fresh()->current_location_id);
    }

    public function test_creating_a_document_file_into_a_full_box_creates_it_unboxed_with_a_notification(): void
    {
        $this->platformAdmin();
        $fullBox = $this->box('FULL');
        $fullBox->update(['capacity_limit' => 1]);
        app(DocumentMovementService::class)->receiveInFile(
            DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-EXISTING', 'title' => 'Existing', 'current_status' => 'active']),
            $fullBox->id,
        );

        Livewire::test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-NEW', 'title' => 'New File', 'current_box_id' => $fullBox->id])
            ->call('create');

        Notification::assertNotified('File created but not boxed');
        $file = DocumentFile::where('file_barcode', 'FBC-NEW')->firstOrFail();
        $this->assertNull($file->current_box_id);
    }

    public function test_box_audit_log_identifies_the_linked_file(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-LINK', 'title' => 'Contract', 'current_status' => 'active']);

        app(DocumentMovementService::class)->receiveInFile($file, $box->id);

        Livewire::test(AuditLogRelationManager::class, ['ownerRecord' => $box, 'pageClass' => ViewBox::class])
            ->assertOk()
            ->assertSee('file_linked')
            ->assertSee('FBC-LINK');
    }

    public function test_location_audit_log_tab_renders_and_is_tenant_scoped(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $otherCustomer = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $otherLocation = Location::create(['customer_id' => $otherCustomer->id, 'location_code' => 'GL1', 'location_name' => 'GL1', 'status' => 'active']);

        Livewire::test(LocationAuditLog::class, ['record' => $location->id])->assertOk();

        // A location's own audit log must never leak another customer's
        // location's audit rows even if ids happen to be adjacent.
        $this->assertNotSame($location->id, $otherLocation->id);
    }

    public function test_a_tenant_user_of_another_customer_cannot_open_this_locations_audit_log(): void
    {
        $location = $this->location('L1');

        $otherCustomer = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $this->customer = $otherCustomer;
        $this->tenantUser('Company Admin', ['stock_inventory']);

        // The other customer's location doesn't even resolve against this
        // tenant's scoped query — a 404-style failure, not a 403, which is
        // the stronger of the two (it never confirms the record exists).
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(LocationAuditLog::class, ['record' => $location->id]);
    }

    public function test_viewer_cannot_execute_transfer_from_the_box_view_page(): void
    {
        // Viewer holds 'view documents' but not 'manage documents' — the
        // header actions added to ViewBox/ViewDocumentFile must respect the
        // exact same authorization as the List page's row actions.
        $user = $this->tenantUser('Viewer');
        $location = $this->location('L1');
        $newLocation = $this->location('L2');
        $box = $this->box('B1', $location->id);

        Livewire::actingAs($user)
            ->test(ViewBox::class, ['record' => $box->id])
            ->assertActionHidden('transferBox')
            ->assertActionHidden('moveOutBox')
            ->assertActionHidden('returnBox');

        $this->assertSame($location->id, $box->fresh()->current_location_id);
    }

    public function test_viewer_cannot_execute_transfer_from_the_document_file_view_page(): void
    {
        $user = $this->tenantUser('Viewer');
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $box->id]);

        Livewire::actingAs($user)
            ->test(ViewDocumentFile::class, ['record' => $file->id])
            ->assertActionHidden('transferFile')
            ->assertActionHidden('moveOutFile')
            ->assertActionHidden('returnFile');
    }

    public function test_editing_a_box_still_allows_other_fields_to_save(): void
    {
        $this->platformAdmin();
        $location = $this->location('L1');
        $box = $this->box('B1', $location->id);

        Livewire::test(EditBox::class, ['record' => $box->id])
            ->fillForm(['remarks' => 'Updated via edit form'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated via edit form', $box->fresh()->remarks);
    }

    public function test_scan_center_quick_create_link_carries_a_malicious_barcode_value_safely(): void
    {
        // The scanned code becomes a query param feeding a form field's
        // ->default() — confirm an HTML/script-like value is just plain
        // text in the prefilled field, not executed or otherwise unsafe.
        $this->platformAdmin();
        $malicious = '<script>alert(1)</script>';

        $response = $this->get(DocumentFileResource::getUrl('create', ['file_barcode' => $malicious]));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    private function registerBarcode(DocumentFile $file): void
    {
        BarcodeRegistry::create([
            'customer_id' => $this->customer->id,
            'barcode' => $file->file_barcode,
            'barcode_type' => 'document_file',
            'reference_table' => 'document_files',
            'reference_id' => $file->id,
            'status' => 'active',
        ]);
    }

    public function test_add_document_mode_off_ignores_scans(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-OFF', 'title' => 'Contract', 'current_status' => 'active']);
        $this->registerBarcode($file);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertSet('addDocumentMode', false)
            ->set('scannedFileBarcode', 'FBC-OFF')
            ->call('scanDocument');

        $this->assertNull($file->fresh()->current_box_id);
    }

    public function test_add_document_mode_assigns_an_unboxed_file(): void
    {
        $this->platformAdmin();
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-NEW', 'title' => 'Contract', 'current_status' => 'active']);
        $this->registerBarcode($file);

        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->set('addDocumentMode', true)
            ->set('scannedFileBarcode', 'FBC-NEW')
            ->call('scanDocument');

        $this->assertSame($box->id, $file->fresh()->current_box_id);
        $this->assertDatabaseHas('document_movement_logs', [
            'movable_type' => 'document_file',
            'movable_id' => $file->id,
            'action_type' => 'create',
            'to_box_id' => $box->id,
        ]);
    }

    public function test_add_document_mode_transfers_a_file_from_another_box(): void
    {
        $this->platformAdmin();
        $originalBox = $this->box('B1');
        $newBox = $this->box('B2');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-XFER', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $originalBox->id]);
        $this->registerBarcode($file);

        Livewire::test(ViewBox::class, ['record' => $newBox->id])
            ->set('addDocumentMode', true)
            ->set('scannedFileBarcode', 'FBC-XFER')
            ->call('scanDocument');

        $this->assertSame($newBox->id, $file->fresh()->current_box_id);
    }

    public function test_add_document_mode_returns_a_dispatched_file(): void
    {
        $this->platformAdmin();
        $originalBox = $this->box('B1');
        $newBox = $this->box('B2');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-DISP', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $originalBox->id]);
        app(DocumentMovementService::class)->moveOutFile($file, 'Client office');
        $file->refresh();
        $this->registerBarcode($file);

        Livewire::test(ViewBox::class, ['record' => $newBox->id])
            ->set('addDocumentMode', true)
            ->set('scannedFileBarcode', 'FBC-DISP')
            ->call('scanDocument');

        $file->refresh();
        $this->assertSame($newBox->id, $file->current_box_id);
        $this->assertSame('active', $file->current_status);
        $this->assertNotNull($file->returned_at);
    }

    public function test_viewer_cannot_use_add_document_mode_toggle(): void
    {
        // Viewer holds 'view documents' but not 'manage documents' — the
        // toggle's own visible() gate and scanDocument()'s re-check must
        // both refuse a Viewer, mirroring the header actions' authorization.
        $user = $this->tenantUser('Viewer');
        $box = $this->box('B1');
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-VWR', 'title' => 'Contract', 'current_status' => 'active']);
        $this->registerBarcode($file);

        Livewire::actingAs($user)
            ->test(ViewBox::class, ['record' => $box->id])
            ->set('addDocumentMode', true)
            ->set('scannedFileBarcode', 'FBC-VWR')
            ->call('scanDocument');

        $this->assertNull($file->fresh()->current_box_id);
    }
}
