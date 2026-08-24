<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentTypeResource;
use App\Filament\Resources\ProductResource;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $this->customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);

        Product::create(['customer_id' => $this->customerA->id, 'sku' => 'A1', 'product_name' => 'Alpha Widget', 'status' => 'active']);
        Product::create(['customer_id' => $this->customerB->id, 'sku' => 'B1', 'product_name' => 'Beta Widget', 'status' => 'active']);
    }

    private function customerUser(Customer $customer): User
    {
        return User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
    }

    public function test_customer_user_only_sees_their_own_records(): void
    {
        $this->actingAs($this->customerUser($this->customerA));

        $skus = Product::pluck('sku')->all();

        $this->assertContains('A1', $skus);
        $this->assertNotContains('B1', $skus);
    }

    public function test_platform_user_sees_all_records(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));

        $this->assertSame(2, Product::count());
    }

    public function test_unauthenticated_context_is_not_scoped(): void
    {
        // No authenticated user (console, queue, seeders) — full visibility.
        $this->assertSame(2, Product::count());
    }

    public function test_customer_id_is_auto_assigned_on_create(): void
    {
        $this->actingAs($this->customerUser($this->customerB));

        $product = Product::create(['sku' => 'B2', 'product_name' => 'Auto Tenant', 'status' => 'active']);

        $this->assertSame($this->customerB->id, $product->customer_id);
    }

    public function test_customer_user_cannot_write_into_another_tenant(): void
    {
        // customer_id is mass-assignable; a crafted create must not be able to
        // plant a record in another customer's tenant. The creating hook forces
        // it back to the acting user's customer.
        $this->actingAs($this->customerUser($this->customerB));

        $product = Product::create([
            'customer_id' => $this->customerA->id, // attempt to write into tenant A
            'sku' => 'B3',
            'product_name' => 'Spoofed Tenant',
            'status' => 'active',
        ]);

        $this->assertSame($this->customerB->id, $product->customer_id);
    }

    /**
     * Security & Access Control Matrix §3.2 (TENANT_STRICT): a platform/
     * shared record (customer_id = null) is NOT automatically visible to a
     * customer user for a model that hasn't opted into §3.3
     * (TENANT_WITH_GLOBAL_DEFAULTS). Regression for CONFORMANCE_GAP_ANALYSIS
     * "Generic tenant scope includes NULL/global records".
     */
    public function test_tenant_strict_model_excludes_null_customer_records(): void
    {
        // customer_id is nullable on notifications (platform broadcasts);
        // Security & Access Control Matrix §3.2 lists "Customer notifications"
        // as TENANT_STRICT, so a null-owned one must not leak to a tenant.
        Notification::create(['customer_id' => $this->customerA->id, 'notification_type' => 'info', 'title' => 'Own', 'message' => 'own']);
        Notification::create(['customer_id' => null, 'notification_type' => 'info', 'title' => 'Platform', 'message' => 'platform']);

        $this->actingAs($this->customerUser($this->customerA));

        $titles = Notification::pluck('title')->all();

        $this->assertContains('Own', $titles);
        $this->assertNotContains('Platform', $titles);
    }

    /**
     * Security & Access Control Matrix §3.3 (TENANT_WITH_GLOBAL_DEFAULTS):
     * Document Type explicitly opts in, so shared defaults remain visible.
     */
    public function test_tenant_with_global_defaults_model_includes_null_customer_records(): void
    {
        DocumentType::create(['customer_id' => $this->customerA->id, 'type_code' => 'OWN', 'type_name' => 'Own type', 'status' => 'active']);
        DocumentType::create(['customer_id' => null, 'type_code' => 'SHARED', 'type_name' => 'Shared default', 'status' => 'active']);
        DocumentType::create(['customer_id' => $this->customerB->id, 'type_code' => 'OTHER', 'type_name' => 'Other tenant', 'status' => 'active']);

        $this->actingAs($this->customerUser($this->customerA));

        $codes = DocumentType::pluck('type_code')->all();

        $this->assertContains('OWN', $codes);
        $this->assertContains('SHARED', $codes);
        $this->assertNotContains('OTHER', $codes);
    }

    /**
     * Security review regression (H1): TENANT_WITH_GLOBAL_DEFAULTS is
     * read-only for the null-owned shared record — a tenant "manage" role
     * must not be able to rename/re-own/delete a Document Type every other
     * tenant relies on.
     */
    public function test_shared_global_default_record_is_read_only_to_a_tenant_user(): void
    {
        $shared = DocumentType::create(['customer_id' => null, 'type_code' => 'SHARED', 'type_name' => 'Shared default', 'status' => 'active']);

        $this->actingAs($this->customerUser($this->customerA));

        $this->assertFalse(
            DocumentTypeResource::can('update', $shared),
            'A tenant user must not be able to edit a shared global-default record',
        );
        $this->assertFalse(
            DocumentTypeResource::can('delete', $shared),
            'A tenant user must not be able to delete a shared global-default record',
        );

        $shared->refresh();
        $this->assertNull($shared->customer_id, 'The shared record must not be re-owned by a tenant');
    }

    /**
     * Security review regression (H1): the model-level updating() hook is a
     * second line of defence — even if a write somehow bypassed
     * BaseResource::can(), it must not silently re-own a shared record.
     */
    public function test_updating_a_shared_global_default_record_is_cancelled_at_the_model_layer(): void
    {
        $shared = DocumentType::create(['customer_id' => null, 'type_code' => 'SHARED', 'type_name' => 'Shared default', 'status' => 'active']);

        $this->actingAs($this->customerUser($this->customerA));

        $result = $shared->update(['type_name' => 'Hijacked']);

        $this->assertFalse($result);
        $shared->refresh();
        $this->assertNull($shared->customer_id);
        $this->assertSame('Shared default', $shared->type_name);
    }

    /**
     * Security review regression (H2): a non-platform user with no
     * customer_id is a data-integrity defect — every tenant-scoped guard
     * must fail closed (no rows) rather than fall through to "unscoped".
     */
    public function test_non_platform_user_with_no_customer_id_sees_nothing(): void
    {
        $orphan = User::factory()->create([
            'customer_id' => null,
            'is_platform_user' => false,
            'status' => 'active',
        ]);

        $this->actingAs($orphan);

        $this->assertSame(0, Product::count());
        $this->assertFalse(ProductResource::can('viewAny'));
    }
}
