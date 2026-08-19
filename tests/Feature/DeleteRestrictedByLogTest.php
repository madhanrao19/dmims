<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerSubscriptionResource\Pages\EditCustomerSubscription;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for the shared EditRecord Delete button: deleting a
 * CustomerSubscription that already has a SubscriptionLog row threw a raw,
 * unstyled 500 (Illuminate\Database\QueryException, SQLSTATE 23000) instead
 * of a normal in-app failure notification. subscription_logs.
 * customer_subscription_id deliberately restrictOnDelete's its parent (see
 * 2026_08_18_000006_restrict_subscription_logs_cascade.php) so the audit
 * trail can't be silently wiped — the record correctly staying undeleted was
 * never the bug, only how that failure reached the user.
 */
class DeleteRestrictedByLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_deleting_a_subscription_with_log_history_fails_gracefully_instead_of_500(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $plan = SubscriptionPlan::create([
            'plan_code' => 'p1', 'plan_name' => 'Plan 1', 'price' => 0, 'billing_cycle' => 'monthly', 'status' => 'active',
        ]);
        $subscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'subscription_no' => 'SUB-1',
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
        ]);

        // The observer that writes SubscriptionLog on create already leaves
        // a restricting row behind, so no extra setup is needed here.
        $this->assertDatabaseHas('subscription_logs', ['customer_subscription_id' => $subscription->id]);

        Livewire::test(EditCustomerSubscription::class, ['record' => $subscription->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseHas('customer_subscriptions', ['id' => $subscription->id]);
    }
}
