<?php

namespace App\Services;

use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use Illuminate\Support\Collection;

/**
 * Turns raw DocumentMovementLog rows into a human-readable activity timeline
 * (grouped by day, plain-language action label, actor name) instead of the
 * raw technical log table.
 */
class MovementTimelineService
{
    public function forDocumentFile(DocumentFile $file): Collection
    {
        return $this->build('document_file', $file->id);
    }

    public function forBox(Box $box): Collection
    {
        return $this->build('box', $box->id);
    }

    /**
     * @return Collection<string, Collection> entries grouped by day label
     */
    protected function build(string $movableType, int $movableId): Collection
    {
        return DocumentMovementLog::where('movable_type', $movableType)
            ->where('movable_id', $movableId)
            ->with(['performedBy', 'fromBox.currentLocation', 'toBox.currentLocation', 'fromLocation', 'toLocation'])
            ->orderBy('performed_at')
            ->get()
            ->map(fn (DocumentMovementLog $log) => [
                'time' => $log->performed_at,
                'title' => $this->title($log),
                'detail' => $this->detail($log),
                'actor' => $log->performedBy?->name ?? 'System',
            ])
            ->groupBy(fn (array $entry) => $entry['time']?->format('d M Y') ?? 'Unknown date');
    }

    public function title(DocumentMovementLog $log): string
    {
        return match ($log->action_type) {
            'create' => 'Created',
            'transfer_file', 'transfer_box' => 'Transferred',
            'move_out' => 'Dispatched',
            'return' => 'Returned',
            'archive' => 'Archived',
            'correction' => 'Correction',
            default => ucfirst($log->action_type),
        };
    }

    public function detail(DocumentMovementLog $log): string
    {
        $from = $log->fromBox?->currentLocation?->ancestry_path
            ?? $log->fromLocation?->ancestry_path
            ?? $log->source_origin;

        $to = $log->toBox
            ? "Box {$log->toBox->box_number}"
            : ($log->toLocation?->ancestry_path ?? $log->destination);

        return match (true) {
            $from && $to => "{$from} → {$to}",
            (bool) $to => (string) $to,
            (bool) $from => (string) $from,
            default => '—',
        };
    }
}
