<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ScannerService::scan() writes customer_id = null when a platform user
 * (no customer_id) scans a barcode that doesn't resolve to any registry —
 * there's no customer to attribute it to. The column was NOT NULL, so this
 * legitimate case threw a constraint violation instead of logging the scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_scan_logs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('barcode_scan_logs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};
