<?php

namespace Tests\Feature;

use App\Filament\Resources\BarcodeRegistryResource\Pages\ListBarcodeRegistries;
use App\Models\BarcodeRegistry;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_action_retires_and_reissues_via_the_resource(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($product);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListBarcodeRegistries::class)
            ->callTableAction('replace', $registry)
            ->assertHasNoTableActionErrors();

        $this->assertSame('retired', $registry->fresh()->status);
        $this->assertSame(2, BarcodeRegistry::withoutGlobalScopes()->count());
    }

    public function test_batch_generate_issues_barcodes_for_selected_products(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $productA = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget A', 'status' => 'active']);
        $productB = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU2', 'product_name' => 'Widget B', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListBarcodeRegistries::class)
            ->callTableAction('batchGenerate', data: [
                'type' => 'product',
                'record_ids' => [$productA->id, $productB->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($productA->fresh()->barcode);
        $this->assertNotNull($productB->fresh()->barcode);
    }
}
