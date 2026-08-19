<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\BillingRecord;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Records manual payments against invoices and keeps the invoice payment status
 * in sync (PRD §13 — no online gateway in Version 1).
 */
class PaymentService
{
    public function __construct(
        private BillingService $billing,
        private NotificationService $notifications,
    ) {}

    public function recordPayment(BillingRecord $record, array $data): BillingPayment
    {
        // Defence-in-depth behind the UI gate: never record a payment against a
        // cancelled or already-fully-paid invoice.
        if ($record->billing_status === 'cancelled') {
            throw new RuntimeException("Cannot record a payment against cancelled invoice {$record->invoice_no}.");
        }

        if ($record->payment_status === 'paid') {
            throw new RuntimeException("Invoice {$record->invoice_no} is already fully paid.");
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException("Payment amount must be greater than zero for invoice {$record->invoice_no}.");
        }

        $outstanding = $record->outstandingAmount();

        if ($amount > $outstanding) {
            throw new RuntimeException("Payment of {$amount} exceeds the outstanding balance of {$outstanding} on invoice {$record->invoice_no}.");
        }

        $payment = BillingPayment::create([
            'customer_id' => $record->customer_id,
            'billing_record_id' => $record->id,
            'payment_no' => $data['payment_no'] ?? $this->generatePaymentNo(),
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'reference_no' => $data['reference_no'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        $this->billing->recalculatePaymentStatus($record->refresh());
        $this->billing->log($record, 'payment_recorded', null, [
            'payment_no' => $payment->payment_no,
            'amount' => $payment->amount,
            'method' => $payment->payment_method,
        ]);

        // Business Rules §25: "Payment updates" is one of the documented
        // notification triggers. Event-driven (not a scheduled scan like
        // low-stock/expiry) since a payment is a discrete, one-off event.
        $this->notifications->notify(
            'payment_recorded',
            "Payment received: {$record->invoice_no}",
            "Payment {$payment->payment_no} of {$payment->amount} recorded against invoice {$record->invoice_no}.",
            $record->customer_id,
        );

        return $payment;
    }

    public function generatePaymentNo(): string
    {
        $year = Carbon::now()->year;
        $seq = SequenceGenerator::next("payment:{$year}");

        return sprintf('PAY-%d-%04d', $year, $seq);
    }
}
