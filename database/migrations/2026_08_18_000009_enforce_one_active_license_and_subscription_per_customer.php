<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nothing enforced "one active license/subscription per customer" at the DB
 * layer, despite the Database Dictionary documenting it as the expected
 * shape (DBA review finding, Medium #7). Adds a generated column that's
 * only non-null when status = 'active', with a unique index on it — MySQL/
 * MariaDB/SQLite all exclude NULLs from a unique index, so this enforces
 * "at most one active row per customer" without constraining non-active
 * rows at all.
 *
 * Guarded: if any customer already has more than one active license (or
 * subscription), skip adding that table's constraint and log it — existing
 * duplicates need a data decision this migration can't safely make.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->enforce('licenses');
        $this->enforce('customer_subscriptions');
    }

    private function enforce(string $table): void
    {
        $violations = DB::table($table)
            ->select('customer_id')
            ->where('status', 'active')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('customer_id');

        if ($violations->isNotEmpty()) {
            logger()->warning('migration.multiple_active_rows_found', [
                'table' => $table,
                'customer_ids' => $violations->all(),
            ]);

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('active_customer_id')
                ->nullable()
                ->virtualAs("CASE WHEN status = 'active' THEN customer_id ELSE NULL END");
        });

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unique('active_customer_id');
        });
    }

    public function down(): void
    {
        foreach (['licenses', 'customer_subscriptions'] as $table) {
            if (Schema::hasColumn($table, 'active_customer_id')) {
                // SQLite doesn't drop the associated index automatically when
                // the column is dropped (unlike MySQL/MariaDB) — drop it
                // explicitly first, in its own schema call.
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropUnique(['active_customer_id']);
                });

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('active_customer_id');
                });
            }
        }
    }
};
