<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    public function isSubscriptionActive($subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        return in_array($subscription->status, ['active', 'near_expiry'], true)
            && (! $subscription->valid_to || $subscription->valid_to->isFuture() || $subscription->valid_to->isToday());
    }

    public function getActiveSubscription(int $customerId): ?CustomerSubscription
    {
        return CustomerSubscription::where('customer_id', $customerId)
            ->whereIn('status', ['active', 'near_expiry'])
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', Carbon::now());
            })
            ->orderByDesc('valid_to')
            ->first();
    }
}
