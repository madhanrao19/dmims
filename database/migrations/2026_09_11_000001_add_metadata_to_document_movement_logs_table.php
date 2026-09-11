<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured external-dispatch details (recipient, company, tracking ref,
 * expected return date) for Box "Move Out" — previously flattened into the
 * free-text `remarks` column, which made them undisplayable independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_movement_logs', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('document_movement_logs', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
