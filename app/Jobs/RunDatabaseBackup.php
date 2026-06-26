<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public Backup $backup) {}

    public function handle(BackupService $backups): void
    {
        Log::info('backup.started', ['backup_no' => $this->backup->backup_no]);

        $backups->run($this->backup);

        Log::info('backup.completed', ['backup_no' => $this->backup->backup_no]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('backup.failed', [
            'backup_no' => $this->backup->backup_no,
            'error' => $exception->getMessage(),
        ]);
    }
}
