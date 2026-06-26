<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Services\DocumentMovementService;
use App\Services\MovementTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    public function test_location_ancestry_path_walks_parent_chain(): void
    {
        $warehouse = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'WH', 'location_name' => 'Warehouse A', 'status' => 'active']);
        $rack = Location::create(['customer_id' => $this->customer->id, 'parent_id' => $warehouse->id, 'location_code' => 'RK', 'location_name' => 'Rack B', 'status' => 'active']);
        $shelf = Location::create(['customer_id' => $this->customer->id, 'parent_id' => $rack->id, 'location_code' => 'SH', 'location_name' => 'Shelf S02', 'status' => 'active']);

        $this->assertSame('Warehouse A > Rack B > Shelf S02', $shelf->ancestry_path);
    }

    public function test_box_and_document_physical_path_includes_full_chain(): void
    {
        $shelf = Location::create(['customer_id' => $this->customer->id, 'location_code' => 'SH', 'location_name' => 'Shelf S02', 'status' => 'active']);
        $box = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'BX-008', 'box_barcode' => 'BC-008', 'current_location_id' => $shelf->id, 'status' => 'active']);
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-1',
            'file_reference_no' => 'HR001',
            'title' => 'HR file',
            'current_box_id' => $box->id,
            'current_status' => 'active',
        ]);

        $this->assertSame('Shelf S02 > Box BX-008', $box->physical_path);
        $this->assertSame('Shelf S02 > Box BX-008 > HR001', $file->physical_path);
    }

    public function test_timeline_groups_entries_by_day_with_readable_titles(): void
    {
        $boxA = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'A', 'box_barcode' => 'BC-A', 'status' => 'active']);
        $boxB = Box::create(['customer_id' => $this->customer->id, 'box_number' => 'B', 'box_barcode' => 'BC-B', 'status' => 'active']);
        $file = DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-1',
            'title' => 'Contract',
            'current_status' => 'active',
        ]);

        $service = app(DocumentMovementService::class);
        $service->receiveInFile($file, $boxA->id, 'External courier');
        $service->transferFile($file->refresh(), $boxB->id);

        $timeline = app(MovementTimelineService::class)->forDocumentFile($file);
        $today = now()->format('d M Y');

        $this->assertTrue($timeline->has($today));
        $titles = $timeline->get($today)->pluck('title');
        $this->assertTrue($titles->contains('Created'));
        $this->assertTrue($titles->contains('Transferred'));
    }
}
