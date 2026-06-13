<?php

namespace App\Http\Middleware;

use App\Models\CustomerSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->customer_id && ! $this->hasActiveSubscription($user->customer_id)) {
            abort(403, 'Subscription is not active.');
        }

        return $next($request);
    }

    /**
     * Whether the customer has an active subscription. Cached briefly so this
     * does not run a database query on every single request (this middleware
     * is in the global web group). A short TTL keeps revocation near-real-time.
     */
    protected function hasActiveSubscription(int $customerId): bool
    {
        return Cache::remember("subscription_active:{$customerId}", now()->addSeconds(60), function () use ($customerId) {
            return CustomerSubscription::where('customer_id', $customerId)
                ->whereIn('status', ['active', 'near_expiry'])
                ->where(function ($query) {
                    $query->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', Carbon::now());
                })
                ->exists();
        });
    }
}
