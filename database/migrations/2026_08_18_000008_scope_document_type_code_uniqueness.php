<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * document_types.type_code had no uniqueness constraint at all, unlike
 * sibling reference tables (modules.module_code, subscription_plans.plan_code,
 * location_types.type_code) which are all unique (DBA review finding,
 * Medium #8). document_types is tenant-owned (nullable customer_id, shared
 * when null, same convention as every other BelongsToCustomer model), so
 * scope the constraint to (customer_id, type_code) rather than globally.
 *
 * Guarded: unlike the barcode uniqueness fix, there was never a prior
 * uniqueness guarantee here, so existing duplicates are possible. If any
 * (customer_id, type_code) pair is already duplicated, skip adding the
 * constraint and log it instead of failing the migration — the duplicates
 * need a data cleanup decision this migration can't safely make on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('document_types')
            ->select('customer_id', 'type_code')
            ->groupBy('customer_id', 'type_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            logger()->warning('migration.document_type_code_duplicates_found', [
                'duplicates' => $duplicates->toArray(),
            ]);

            return;
        }

        Schema::table('document_types', function (Blueprint $table) {
            $table->unique(['customer_id', 'type_code']);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('document_types', 'document_types_customer_id_type_code_unique')) {
            Schema::table('document_types', function (Blueprint $table) {
                $table->dropUnique(['customer_id', 'type_code']);
            });
        }
    }
};
