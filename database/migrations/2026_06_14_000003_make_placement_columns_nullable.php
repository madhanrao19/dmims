<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A moved-out box has no location and a moved-out file has no box (TDD §20:
 * items can leave the system). The original schema made these foreign keys
 * NOT NULL, which prevented move-out — relax them to nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('current_location_id')->nullable()->change();
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->foreignId('current_box_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Once any box/file has actually been moved out, a NULL row exists
        // by design (that's the entire point of this migration) and
        // re-applying NOT NULL would fail with a raw DB constraint error.
        // Fail fast with a clear, actionable message instead (DBA review
        // finding, Low) — there's no safe automatic choice of location/box
        // to backfill those rows with; that's a business decision, not
        // something this migration can make on its own.
        if (DB::table('boxes')->whereNull('current_location_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: one or more boxes have current_location_id = NULL '.
                '(i.e. have been moved out). Reassign them to a location before rolling back, '.
                'or accept that this migration is one-way once move-out has been used.'
            );
        }

        if (DB::table('document_files')->whereNull('current_box_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: one or more document_files have current_box_id = NULL '.
                '(i.e. have been moved out). Reassign them to a box before rolling back, '.
                'or accept that this migration is one-way once move-out has been used.'
            );
        }

        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('current_location_id')->nullable(false)->change();
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->foreignId('current_box_id')->nullable(false)->change();
        });
    }
};
