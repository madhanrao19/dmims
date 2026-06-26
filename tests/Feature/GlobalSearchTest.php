<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\ProductResource;
use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_finds_records_across_resource_types(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'UNIQ-SKU-42', 'product_name' => 'Widget', 'status' => 'active']);
        $box = Box::create(['customer_id' => $customer->id, 'box_number' => 'UNIQ-BOX-42', 'box_barcode' => 'BC-42', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'UNIQ-LOC-42', 'location_name' => 'Shelf 42', 'status' => 'active']);
        $file = DocumentFile::create(['customer_id' => $customer->id, 'file_barcode' => 'UNIQ-DOC-42', 'title' => 'Contract 42', 'current_status' => 'active']);

        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $this->actingAs($admin);

        $this->assertCount(1, ProductResource::getGlobalSearchResults('UNIQ-SKU-42'));
        $this->assertCount(1, BoxResource::getGlobalSearchResults('UNIQ-BOX-42'));
        $this->assertCount(1, LocationResource::getGlobalSearchResults('UNIQ-LOC-42'));
        $this->assertCount(1, DocumentFileResource::getGlobalSearchResults('UNIQ-DOC-42'));
    }
}
