<?php

namespace App\Observers;

use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\Module;
use Illuminate\Support\Facades\Cache;

class CustomerSubscriptionObserver
{
    public function created(CustomerSubscription $subscription): void
    {
        $this->syncEnabledModules($subscription);
        $this->forgetSubscriptionCache($subscription);
    }

    public function updated(CustomerSubscription $subscription): void
    {
        $this->syncEnabledModules($subscription);
        $this->forgetSubscriptionCache($subscription);
    }

    public function deleted(CustomerSubscription $subscription): void
    {
        $this->forgetSubscriptionCache($subscription);
    }

    protected function forgetSubscriptionCache(CustomerSubscription $subscription): void
    {
        Cache::forget("subscription_active:{$subscription->customer_id}");
    }

    protected function syncEnabledModules(CustomerSubscription $subscription): void
    {
        $enabled = $subscription->enabled_modules ?: [];

        // Ensure enabled modules exist as CustomerModule rows
        $moduleIds = Module::whereIn('module_code', $enabled)->pluck('id')->all();

        // Enable listed modules
        foreach ($moduleIds as $moduleId) {
            CustomerModule::updateOrCreate(
                ['customer_id' => $subscription->customer_id, 'module_id' => $moduleId],
                ['is_enabled' => true, 'enabled_at' => now()]
            );
        }

        // Disable modules not in the enabled list
        CustomerModule::where('customer_id', $subscription->customer_id)
            ->whereNotIn('module_id', $moduleIds)
            ->update(['is_enabled' => false, 'disabled_at' => now()]);
    }
}
