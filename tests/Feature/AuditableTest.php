<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $customer = Customer::create([
            'company_name' => 'Acme',
            'company_code' => 'ACM',
            'status' => 'active',
        ]);

        return Product::create([
            'customer_id' => $customer->id,
            'sku' => 'SKU1',
            'product_name' => 'Widget',
            'status' => 'active',
        ]);
    }

    public function test_creating_a_model_writes_an_audit_log(): void
    {
        $product = $this->makeProduct();

        $log = AuditLog::where('auditable_type', Product::class)
            ->where('auditable_id', $product->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Widget', $log->new_values['product_name']);
        $this->assertNull($log->old_values);
        $this->assertSame($product->customer_id, $log->customer_id);
    }

    public function test_updating_a_model_records_only_changed_attributes(): void
    {
        $product = $this->makeProduct();
        $product->update(['product_name' => 'Widget v2']);

        $log = AuditLog::where('auditable_id', $product->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Widget v2', $log->new_values['product_name']);
        $this->assertSame('Widget', $log->old_values['product_name']);
        $this->assertArrayNotHasKey('sku', $log->new_values);
    }

    public function test_deleting_a_model_records_a_snapshot(): void
    {
        $product = $this->makeProduct();
        $product->delete();

        $log = AuditLog::where('auditable_id', $product->id)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->new_values);
        $this->assertSame('Widget', $log->old_values['product_name']);
    }

    public function test_sensitive_attributes_are_never_logged(): void
    {
        $user = User::factory()->create([
            'password' => 'super-secret-value',
        ]);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }
}
