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
}
