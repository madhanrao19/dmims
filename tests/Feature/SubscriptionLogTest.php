<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionLog;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLogTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): CustomerSubscription
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $plan = SubscriptionPlan::create([
            'plan_code' => 'p1', 'plan_name' => 'Plan 1', 'price' => 0, 'billing_cycle' => 'monthly', 'status' => 'active',
        ]);

        return CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'subscription_no' => 'SUB-1',
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function test_creating_a_subscription_writes_a_log(): void
    {
        $sub = $this->subscription();

        $this->assertDatabaseHas('subscription_logs', [
            'customer_subscription_id' => $sub->id,
            'action' => 'created',
        ]);
    }

    public function test_updating_a_subscription_logs_the_change(): void
    {
        $sub = $this->subscription();
        $sub->update(['status' => 'suspended']);

        $log = SubscriptionLog::where('customer_subscription_id', $sub->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('suspended', $log->new_values['status']);
        $this->assertSame('active', $log->old_values['status']);
    }
}
