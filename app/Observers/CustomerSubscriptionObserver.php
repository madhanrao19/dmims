<?php

namespace App\Observers;

use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\Module;
use App\Models\SubscriptionLog;
use Illuminate\Support\Facades\Cache;

class CustomerSubscriptionObserver
{
    public function created(CustomerSubscription $subscription): void
    {
        $this->syncEnabledModules($subscription);
        $this->forgetSubscriptionCache($subscription);
        $this->log($subscription, 'created', null, $subscription->only(['subscription_no', 'status', 'valid_to']));
    }

    public function updated(CustomerSubscription $subscription): void
    {
        $this->syncEnabledModules($subscription);
        $this->forgetSubscriptionCache($subscription);

        $changes = $subscription->getChanges();
        unset($changes['updated_at']);

        if ($changes) {
            $this->log(
                $subscription,
                'updated',
                array_intersect_key($subscription->getOriginal(), $changes),
                $changes,
            );
        }
    }

    public function deleted(CustomerSubscription $subscription): void
    {
        $this->forgetSubscriptionCache($subscription);
        $this->log($subscription, 'deleted');
    }

    protected function log(CustomerSubscription $subscription, string $action, ?array $old = null, ?array $new = null): void
    {
        SubscriptionLog::create([
            'customer_id' => $subscription->customer_id,
            'customer_subscription_id' => $subscription->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'performed_by' => auth()->id(),
            'created_at' => now(),
        ]);
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
