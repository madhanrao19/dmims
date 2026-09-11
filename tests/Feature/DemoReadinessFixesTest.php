<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource\Pages\ViewBox;
use App\Filament\Resources\BoxResource\RelationManagers\AuditLogRelationManager;
use App\Filament\Resources\BoxResource\RelationManagers\DocumentFilesRelationManager;
use App\Filament\Resources\BoxResource\RelationManagers\MovementLogRelationManager;
use App\Filament\Resources\DocumentFileResource\Pages\CreateDocumentFile;
use App\Filament\Resources\DocumentFileResource\Pages\ViewDocumentFile;
use App\Filament\Resources\DocumentFileResource\RelationManagers\AuditLogRelationManager as DocumentAuditLogRelationManager;
use App\Filament\Resources\DocumentFileResource\RelationManagers\MovementLogRelationManager as DocumentMovementLogRelationManager;
use App\Filament\Resources\LocationResource;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the demo-readiness pass (Sep 2026): Box/Document
 * File detail tabs, Location delete/edit, optional+scannable Current Box,
 * and "Add Document Mode" scan-to-box assignment.
 */
class DemoReadinessFixesTest extends TestCase
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

    /**
     * A tenant user of $this->customer with $role, a full license, and
     * $modules enabled — enough to pass the license/module gates so a
     * permission check is what's actually being tested, not a side effect.
     */
    private function tenantUser(string $role, array $modules = ['document_tracking', 'barcode_scanning']): User
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

        return $user;
    }

    public function test_creating_a_document_file_without_a_box_succeeds(): void
    {
        $this->platformAdmin();

        Livewire::test(CreateDocumentFile::class)
            ->fillForm(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-UNBOXED', 'title' => 'Unboxed Contract'])
            ->call('create')
            ->assertHasNoFormErrors();

        $file = DocumentFile::where('file_barcode', 'FBC-UNBOXED')->firstOrFail();
        $this->assertNull($file->current_box_id);
        $this->assertDatabaseMissing('document_movement_logs', ['movable_type' => 'document_file', 'movable_id' => $file->id]);
    }

    public function test_deleting_a_location_with_a_linked_box_is_blocked(): void
    {
        $location = $this->location();
        $this->box('B1', $location->id);

        $this->assertFalse($location->delete());
        $this->assertNull($location->fresh()->deleted_at);
    }

    public function test_deleting_an_empty_location_succeeds(): void
    {
        $location = $this->location();

        $this->assertTrue($location->delete());
        $this->assertNotNull($location->fresh()->deleted_at);
    }

    public function test_box_detail_tabs_render(): void
    {
        $this->platformAdmin();
        $box = $this->box();
        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active', 'current_box_id' => $box->id]);

        Livewire::test(ViewBox::class, ['record' => $box->id])->assertOk();
        Livewire::test(DocumentFilesRelationManager::class, ['ownerRecord' => $box, 'pageClass' => ViewBox::class])->assertOk()->assertSee('FBC-1');
        Livewire::test(MovementLogRelationManager::class, ['ownerRecord' => $box, 'pageClass' => ViewBox::class])->assertOk();
        Livewire::test(AuditLogRelationManager::class, ['ownerRecord' => $box, 'pageClass' => ViewBox::class])->assertOk();
    }

    public function test_document_file_detail_tabs_render(): void
    {
        $this->platformAdmin();
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-1', 'title' => 'Contract', 'current_status' => 'active']);

        Livewire::test(ViewDocumentFile::class, ['record' => $file->id])->assertOk();
        Livewire::test(DocumentMovementLogRelationManager::class, ['ownerRecord' => $file, 'pageClass' => ViewDocumentFile::class])->assertOk();
        Livewire::test(DocumentAuditLogRelationManager::class, ['ownerRecord' => $file, 'pageClass' => ViewDocumentFile::class])->assertOk();
    }

    /**
     * Scan Center was removed (its "scan a Document File into a target box"
     * mode was redundant with View Box's own Scan Mode, which uses the same
     * ScannerService/DocumentMovementService underneath) — this cross-
     * customer guard is still exercised, just via ViewBox::scanDocument()
     * instead of the now-deleted BarcodeScanner page. The basic
     * assign-via-scan and permission-gating cases are already covered by
     * DemoCorrectionPassTest's test_add_document_mode_assigns_an_unboxed_file
     * and test_viewer_cannot_use_add_document_mode_toggle.
     */
    public function test_platform_user_cannot_scan_assign_a_file_into_another_customers_box(): void
    {
        $this->platformAdmin();
        $otherCustomer = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $otherBox = Box::create(['customer_id' => $otherCustomer->id, 'box_number' => 'GB1', 'box_barcode' => 'BC-GB1', 'status' => 'active']);
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-XT', 'title' => 'Contract', 'current_status' => 'active']);
        BarcodeRegistry::create([
            'customer_id' => $this->customer->id,
            'barcode' => 'FBC-XT',
            'barcode_type' => 'document_file',
            'reference_table' => 'document_files',
            'reference_id' => $file->id,
            'status' => 'active',
        ]);

        Livewire::test(ViewBox::class, ['record' => $otherBox->id])
            ->set('addDocumentMode', true)
            ->set('scannedFileBarcode', 'FBC-XT')
            ->call('scanDocument');

        $this->assertNull($file->fresh()->current_box_id);
        $this->assertDatabaseMissing('document_movement_logs', ['movable_type' => 'document_file', 'movable_id' => $file->id]);
    }

    public function test_viewer_cannot_access_box_audit_log_tab(): void
    {
        // Security & Access Control Matrix §14: Viewer can view boxes but
        // not audit logs — the tab must gate on 'view audit logs', not just
        // on being able to view the box itself. Asserted directly against
        // canViewForRecord() (what the parent page consults to decide
        // whether to even show the tab) rather than via Livewire::test's
        // initial mount — RelationManager's own CanAuthorizeAccess trait
        // only re-checks on hydrate (a follow-up request), not first mount,
        // since it's normally a defense-in-depth backstop behind the
        // parent page's own tab-visibility gate.
        $user = $this->tenantUser('Viewer', ['document_tracking']);
        $this->actingAs($user);
        $box = $this->box();

        $this->assertFalse(AuditLogRelationManager::canViewForRecord($box, ViewBox::class));

        // End-to-end: the parent page must not render the tab at all for
        // this role, not just fail a direct hit against the tab component.
        Livewire::test(ViewBox::class, ['record' => $box->id])
            ->assertOk()
            ->assertDontSee('Box Audit Log');
    }

    public function test_document_movement_service_rejects_cross_customer_transfer(): void
    {
        $otherCustomer = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        $otherBox = Box::create(['customer_id' => $otherCustomer->id, 'box_number' => 'GB1', 'box_barcode' => 'BC-GB1', 'status' => 'active']);
        $file = DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'FBC-SVC', 'title' => 'Contract', 'current_status' => 'active']);

        $this->expectException(InvalidArgumentException::class);

        app(DocumentMovementService::class)->transferFile($file, $otherBox->id);
    }

    public function test_location_table_has_a_delete_action(): void
    {
        $this->platformAdmin();
        $this->location();

        Livewire::test(LocationResource\Pages\ListLocations::class)
            ->assertTableActionExists('delete');
    }
}
