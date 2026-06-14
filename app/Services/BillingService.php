<?php

namespace App\Services;

use App\Models\BillingLog;
use App\Models\BillingRecord;
use Illuminate\Support\Carbon;

/**
 * Manual billing (PRD §13). Creates invoices, maintains totals and payment
 * status, and writes immutable billing history. No payment gateway (Version 1).
 */
class BillingService
{
    /**
     * Create an invoice. total_amount is always derived as amount + tax_amount.
     */
    public function createInvoice(array $data): BillingRecord
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $tax = round((float) ($data['tax_amount'] ?? 0), 2);

        $record = BillingRecord::create([
            'customer_id' => $data['customer_id'] ?? auth()->user()?->customer_id,
            'invoice_no' => $data['invoice_no'] ?? $this->generateInvoiceNo(),
            'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? null,
            'amount' => $amount,
            'tax_amount' => $tax,
            'total_amount' => round($amount + $tax, 2),
            'billing_status' => $data['billing_status'] ?? 'draft',
            'payment_status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $this->log($record, 'created', null, $record->only(['invoice_no', 'total_amount', 'billing_status']));

        return $record;
    }

    public function issue(BillingRecord $record): BillingRecord
    {
        $record->update(['billing_status' => 'issued']);
        $this->log($record, 'issued');

        return $record;
    }

    public function cancel(BillingRecord $record): BillingRecord
    {
        $record->update(['billing_status' => 'cancelled']);
        $this->log($record, 'cancelled');

        return $record;
    }

    /**
     * Recompute payment_status from the sum of recorded payments.
     */
    public function recalculatePaymentStatus(BillingRecord $record): BillingRecord
    {
        $paid = $record->paidAmount();
        $total = (float) $record->total_amount;

        $status = match (true) {
            $paid <= 0 => 'unpaid',
            $paid + 0.005 >= $total => 'paid',
            default => 'partial',
        };

        $record->update(['payment_status' => $status]);

        return $record;
    }

    /**
     * Append an immutable billing history entry.
     */
    public function log(BillingRecord $record, string $action, ?array $old = null, ?array $new = null, ?string $remarks = null): void
    {
        BillingLog::create([
            'customer_id' => $record->customer_id,
            'billing_record_id' => $record->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'remarks' => $remarks,
            'performed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Sequential invoice number per calendar year: INV-YYYY-0001.
     */
    public function generateInvoiceNo(): string
    {
        $year = Carbon::now()->year;
        $count = BillingRecord::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('INV-%d-%04d', $year, $count + 1);
    }
}
