<?php

namespace Tests\Feature;

use App\Jobs\RunExport;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Product;
use App\Services\ExportService;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    public function test_it_exports_products_to_csv(): void
    {
        Storage::fake('local');
        $customer = $this->customer();
        Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'unit_price' => 9.99, 'status' => 'active']);
        Product::create(['customer_id' => $customer->id, 'sku' => 'SKU2', 'product_name' => 'Gadget', 'unit_price' => 5.00, 'status' => 'active']);

        $export = app(ExportService::class)->export('products', $customer->id);

        $this->assertSame('completed', $export->status);
        Storage::disk('local')->assertExists($export->file_path);

        $contents = Storage::disk('local')->get($export->file_path);
        $this->assertStringContainsString('sku,barcode,product_name', $contents);
        $this->assertStringContainsString('SKU1', $contents);
        $this->assertStringContainsString('SKU2', $contents);
        $this->assertSame(3, substr_count(trim($contents), "\n") + 1); // header + 2 rows
    }

    public function test_create_pending_export_does_not_write_until_the_job_runs(): void
    {
        Storage::fake('local');
        Queue::fake();
        $customer = $this->customer();
        Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);

        $export = app(ExportService::class)->createPending('products', $customer->id);
        $this->assertSame('pending', $export->status);
        $this->assertNull($export->file_path);

        RunExport::dispatch($export);
        Queue::assertPushed(RunExport::class);
        $this->assertSame('pending', $export->fresh()->status);

        app(RunExport::class, ['export' => $export])->handle(app(ExportService::class));

        $this->assertSame('completed', $export->fresh()->status);
        Storage::disk('local')->assertExists($export->fresh()->file_path);
    }

    public function test_it_imports_valid_rows_and_flags_invalid_ones(): void
    {
        Storage::fake('local');
        $customer = $this->customer();

        $csv = "sku,product_name,unit_price,status\n"
            ."SKU1,Widget,9.99,active\n"
            .",Missing SKU,1.00,active\n"
            ."SKU3,Gadget,5.00,active\n";
        Storage::disk('local')->put('imports/test.csv', $csv);

        $import = Import::create([
            'customer_id' => $customer->id,
            'import_no' => 'IMP-TEST',
            'import_type' => 'products',
            'file_name' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'status' => 'uploaded',
        ]);

        $import = app(ImportService::class)->process($import);

        $this->assertSame('completed', $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(2, $import->success_rows);
        $this->assertSame(1, $import->failed_rows);

        $this->assertSame(2, Product::where('customer_id', $customer->id)->count());
        $this->assertDatabaseHas('products', ['sku' => 'SKU1', 'product_name' => 'Widget']);

        // The invalid row was recorded with an error and not imported.
        $this->assertDatabaseHas('import_rows', ['import_id' => $import->id, 'validation_status' => 'invalid']);
    }

    public function test_it_detects_duplicate_rows_in_file_and_in_database(): void
    {
        Storage::fake('local');
        $customer = $this->customer();

        // Pre-existing product to collide with.
        Product::create(['customer_id' => $customer->id, 'sku' => 'EXISTING', 'product_name' => 'Already here', 'status' => 'active']);

        $csv = "sku,product_name,status\n"
            ."NEW1,New product,active\n"
            ."NEW1,Duplicate in file,active\n"   // duplicate within the file
            ."EXISTING,Clashes with DB,active\n"; // duplicate of existing record
        Storage::disk('local')->put('imports/dupes.csv', $csv);

        $import = Import::create([
            'customer_id' => $customer->id,
            'import_no' => 'IMP-DUP',
            'import_type' => 'products',
            'file_name' => 'dupes.csv',
            'file_path' => 'imports/dupes.csv',
            'status' => 'uploaded',
        ]);

        $import = app(ImportService::class)->process($import);

        $this->assertSame(3, $import->total_rows);
        $this->assertSame(1, $import->success_rows);   // only NEW1 (first occurrence)
        $this->assertSame(2, $import->failed_rows);    // in-file dup + DB dup
        $this->assertSame(1, Product::where('sku', 'NEW1')->count());
    }
}
