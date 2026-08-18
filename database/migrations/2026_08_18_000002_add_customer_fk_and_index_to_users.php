<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.customer_id had no FK constraint and no index (DBA review finding,
 * Critical #1) despite being the column every tenant global scope depends
 * on. Guarded: null out any customer_id already pointing at a customer that
 * no longer exists before adding the constraint, so this can't fail against
 * existing production data. Only rows that are already broken references
 * are touched; valid data is untouched. Logged so the change is auditable if
 * it ever fires against real data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphaned = DB::table('users')
            ->whereNotNull('customer_id')
            ->whereNotIn('customer_id', DB::table('customers')->select('id'))
            ->pluck('id');

        if ($orphaned->isNotEmpty()) {
            DB::table('users')->whereIn('id', $orphaned)->update(['customer_id' => null]);
            logger()->warning('migration.orphaned_user_customer_id_nulled', [
                'user_ids' => $orphaned->all(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
        });
    }
};
