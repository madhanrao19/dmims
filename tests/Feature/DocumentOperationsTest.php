<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Services\DocumentMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    private function box(string $no = 'B1'): Box
    {
        return Box::create(['customer_id' => $this->customer->id, 'box_number' => $no, 'box_barcode' => "BC-{$no}", 'status' => 'active']);
    }

    private function file(): DocumentFile
    {
        return DocumentFile::create([
            'customer_id' => $this->customer->id,
            'file_barcode' => 'DOC-'.uniqid(),
            'title' => 'Contract',
            'current_status' => 'active',
        ]);
    }

    private function location(string $code = 'L1'): Location
    {
        return Location::create(['customer_id' => $this->customer->id, 'location_code' => $code, 'location_name' => $code, 'status' => 'active']);
    }

    public function test_file_transfer_moves_between_boxes_and_logs(): void
    {
        $file = $this->file();
        $boxA = $this->box('A');
        $boxB = $this->box('B');
        app(DocumentMovementService::class)->receiveInFile($file, $boxA->id, 'External courier');

        app(DocumentMovementService::class)->transferFile($file->refresh(), $boxB->id);

        $this->assertSame($boxB->id, $file->refresh()->current_box_id);
        $this->assertDatabaseHas('document_movement_logs', [
            'movable_type' => 'document_file',
            'movable_id' => $file->id,
            'action_type' => 'transfer_file',
            'from_box_id' => $boxA->id,
            'to_box_id' => $boxB->id,
        ]);
    }

    public function test_file_move_out_and_return(): void
    {
        $file = $this->file();
        $box = $this->box();
        app(DocumentMovementService::class)->receiveInFile($file, $box->id);

        app(DocumentMovementService::class)->moveOutFile($file->refresh(), 'Lawyer office');
        $file->refresh();
        $this->assertSame('moved_out', $file->current_status);
        $this->assertNull($file->current_box_id);

        app(DocumentMovementService::class)->returnFile($file, $box->id);
        $file->refresh();
        $this->assertSame('active', $file->current_status);
        $this->assertSame($box->id, $file->current_box_id);
    }

    public function test_box_transfer_and_move_out(): void
    {
        $box = $this->box();
        $locA = $this->location('A');
        $locB = $this->location('B');
        app(DocumentMovementService::class)->receiveInBox($box, $locA->id);

        app(DocumentMovementService::class)->transferBox($box->refresh(), $locB->id);
        $this->assertSame($locB->id, $box->refresh()->current_location_id);

        app(DocumentMovementService::class)->moveOutBox($box->refresh(), 'Offsite archive');
        $box->refresh();
        $this->assertSame('moved_out', $box->status);
        $this->assertNull($box->current_location_id);
    }

    public function test_movement_numbers_are_unique_across_calls(): void
    {
        $box = $this->box();
        $locA = $this->location('A');
        $locB = $this->location('B');
        $service = app(DocumentMovementService::class);

        $first = $service->receiveInBox($box, $locA->id);
        $second = $service->transferBox($box->refresh(), $locB->id);

        $this->assertNotSame($first->movement_no, $second->movement_no);
    }

    public function test_box_file_count_is_derived_from_movements(): void
    {
        $service = app(DocumentMovementService::class);
        $boxA = $this->box('A');
        $boxB = $this->box('B');
        $file = $this->file();

        $service->receiveInFile($file, $boxA->id);
        $this->assertSame(1, $boxA->fresh()->current_file_count);

        $service->transferFile($file->refresh(), $boxB->id);
        $this->assertSame(0, $boxA->fresh()->current_file_count);
        $this->assertSame(1, $boxB->fresh()->current_file_count);

        $service->moveOutFile($file->refresh(), 'Lawyer office');
        $this->assertSame(0, $boxB->fresh()->current_file_count);

        $service->returnFile($file->refresh(), $boxB->id);
        $this->assertSame(1, $boxB->fresh()->current_file_count);
    }

    public function test_box_capacity_percent_is_computed(): void
    {
        $box = Box::create([
            'customer_id' => $this->customer->id,
            'box_number' => 'CAP1',
            'box_barcode' => 'BC-CAP1',
            'status' => 'active',
            'capacity_limit' => 10,
            'current_file_count' => 8,
        ]);

        $this->assertSame(80, $box->capacity_percent);

        $box->capacity_limit = null;
        $this->assertNull($box->capacity_percent);
    }

    public function test_location_box_capacity_percent_is_computed(): void
    {
        $shelf = $this->location('SHELF-A');
        $shelf->box_capacity = 5;
        $shelf->save();

        $this->box('A')->update(['current_location_id' => $shelf->id]);
        $this->box('B')->update(['current_location_id' => $shelf->id]);

        $this->assertSame(2, $shelf->boxes_used_count);
        $this->assertSame(40, $shelf->box_capacity_percent);
    }

    public function test_move_out_with_due_date_tracks_borrow_and_overdue_state(): void
    {
        $file = $this->file();
        $box = $this->box();
        $service = app(DocumentMovementService::class);
        $service->receiveInFile($file, $box->id);

        $service->moveOutFile($file->refresh(), 'Legal dept', [
            'borrowed_by' => 'Santhi',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $file->refresh();
        $this->assertSame('Santhi', $file->borrowed_by);
        $this->assertTrue($file->is_overdue);

        $service->returnFile($file, $box->id);
        $file->refresh();
        $this->assertFalse($file->is_overdue);
        $this->assertNotNull($file->returned_at);
    }
}
