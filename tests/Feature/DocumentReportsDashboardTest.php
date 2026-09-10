<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\DocumentFile;
use App\Models\License;
use App\Models\Location;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        License::create([
            'customer_id' => $this->customer->id,
            'license_no' => 'LIC-'.$this->customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        foreach (['document_tracking', 'reports'] as $moduleCode) {
            $module = Module::firstOrCreate(['module_code' => $moduleCode], ['module_name' => $moduleCode, 'status' => 'active']);
            CustomerModule::create(['customer_id' => $this->customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);
        }

        $this->location = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'L1', 'location_name' => 'Shelf 1', 'status' => 'active']);
    }

    private function tenantUser(string $role): User
    {
        $user = User::factory()->create(['customer_id' => $this->customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_dashboard_is_visible_for_a_document_tracking_user_and_shows_correct_kpis(): void
    {
        $this->tenantUser('Document Tracking User');

        $box = Box::create([
            'customer_id' => $this->customer->id,
            'box_barcode' => 'BOX-BC-1',
            'box_number' => 'BOX-1',
            'current_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-BC-1',
            'title' => 'Active file',
            'current_box_id' => $box->id,
            'current_status' => 'active',
        ]);

        DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-BC-2',
            'title' => 'Missing file',
            'current_box_id' => $box->id,
            'current_status' => 'missing',
        ]);

        Livewire::test(Reports::class)
            ->assertSee('Document Reports')
            ->assertSee('Total Documents')
            ->assertSee('DOC-BC-1')
            ->assertSee('BOX-1');

        $component = Livewire::test(Reports::class);
        $kpis = $component->instance()->documentKpis();

        $this->assertSame(2, $kpis['total_documents']);
        $this->assertSame(1, $kpis['missing_documents']);
        $this->assertSame(1, $kpis['tracked_boxes']);
    }

    public function test_status_filter_narrows_the_kpis(): void
    {
        $this->tenantUser('Document Tracking User');

        $box = Box::create([
            'customer_id' => $this->customer->id,
            'box_barcode' => 'BOX-BC-2',
            'box_number' => 'BOX-2',
            'current_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'DOC-BC-3', 'title' => 'A', 'current_box_id' => $box->id, 'current_status' => 'active']);
        DocumentFile::create(['customer_id' => $this->customer->id, 'file_barcode' => 'DOC-BC-4', 'title' => 'B', 'current_box_id' => $box->id, 'current_status' => 'missing']);

        $component = Livewire::test(Reports::class)->set('docStatus', 'missing');

        $this->assertSame(1, $component->instance()->documentKpis()['total_documents']);
    }

    public function test_dashboard_is_hidden_for_a_platform_user(): void
    {
        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $this->actingAs($user);

        Livewire::test(Reports::class)->assertDontSee('Document Reports');
    }

    public function test_dashboard_is_hidden_when_document_tracking_module_is_disabled(): void
    {
        CustomerModule::where('customer_id', $this->customer->id)
            ->whereHas('module', fn ($q) => $q->where('module_code', 'document_tracking'))
            ->update(['is_enabled' => false]);

        $this->tenantUser('Document Tracking User');

        Livewire::test(Reports::class)->assertDontSee('Document Reports');
    }

    public function test_csv_export_neutralises_formula_injection_in_free_text_fields(): void
    {
        $this->tenantUser('Document Tracking User');

        $box = Box::create([
            'customer_id' => $this->customer->id,
            'box_barcode' => 'BOX-BC-9',
            'box_number' => '=cmd|\'/c calc\'!A1',
            'current_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-BC-9',
            'title' => '=1+1',
            'current_box_id' => $box->id,
            'current_status' => 'active',
        ]);

        $documentsCsv = $this->streamedContent(Livewire::test(Reports::class)->instance()->downloadDocumentsCsv());
        $this->assertStringContainsString("'=1+1", $documentsCsv);

        $boxesCsv = $this->streamedContent(Livewire::test(Reports::class)->instance()->downloadBoxesCsv());
        $this->assertStringContainsString("'=cmd", $boxesCsv);
    }

    private function streamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_documents_from_another_customer_never_appear(): void
    {
        // Fixtures for the OTHER customer must be created before actingAs()
        // switches the request to the Acme tenant user below —
        // BelongsToCustomer's creating() hook forces customer_id to the
        // acting user's own tenant on every create() call, so building
        // "another tenant's" row while already acting as tenant A would
        // silently save it under tenant A instead (see
        // DocumentTenantIsolationTest for the same ordering convention).
        $otherCustomer = Customer::create(['company_name' => 'Other', 'company_code' => 'OTH', 'status' => 'active']);
        $otherLocation = Location::create(['customer_id' => $otherCustomer->id, 'location_code' => 'OL1', 'location_name' => 'Other Shelf', 'status' => 'active']);
        $otherBox = Box::create([
            'customer_id' => $otherCustomer->id,
            'box_barcode' => 'OTHER-BOX-BC',
            'box_number' => 'OTHER-BOX',
            'current_location_id' => $otherLocation->id,
            'status' => 'active',
        ]);
        DocumentFile::create(['customer_id' => $otherCustomer->id, 'file_barcode' => 'OTHER-DOC-BC', 'title' => 'Other tenant file', 'current_box_id' => $otherBox->id, 'current_status' => 'active']);

        $this->tenantUser('Document Tracking User');

        $component = Livewire::test(Reports::class);

        $this->assertSame(0, $component->instance()->documentKpis()['total_documents']);
        $component->assertDontSee('OTHER-DOC-BC');
    }
}
