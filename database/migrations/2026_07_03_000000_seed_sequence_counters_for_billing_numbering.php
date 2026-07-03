<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time seed so BillingService/PaymentService's switch from count()+1 to
 * SequenceGenerator (a row-locked counter) doesn't collide with or reuse
 * invoice_no/payment_no values already issued. For each year present in the
 * data, seeds sequence_counters with the max of (a) the row count for that
 * year and (b) the highest numeric suffix parsed out of existing
 * invoice_no/payment_no values — the latter guards against any historical
 * record that deviated from strict append order (e.g. a hard delete).
 *
 * Years are computed in PHP (not via driver-specific SQL like YEAR()/strftime)
 * so this runs identically on SQLite (tests) and MySQL/MariaDB (production).
 */
return new class extends Migration
{
    private const PATTERNS = [
        'billing_records' => ['number_column' => 'invoice_no', 'key_prefix' => 'invoice', 'regex' => '/^INV-\d+-(\d+)$/'],
        'billing_payments' => ['number_column' => 'payment_no', 'key_prefix' => 'payment', 'regex' => '/^PAY-\d+-(\d+)$/'],
    ];

    public function up(): void
    {
        foreach (self::PATTERNS as $table => $spec) {
            $rows = DB::table($table)->select(['created_at', $spec['number_column']])->get();

            $byYear = $rows->groupBy(fn ($row) => Carbon::parse($row->created_at)->year);

            foreach ($byYear as $year => $yearRows) {
                $countForYear = $yearRows->count();

                $parsedMax = 0;
                foreach ($yearRows as $row) {
                    $number = $row->{$spec['number_column']};
                    if ($number && preg_match($spec['regex'], $number, $m)) {
                        $parsedMax = max($parsedMax, (int) $m[1]);
                    }
                }

                $seed = max($countForYear, $parsedMax);

                if ($seed > 0) {
                    DB::table('sequence_counters')->updateOrInsert(
                        ['key' => "{$spec['key_prefix']}:{$year}"],
                        ['value' => $seed, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $keys = [];

        foreach (self::PATTERNS as $table => $spec) {
            $years = DB::table($table)->pluck('created_at')
                ->map(fn ($createdAt) => Carbon::parse($createdAt)->year)
                ->unique();

            foreach ($years as $year) {
                $keys[] = "{$spec['key_prefix']}:{$year}";
            }
        }

        if ($keys !== []) {
            DB::table('sequence_counters')->whereIn('key', $keys)->delete();
        }
    }
};
