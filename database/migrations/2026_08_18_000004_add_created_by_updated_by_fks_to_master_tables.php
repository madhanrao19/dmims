<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * created_by/updated_by were unconstrained on 9 master tables, unlike every
 * sibling table using the same column pair (boxes, document_files,
 * billing_*, stock_movements, imports, exports, backups, etc.), which all
 * correctly .constrained('users') (DBA review finding, High #4). Guarded:
 * null out any value already pointing at a nonexistent user before adding
 * the constraint (logged), matching the users.customer_id migration.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'customers', 'departments', 'customer_modules', 'customer_subscriptions',
        'licenses', 'locations', 'barcode_registry', 'categories', 'products',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            foreach (['created_by', 'updated_by'] as $column) {
                $orphaned = DB::table($tableName)
                    ->whereNotNull($column)
                    ->whereNotIn($column, DB::table('users')->select('id'))
                    ->pluck('id');

                if ($orphaned->isNotEmpty()) {
                    DB::table($tableName)->whereIn('id', $orphaned)->update([$column => null]);
                    logger()->warning('migration.orphaned_reference_nulled', [
                        'table' => $tableName,
                        'column' => $column,
                        'row_ids' => $orphaned->all(),
                    ]);
                }
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('updated_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
            });
        }
    }
};
