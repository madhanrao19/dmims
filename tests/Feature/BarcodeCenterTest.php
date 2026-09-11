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
use Spatie\Permission\Models\Permission;
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
        $admin->givePermissionTo(Permission::findOrCreate('manage barcode'));

        Livewire::actingAs($admin)
            ->test(ListBarcodeRegistries::class)
            ->callTableAction('replace', $registry, data: ['reason' => 'Label peeled off in transit'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('retired', $registry->fresh()->status);
        $this->assertSame(2, BarcodeRegistry::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => BarcodeRegistry::class,
            'action' => 'replace',
        ]);
    }

    public function test_replace_action_requires_a_reason(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($product);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->givePermissionTo(Permission::findOrCreate('manage barcode'));

        Livewire::actingAs($admin)
            ->test(ListBarcodeRegistries::class)
            ->callTableAction('replace', $registry, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertSame('active', $registry->fresh()->status);
    }

    /**
     * "Batch Generate" (renamed from "Reserve Labels" — the previous,
     * separate "Batch Generate" action that barcoded existing un-barcoded
     * DB rows was removed; this reservation-based action is now the only
     * one, under the name the user expects).
     */
    public function test_batch_generate_action_creates_unused_reserved_rows(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->givePermissionTo(Permission::findOrCreate('manage barcode'));

        Livewire::actingAs($admin)
            ->test(ListBarcodeRegistries::class)
            ->callTableAction('batchGenerate', data: [
                'customer_id' => $customer->id,
                'type' => 'document_file',
                'count' => 5,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(5, BarcodeRegistry::withoutGlobalScopes()->where('status', 'unused')->where('customer_id', $customer->id)->count());
    }

    public function test_platform_user_without_manage_barcode_cannot_run_mutating_actions(): void
    {
        // Regression for the gap where custom table actions had no
        // ->authorize(), so any platform user (even view-only) could batch
        // generate or replace barcodes regardless of permissions.
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        $registry = app(BarcodeService::class)->registerFor($product);
        $viewOnly = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::actingAs($viewOnly)
            ->test(ListBarcodeRegistries::class)
            ->assertTableActionHidden('replace', $registry)
            ->assertTableActionHidden('batchGenerate');
    }
}
