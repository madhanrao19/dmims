<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Scheduled database backup (TDD §27). Runs the same BackupService used by the
 * admin panel's "Run Database Backup" action.
 */
class BackupDatabase extends Command
{
    protected $signature = 'dmims:backup-database';

    protected $description = 'Create a database backup.';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->backupDatabase(['remarks' => 'Scheduled backup']);
            $this->info("Backup {$backup->backup_no} completed ({$backup->file_size} bytes).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
