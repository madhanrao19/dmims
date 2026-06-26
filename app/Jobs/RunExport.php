<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public Export $export) {}

    public function handle(ExportService $exports): void
    {
        Log::info('export.started', ['export_no' => $this->export->export_no, 'type' => $this->export->export_type]);

        $exports->run($this->export);

        Log::info('export.completed', ['export_no' => $this->export->export_no]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('export.failed', [
            'export_no' => $this->export->export_no,
            'error' => $exception->getMessage(),
        ]);
    }
}
