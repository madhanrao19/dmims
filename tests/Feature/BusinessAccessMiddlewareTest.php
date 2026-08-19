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

    /** Business Rules §7: "A subscription does not directly determine whether
     *  the customer can technically use the system" — trial and expired_grace
     *  are legitimate operating states and must not be hard-blocked here. */
    public function test_user_with_trial_or_expired_grace_subscription_is_not_blocked(): void
    {
        foreach (['trial', 'expired_grace'] as $status) {
            $customer = Customer::create([
                'company_name' => "Co-{$status}",
                'company_code' => strtoupper(substr($status, 0, 3)).rand(100, 999),
                'status' => 'active',
            ]);

            CustomerSubscription::create([
                'customer_id' => $customer->id,
                'subscription_no' => "SUB-{$status}",
                'valid_from' => now()->subMonth(),
                'valid_to' => now()->addMonth(),
                'status' => $status,
            ]);

            $user = User::factory()->create([
                'customer_id' => $customer->id,
                'is_platform_user' => false,
                'status' => 'active',
            ]);

            $response = $this->actingAs($user)->get('/admin');

            $response->assertStatus(200, "Expected status 200 for subscription status '{$status}'");
        }
    }

    /** Business Rules §4: same reasoning as EnsureCompanyActive's fix — only
     *  Cancelled/Archived companies are blocked; Trial/Active/Near
     *  Expiry/Expired/Suspended all get through this middleware (further
     *  restriction, if any, happens via the license layer). This previously
     *  hard-blocked every non-'active' company status on every request. */
    public function test_user_access_across_company_statuses(): void
    {
        $allowed = ['trial', 'active', 'near_expiry', 'expired', 'suspended'];
        $blocked = ['cancelled', 'archived'];

        foreach ([...$allowed, ...$blocked] as $status) {
            $customer = Customer::create([
                'company_name' => "Co-{$status}",
                'company_code' => strtoupper(substr($status, 0, 3)).rand(100, 999),
                'status' => $status,
            ]);

            CustomerSubscription::create([
                'customer_id' => $customer->id,
                'subscription_no' => "SUB-{$status}",
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

            $expected = in_array($status, $allowed, true) ? 200 : 403;
            $response->assertStatus($expected, "Expected {$expected} for company status '{$status}'");
        }
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
