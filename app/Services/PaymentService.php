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
    public function __construct(private BillingService $billing) {}

    public function recordPayment(BillingRecord $record, array $data): BillingPayment
    {
        // Defence-in-depth behind the UI gate: never record a payment against a
        // cancelled invoice.
        if ($record->billing_status === 'cancelled') {
            throw new RuntimeException("Cannot record a payment against cancelled invoice {$record->invoice_no}.");
        }

        $payment = BillingPayment::create([
            'customer_id' => $record->customer_id,
            'billing_record_id' => $record->id,
            'payment_no' => $data['payment_no'] ?? $this->generatePaymentNo(),
            'amount' => round((float) $data['amount'], 2),
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

        return $payment;
    }

    public function generatePaymentNo(): string
    {
        $year = Carbon::now()->year;
        $seq = SequenceGenerator::next("payment:{$year}");

        return sprintf('PAY-%d-%04d', $year, $seq);
    }
}
