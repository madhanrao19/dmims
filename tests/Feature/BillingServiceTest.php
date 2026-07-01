<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    public function test_create_invoice_derives_total_and_numbers_sequentially(): void
    {
        $customer = $this->customer();

        $invoice = app(BillingService::class)->createInvoice([
            'customer_id' => $customer->id,
            'amount' => 100,
            'tax_amount' => 10,
        ]);

        $this->assertSame('110.00', $invoice->total_amount);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertStringStartsWith('INV-', $invoice->invoice_no);
        $this->assertDatabaseHas('billing_logs', ['billing_record_id' => $invoice->id, 'action' => 'created']);

        $second = app(BillingService::class)->createInvoice(['customer_id' => $customer->id, 'amount' => 5]);
        $this->assertNotSame($invoice->invoice_no, $second->invoice_no);
    }

    public function test_recording_payments_updates_status_and_outstanding(): void
    {
        $customer = $this->customer();
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $customer->id, 'amount' => 100]);

        app(PaymentService::class)->recordPayment($invoice, ['amount' => 40, 'payment_method' => 'cash']);
        $invoice->refresh();
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertEqualsWithDelta(60.0, $invoice->outstandingAmount(), 0.001);

        app(PaymentService::class)->recordPayment($invoice, ['amount' => 60]);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEqualsWithDelta(0.0, $invoice->outstandingAmount(), 0.001);
        $this->assertDatabaseHas('billing_logs', ['billing_record_id' => $invoice->id, 'action' => 'payment_recorded']);
    }

    public function test_issue_and_cancel_transitions(): void
    {
        $customer = $this->customer();
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $customer->id, 'amount' => 50]);

        app(BillingService::class)->issue($invoice);
        $this->assertSame('issued', $invoice->refresh()->billing_status);

        app(BillingService::class)->cancel($invoice);
        $this->assertSame('cancelled', $invoice->refresh()->billing_status);
    }

    public function test_cannot_pay_a_cancelled_invoice(): void
    {
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $this->customer()->id, 'amount' => 50]);
        app(BillingService::class)->cancel($invoice);

        $this->expectException(\RuntimeException::class);
        app(PaymentService::class)->recordPayment($invoice->refresh(), ['amount' => 50]);
    }

    public function test_cannot_issue_a_non_draft_invoice(): void
    {
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $this->customer()->id, 'amount' => 50]);
        app(BillingService::class)->issue($invoice);

        $this->expectException(\RuntimeException::class);
        app(BillingService::class)->issue($invoice->refresh());
    }
}
