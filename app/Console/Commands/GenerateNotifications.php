<?php

namespace App\Console\Commands;

use App\Models\BillingRecord;
use App\Models\CustomerSubscription;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generates proactive operational alerts (PRD §16 / TDD §24): low stock,
 * subscription/license expiry and overdue billing. Idempotent — uses
 * notifyOnce so repeated runs do not duplicate open alerts.
 */
class GenerateNotifications extends Command
{
    protected $signature = 'dmims:generate-notifications {--expiry-days=14 : Days ahead to warn about expiries}';

    protected $description = 'Generate operational notifications (low stock, expiries, overdue billing).';

    public function handle(NotificationService $notifications): int
    {
        $days = (int) $this->option('expiry-days');

        $created = $this->lowStock($notifications)
            + $this->subscriptionExpiry($notifications, $days)
            + $this->licenseExpiry($notifications, $days)
            + $this->billingOverdue($notifications);

        $this->info("Generated {$created} notification(s).");

        return self::SUCCESS;
    }

    private function lowStock(NotificationService $n): int
    {
        $count = 0;

        Product::withoutGlobalScopes()
            ->where('reorder_level', '>', 0)
            ->chunkById(200, function ($products) use ($n, &$count) {
                foreach ($products as $product) {
                    $available = (float) ProductLocationStock::withoutGlobalScopes()
                        ->where('product_id', $product->id)
                        ->sum('available_quantity');

                    if ($available <= (float) $product->reorder_level) {
                        $made = $n->notifyOnce(
                            'low_stock',
                            "Low stock: {$product->product_name}",
                            "Available quantity {$available} is at or below the reorder level {$product->reorder_level}.",
                            $product->customer_id,
                        );
                        $count += $made ? 1 : 0;
                    }
                }
            });

        return $count;
    }

    private function subscriptionExpiry(NotificationService $n, int $days): int
    {
        $count = 0;

        CustomerSubscription::withoutGlobalScopes()
            ->whereIn('status', ['active', 'near_expiry', 'trial'])
            ->whereNotNull('valid_to')
            ->whereBetween('valid_to', [Carbon::today(), Carbon::today()->addDays($days)])
            ->get()
            ->each(function ($sub) use ($n, &$count) {
                $date = Carbon::parse($sub->valid_to)->toDateString();
                $made = $n->notifyOnce(
                    'subscription_expiry',
                    "Subscription expiring on {$date}",
                    "The subscription {$sub->subscription_no} expires on {$date}.",
                    $sub->customer_id,
                );
                $count += $made ? 1 : 0;
            });

        return $count;
    }

    private function licenseExpiry(NotificationService $n, int $days): int
    {
        $count = 0;

        License::withoutGlobalScopes()
            ->whereIn('status', ['active', 'near_expiry', 'trial'])
            ->whereNotNull('valid_to')
            ->whereBetween('valid_to', [Carbon::today(), Carbon::today()->addDays($days)])
            ->get()
            ->each(function ($license) use ($n, &$count) {
                $date = Carbon::parse($license->valid_to)->toDateString();
                $made = $n->notifyOnce(
                    'license_expiry',
                    "License expiring on {$date}",
                    "The license {$license->license_no} expires on {$date}.",
                    $license->customer_id,
                );
                $count += $made ? 1 : 0;
            });

        return $count;
    }

    private function billingOverdue(NotificationService $n): int
    {
        $count = 0;

        BillingRecord::withoutGlobalScopes()
            ->where('billing_status', 'issued')
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->get()
            ->each(function ($invoice) use ($n, &$count) {
                $made = $n->notifyOnce(
                    'billing_overdue',
                    "Invoice {$invoice->invoice_no} overdue",
                    "Invoice {$invoice->invoice_no} was due on ".Carbon::parse($invoice->due_date)->toDateString().
                    " and has an outstanding balance of {$invoice->outstandingAmount()}.",
                    $invoice->customer_id,
                );
                $count += $made ? 1 : 0;
            });

        return $count;
    }
}
