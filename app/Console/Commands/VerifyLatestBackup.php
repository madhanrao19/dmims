<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Periodic restore-readiness check (TDD §27 / production-readiness review):
 * decrypts the most recent successful backup and re-runs the same structural
 * check used at backup time, without touching the live database.
 *
 * ponytail: this is a content-integrity check, not a full restore drill into
 * a scratch database — add a scratch-DB restore drill if/when one is
 * provisioned.
 */
class VerifyLatestBackup extends Command
{
    protected $signature = 'dmims:verify-latest-backup';

    protected $description = 'Verify the most recent successful backup is still decryptable and structurally intact.';

    public function handle(): int
    {
        $backup = Backup::query()->where('status', 'success')->latest('completed_at')->first();

        if (! $backup) {
            $this->warn('No successful backup found to verify.');

            return self::SUCCESS;
        }

        $disk = $backup->storage_location ?: 'local';

        if (! $backup->file_path || ! Storage::disk($disk)->exists($backup->file_path)) {
            $this->error("Backup {$backup->backup_no} is missing its file; restore would fail.");

            return self::FAILURE;
        }

        $encrypted = Storage::disk($disk)->get($backup->file_path);

        if ($backup->checksum && hash('sha256', $encrypted) !== $backup->checksum) {
            $this->error("Backup {$backup->backup_no} failed checksum verification.");

            return self::FAILURE;
        }

        try {
            Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            $this->error("Backup {$backup->backup_no} could not be decrypted: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Backup {$backup->backup_no} verified OK (checksum + decrypt).");

        return self::SUCCESS;
    }
}
