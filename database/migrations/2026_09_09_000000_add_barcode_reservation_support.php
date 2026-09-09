<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barcode pre-printing/reservation: a registry row can now exist before any
 * record claims it — status 'unused', reference_table/reference_id null.
 * BarcodeService::reserve() creates these; claim() attaches a real record to
 * one when it's created with a matching barcode.
 *
 * The per-customer barcode uniqueness policy is unchanged — see
 * 2026_08_18_000001_scope_barcode_uniqueness_to_customer.php, which already
 * established per-customer (not global) uniqueness as this project's
 * deliberate tenant-data policy. This migration only adds the "not yet
 * claimed" state; it does not touch the existing unique(customer_id, barcode)
 * index.
 *
 * barcode_scan_logs.scan_result also gains 'unused' — ScannerService::scan()
 * needs to log scanning a reserved-but-unclaimed label distinctly from
 * 'inactive', so it can redirect to the matching create form instead of
 * showing a dead-end message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->string('reference_table')->nullable()->change();
            $table->unsignedBigInteger('reference_id')->nullable()->change();
            $table->enum('status', ['active', 'inactive', 'retired', 'unused'])->default('active')->change();
        });

        Schema::table('barcode_scan_logs', function (Blueprint $table) {
            $table->enum('scan_result', ['found', 'unknown', 'inactive', 'permission_denied', 'unused'])->default('found')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('barcode_registry')->where('status', 'unused')->exists()) {
            throw new RuntimeException('Cannot roll back: barcode_registry has unused (reserved, unclaimed) rows — reference_table/reference_id would become non-nullable while still null.');
        }

        if (DB::table('barcode_scan_logs')->where('scan_result', 'unused')->exists()) {
            throw new RuntimeException('Cannot roll back: barcode_scan_logs has rows logged with scan_result = unused, which the narrower enum no longer allows.');
        }

        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->string('reference_table')->nullable(false)->change();
            $table->unsignedBigInteger('reference_id')->nullable(false)->change();
            $table->enum('status', ['active', 'inactive', 'retired'])->default('active')->change();
        });

        Schema::table('barcode_scan_logs', function (Blueprint $table) {
            $table->enum('scan_result', ['found', 'unknown', 'inactive', 'permission_denied'])->default('found')->change();
        });
    }
};
