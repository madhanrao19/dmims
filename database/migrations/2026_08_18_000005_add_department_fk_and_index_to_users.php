<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.department_id had no FK or index, unlike document_files.department_id
 * which is correctly constrained (DBA review finding, High #6). Guarded:
 * null out any department_id already pointing at a nonexistent department
 * before adding the constraint (logged).
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphaned = DB::table('users')
            ->whereNotNull('department_id')
            ->whereNotIn('department_id', DB::table('departments')->select('id'))
            ->pluck('id');

        if ($orphaned->isNotEmpty()) {
            DB::table('users')->whereIn('id', $orphaned)->update(['department_id' => null]);
            logger()->warning('migration.orphaned_user_department_id_nulled', [
                'user_ids' => $orphaned->all(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index('department_id');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
        });
    }
};
