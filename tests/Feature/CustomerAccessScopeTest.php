<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\LicenseResource;
use App\Filament\Resources\SubscriptionPlanResource;
use App\Filament\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security & Access Control Matrix §3.2 (TENANT_STRICT) and §5–§8:
 * regression coverage for CONFORMANCE_GAP_ANALYSIS.md's "Required
 * Regression Tests" (24 August 2026 access-control review).
 */
class CustomerAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private function companyAdmin(Customer $customer): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Admin');

        return $user;
    }

    public function test_company_admin_cannot_see_other_customer_or_platform_null_users(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $admin = $this->companyAdmin($customerA);

        User::factory()->create(['customer_id' => $customerB->id, 'is_platform_user' => false, 'status' => 'active']);
        $platformUser = User::factory()->create(['customer_id' => null, 'is_platform_user' => true, 'status' => 'active']);

        $this->actingAs($admin);

        $ids = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertNotContains($platformUser->id, $ids);
        $this->assertFalse(UserResource::can('view', $platformUser), 'Direct-ID access to a platform user must be denied');
    }

    public function test_company_admin_sees_only_own_customer_audit_logs(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);
        $admin = $this->companyAdmin($customerA);

        $ownLog = AuditLog::create(['customer_id' => $customerA->id, 'module' => 'test', 'action' => 'own']);
        $otherLog = AuditLog::create(['customer_id' => $customerB->id, 'module' => 'test', 'action' => 'other']);
        $platformLog = AuditLog::create(['customer_id' => null, 'module' => 'test', 'action' => 'platform']);

        $this->actingAs($admin);

        $ids = AuditLogResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($ownLog->id, $ids);
        $this->assertNotContains($otherLog->id, $ids);
        $this->assertNotContains($platformLog->id, $ids);
        $this->assertFalse(AuditLogResource::can('view', $platformLog));
    }

    public function test_customer_user_cannot_browse_subscription_plans(): void
    {
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $plan = SubscriptionPlan::create(['plan_code' => 'P1', 'plan_name' => 'Plan One', 'billing_cycle' => 'yearly', 'status' => 'active']);

        $this->actingAs($admin);

        $this->assertFalse(SubscriptionPlanResource::can('viewAny'));
        $this->assertFalse(SubscriptionPlanResource::can('view', $plan));
    }

    public function test_customer_user_cannot_open_license_management(): void
    {
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);
        $license = License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-1',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $this->assertFalse(LicenseResource::can('viewAny'));
        $this->assertFalse(LicenseResource::can('view', $license), 'Even the customer\'s own license must not be reachable via the admin resource');
    }

    public function test_global_search_does_not_expose_platform_only_resources_to_customer(): void
    {
        // Filament's global search dispatcher gates each resource on
        // canGloballySearch(), which delegates to canAccess() -> can('viewAny')
        // -> our $platformOnly check — this is what actually keeps Subscription
        // Plans/License Management out of a customer's search results.
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $admin = $this->companyAdmin($customer);

        $this->actingAs($admin);

        $this->assertFalse(SubscriptionPlanResource::canGloballySearch());
        $this->assertFalse(LicenseResource::canGloballySearch());
    }

    public function test_platform_user_retains_full_access(): void
    {
        $customer = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $platformUser = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $plan = SubscriptionPlan::create(['plan_code' => 'P1', 'plan_name' => 'Plan One', 'billing_cycle' => 'yearly', 'status' => 'active']);
        $license = License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-1',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);
        $platformLog = AuditLog::create(['customer_id' => null, 'module' => 'test', 'action' => 'platform']);

        $this->actingAs($platformUser);

        $this->assertTrue(SubscriptionPlanResource::can('viewAny'));
        $this->assertTrue(LicenseResource::can('viewAny'));
        $this->assertTrue(LicenseResource::can('view', $license));
        $this->assertTrue(AuditLogResource::can('view', $platformLog));
        $this->assertContains($plan->id, SubscriptionPlanResource::getEloquentQuery()->pluck('id')->all());
    }
}
