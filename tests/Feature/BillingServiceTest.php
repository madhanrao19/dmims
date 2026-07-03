<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /** invoice_no/payment_no now come from the concurrency-safe SequenceGenerator
     *  (not count()+1) — assert numbering is sequential and gapless within a year. */
    public function test_invoice_numbers_are_sequential_and_gapless_within_a_year(): void
    {
        $customer = $this->customer();
        $service = app(BillingService::class);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $invoice = $service->createInvoice(['customer_id' => $customer->id, 'amount' => 10]);
            preg_match('/^INV-\d+-(\d+)$/', $invoice->invoice_no, $m);
            $numbers[] = (int) $m[1];
        }

        $this->assertSame([$numbers[0], $numbers[0] + 1, $numbers[0] + 2], $numbers);
    }

    public function test_payment_numbers_are_sequential_and_gapless_within_a_year(): void
    {
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $this->customer()->id, 'amount' => 300]);
        $paymentService = app(PaymentService::class);

        $numbers = [];
        foreach ([100, 100, 100] as $amount) {
            $payment = $paymentService->recordPayment($invoice->refresh(), ['amount' => $amount]);
            preg_match('/^PAY-\d+-(\d+)$/', $payment->payment_no, $m);
            $numbers[] = (int) $m[1];
        }

        $this->assertSame([$numbers[0], $numbers[0] + 1, $numbers[0] + 2], $numbers);
    }

    /** The one-time seeding migration must prevent the new SequenceGenerator-based
     *  numbering from colliding with invoice/payment numbers issued before the switch. */
    public function test_seeding_migration_prevents_invoice_number_collision(): void
    {
        $customer = $this->customer();
        $year = now()->year;

        // Simulate 5 invoices that already exist from before this migration ran
        // (inserted directly, the way historical/pre-migration data would exist).
        for ($i = 1; $i <= 5; $i++) {
            DB::table('billing_records')->insert([
                'customer_id' => $customer->id,
                'invoice_no' => sprintf('INV-%d-%04d', $year, $i),
                'invoice_date' => now()->toDateString(),
                'amount' => 10,
                'tax_amount' => 0,
                'total_amount' => 10,
                'billing_status' => 'draft',
                'payment_status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        (require database_path('migrations/2026_07_03_000000_seed_sequence_counters_for_billing_numbering.php'))->up();

        $next = app(BillingService::class)->generateInvoiceNo();

        $this->assertSame(sprintf('INV-%d-0006', $year), $next);
    }

    public function test_seeding_migration_prevents_payment_number_collision(): void
    {
        $customer = $this->customer();
        $year = now()->year;
        $invoice = app(BillingService::class)->createInvoice(['customer_id' => $customer->id, 'amount' => 1000]);

        for ($i = 1; $i <= 3; $i++) {
            DB::table('billing_payments')->insert([
                'customer_id' => $customer->id,
                'billing_record_id' => $invoice->id,
                'payment_no' => sprintf('PAY-%d-%04d', $year, $i),
                'amount' => 10,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        (require database_path('migrations/2026_07_03_000000_seed_sequence_counters_for_billing_numbering.php'))->up();

        $next = app(PaymentService::class)->generatePaymentNo();

        $this->assertSame(sprintf('PAY-%d-0004', $year), $next);
    }
}
