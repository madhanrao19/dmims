<?php

namespace App\Console\Commands;

use App\Models\BillingRecord;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\StockAlert;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generates proactive operational alerts (PRD §16 / TDD §24; Business Rules
 * §25): low stock, subscription/license expiry, overdue billing, and
 * overdue returns. Idempotent — uses notifyOnce so repeated runs do not
 * duplicate open alerts. ("Payment updates" and "Import failures"/"Export
 * completion" from the same doc section are event-driven instead — fired
 * from PaymentService/ImportService/ExportService at the point of the
 * event, not scanned for here.)
 */
class GenerateNotifications extends Command
{
    protected $signature = 'dmims:generate-notifications {--expiry-days=14 : Days ahead to warn about expiries}';

    protected $description = 'Generate operational notifications (low stock, expiries, overdue billing/returns).';

    public function handle(NotificationService $notifications): int
    {
        $days = (int) $this->option('expiry-days');

        $created = $this->lowStock($notifications)
            + $this->subscriptionExpiry($notifications, $days)
            + $this->licenseExpiry($notifications, $days)
            + $this->billingOverdue($notifications)
            + $this->overdueReturns($notifications);

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

                        // The StockAlertResource admin screen reads this table
                        // directly — it was never written to (dead feature),
                        // only the generic Notification above fired.
                        StockAlert::withoutGlobalScopes()->updateOrCreate(
                            [
                                'customer_id' => $product->customer_id,
                                'product_id' => $product->id,
                                'alert_type' => 'low_stock',
                                'status' => 'open',
                            ],
                            [
                                'threshold_quantity' => $product->reorder_level,
                                'current_quantity' => $available,
                            ],
                        );
                    } else {
                        // Stock recovered above the reorder level — close any
                        // open alert for this product rather than leaving it
                        // stale forever.
                        StockAlert::withoutGlobalScopes()
                            ->where('customer_id', $product->customer_id)
                            ->where('product_id', $product->id)
                            ->where('alert_type', 'low_stock')
                            ->where('status', 'open')
                            ->update(['status' => 'closed', 'current_quantity' => $available]);
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

    /** Business Rules §25: "Overdue returns" — a moved-out file whose
     *  due_date has passed and hasn't been returned. Boxes have no
     *  borrow/due_date concept in the schema, only files do. */
    private function overdueReturns(NotificationService $n): int
    {
        $count = 0;

        DocumentFile::withoutGlobalScopes()
            ->where('current_status', 'moved_out')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->get()
            ->each(function ($file) use ($n, &$count) {
                $date = Carbon::parse($file->due_date)->toDateString();
                $made = $n->notifyOnce(
                    'overdue_return',
                    "Overdue return: {$file->title}",
                    "File {$file->file_barcode} was due back on {$date}".
                    ($file->borrowed_by ? " (borrowed by {$file->borrowed_by})" : '').'.',
                    $file->customer_id,
                );
                $count += $made ? 1 : 0;
            });

        return $count;
    }
}
