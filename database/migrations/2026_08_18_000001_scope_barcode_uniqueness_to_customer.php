<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * boxes.box_barcode/box_number and document_files.file_barcode were
 * globally unique instead of unique-per-tenant (DBA review finding, Critical
 * #2). Safe to apply as-is: a value that was already unique across the whole
 * table is necessarily unique within any subset of it, so narrowing to a
 * (customer_id, column) unique index cannot conflict with existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropUnique(['box_barcode']);
            $table->dropUnique(['box_number']);
            $table->unique(['customer_id', 'box_barcode']);
            $table->unique(['customer_id', 'box_number']);
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->dropUnique(['file_barcode']);
            $table->unique(['customer_id', 'file_barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'box_barcode']);
            $table->dropUnique(['customer_id', 'box_number']);
            $table->unique('box_barcode');
            $table->unique('box_number');
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'file_barcode']);
            $table->unique('file_barcode');
        });
    }
};
