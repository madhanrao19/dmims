<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the business-access middleware group (SetCompanyContext,
 * EnsureUserIsActive, EnsureCompanyAssigned, EnsureCompanyActive,
 * EnsureSubscriptionActive, EnsureLicenseAllowsAccess). These previously read
 * auth()->user() from the GLOBAL middleware stack, which runs before the
 * Filament panel's own StartSession — so auth()->user() was always null and
 * every one of these checks silently no-op'd on every /admin request. They now
 * run as a named group attached inside FilamentPanelProvider (panel) and
 * routes/api.php (API), after authentication.
 */
class BusinessAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_lapsed_subscription_is_denied_admin_access(): void
    {
        $customer = Customer::create(['company_name' => 'Lapsed Co', 'company_code' => 'LPS', 'status' => 'active']);

        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-LPS',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(), // lapsed yesterday
            'status' => 'cancelled',
        ]);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_user_with_active_subscription_is_not_blocked(): void
    {
        $customer = Customer::create(['company_name' => 'Active Co', 'company_code' => 'ACT', 'status' => 'active']);

        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-ACT',
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
    }
}
