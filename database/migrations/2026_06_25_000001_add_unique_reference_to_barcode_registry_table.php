<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Without this, two concurrent BarcodeService::registerFor() calls for the
 * same record could both pass the "already registered" check and insert two
 * registry rows. The unique index makes the second insert fail instead of
 * silently duplicating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->unique(['reference_table', 'reference_id'], 'barcode_registry_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('barcode_registry', function (Blueprint $table) {
            $table->dropUnique('barcode_registry_reference_unique');
        });
    }
};
