<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unique(reference_table, reference_id) constraint added to prevent
 * double-registration races also blocks a legitimate operation: replacing a
 * lost/damaged barcode, which retires the old registry row and issues a new
 * one for the same record. A plain (non-unique) index already exists for
 * lookups, so this only removes the constraint, not the index.
 *
 * ponytail: without this constraint, two concurrent *first-ever*
 * registrations of the same brand-new record could in theory both pass the
 * "no active registration yet" check and insert two active rows. The
 * transaction + lockForUpdate + re-check in BarcodeService::registerFor()
 * covers the realistic case (registerFor is called once, synchronously,
 * right after a record is created). If concurrent first-registration is
 * ever observed in practice, the upgrade path is a partial unique index on
 * (reference_table, reference_id) WHERE status = 'active'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->dropUnique('barcode_registry_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->unique(['reference_table', 'reference_id'], 'barcode_registry_reference_unique');
        });
    }
};
