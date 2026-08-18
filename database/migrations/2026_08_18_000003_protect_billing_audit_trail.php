<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_records had no soft delete, yet billing_payments/billing_logs
 * cascade-delete off it — a single hard delete on billing_records silently
 * destroyed the payment history and the "immutable, append-only" billing
 * log (DBA review finding, Critical #3). Adds soft deletes to
 * billing_records (additive column, no data impact) and changes the two
 * child FKs from cascade to restrict, so a hard delete is blocked while
 * payments/log entries exist instead of silently wiping them. Nothing in
 * the app currently issues a hard delete on billing_records (no Filament
 * delete action is wired for it), so this cannot break existing behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_records', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            $table->dropForeign(['billing_record_id']);
            $table->foreign('billing_record_id')->references('id')->on('billing_records')->restrictOnDelete();
        });

        Schema::table('billing_logs', function (Blueprint $table) {
            $table->dropForeign(['billing_record_id']);
            $table->foreign('billing_record_id')->references('id')->on('billing_records')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            $table->dropForeign(['billing_record_id']);
            $table->foreign('billing_record_id')->references('id')->on('billing_records')->cascadeOnDelete();
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            $table->dropForeign(['billing_record_id']);
            $table->foreign('billing_record_id')->references('id')->on('billing_records')->cascadeOnDelete();
        });

        Schema::table('billing_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
