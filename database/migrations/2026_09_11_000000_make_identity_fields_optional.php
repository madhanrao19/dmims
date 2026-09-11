<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barcode-scan quick-create lets an operator save a Box/Document File/Location
 * as an empty shell (scan first, fill details later) — these columns were
 * NOT NULL, which blocked that. current_location_id/current_box_id are
 * already nullable (2026_06_14_000003_make_placement_columns_nullable.php);
 * this covers the remaining identity columns. Per-customer unique() rules on
 * the barcode/number/code columns are unaffected: MySQL/MariaDB/SQLite all
 * allow multiple NULLs in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->string('file_barcode', 150)->nullable()->change();
            $table->string('title')->nullable()->change();
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->string('box_barcode', 150)->nullable()->change();
            $table->string('box_number', 100)->nullable()->change();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code')->nullable()->change();
            $table->string('location_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->string('file_barcode', 150)->nullable(false)->change();
            $table->string('title')->nullable(false)->change();
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->string('box_barcode', 150)->nullable(false)->change();
            $table->string('box_number', 100)->nullable(false)->change();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code')->nullable(false)->change();
            $table->string('location_name')->nullable(false)->change();
        });
    }
};
