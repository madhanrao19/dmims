<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * stock_movements, document_movement_logs, and audit_logs are documented as
 * immutable, append-only tables but nothing at the DB layer enforced that —
 * only application discipline (confirmed no update/delete call exists
 * anywhere in app code or tests for these three models). Adds BEFORE
 * UPDATE / BEFORE DELETE triggers that reject any write, so a direct SQL
 * statement or a future code bug can no longer silently violate the
 * append-only rule (DBA review finding, Low).
 *
 * TRUNCATE bypasses per-row triggers (both MySQL/MariaDB and SQLite), so
 * this doesn't interfere with migrate:fresh or test database resets.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['stock_movements', 'document_movement_logs', 'audit_logs'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->createGuardTriggers($table);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
        }
    }

    private function createGuardTriggers(string $table): void
    {
        $message = "{$table} is append-only; updates and deletes are not allowed";

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER {$table}_no_update
                BEFORE UPDATE ON {$table}
                BEGIN
                    SELECT RAISE(ABORT, '{$message}');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER {$table}_no_delete
                BEFORE DELETE ON {$table}
                BEGIN
                    SELECT RAISE(ABORT, '{$message}');
                END;
            ");

            return;
        }

        // MySQL / MariaDB
        DB::unprepared("
            CREATE TRIGGER {$table}_no_update
            BEFORE UPDATE ON {$table}
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'
        ");
        DB::unprepared("
            CREATE TRIGGER {$table}_no_delete
            BEFORE DELETE ON {$table}
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'
        ");
    }
};
