<?php

namespace App\Services;

use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Document tracking operations (PRD §9 / TDD §19-20). Files live in boxes; boxes
 * live in locations (File -> Box -> Location). External origins/destinations are
 * stored as free text — never as fake locations (TDD §20).
 *
 * action_type and movable_type conform to the document_movement_logs enums.
 */
class DocumentMovementService
{
    // ----- File operations -------------------------------------------------

    public function receiveInFile(DocumentFile $file, int $toBoxId, ?string $sourceOrigin = null, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($file, $toBoxId, $sourceOrigin, $data) {
            $log = $this->log($file, 'create', array_merge($data, [
                'to_box_id' => $toBoxId,
                'source_origin' => $sourceOrigin,
            ]));

            $file->update(['current_box_id' => $toBoxId, 'current_status' => 'active']);
            $this->adjustBoxFileCount($toBoxId, 1);

            return $log;
        });
    }

    public function transferFile(DocumentFile $file, int $toBoxId, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($file, $toBoxId, $data) {
            $fromBoxId = $file->current_box_id;

            $log = $this->log($file, 'transfer_file', array_merge($data, [
                'from_box_id' => $fromBoxId,
                'to_box_id' => $toBoxId,
            ]));

            $file->update(['current_box_id' => $toBoxId, 'current_status' => 'active']);
            $this->adjustBoxFileCount($fromBoxId, -1);
            $this->adjustBoxFileCount($toBoxId, 1);

            return $log;
        });
    }

    /**
     * @param  array{borrowed_by?: ?string, due_date?: ?string}  $data
     */
    public function moveOutFile(DocumentFile $file, string $destination, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($file, $destination, $data) {
            $fromBoxId = $file->current_box_id;

            $log = $this->log($file, 'move_out', array_merge($data, [
                'from_box_id' => $fromBoxId,
                'destination' => $destination,
            ]));

            $file->update([
                'current_box_id' => null,
                'current_status' => 'moved_out',
                'destination' => $destination,
                'borrowed_by' => $data['borrowed_by'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'returned_at' => null,
            ]);
            $this->adjustBoxFileCount($fromBoxId, -1);

            return $log;
        });
    }

    public function returnFile(DocumentFile $file, int $toBoxId, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($file, $toBoxId, $data) {
            $log = $this->log($file, 'return', array_merge($data, ['to_box_id' => $toBoxId]));

            $file->update([
                'current_box_id' => $toBoxId,
                'current_status' => 'active',
                'returned_at' => now(),
            ]);
            $this->adjustBoxFileCount($toBoxId, 1);

            return $log;
        });
    }

    /**
     * Keep Box.current_file_count derived from actual file movements instead
     * of relying on manual edits, which drift from reality.
     */
    protected function adjustBoxFileCount(?int $boxId, int $delta): void
    {
        if (! $boxId) {
            return;
        }

        Box::whereKey($boxId)->lockForUpdate()->first()?->increment('current_file_count', $delta);
    }

    // ----- Box operations --------------------------------------------------

    public function receiveInBox(Box $box, int $toLocationId, ?string $sourceOrigin = null, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($box, $toLocationId, $sourceOrigin, $data) {
            $log = $this->log($box, 'create', array_merge($data, [
                'to_location_id' => $toLocationId,
                'source_origin' => $sourceOrigin,
            ]));

            $box->update(['current_location_id' => $toLocationId, 'status' => 'active']);

            return $log;
        });
    }

    public function transferBox(Box $box, int $toLocationId, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($box, $toLocationId, $data) {
            $log = $this->log($box, 'transfer_box', array_merge($data, [
                'from_location_id' => $box->current_location_id,
                'to_location_id' => $toLocationId,
            ]));

            $box->update(['current_location_id' => $toLocationId, 'status' => 'active']);

            return $log;
        });
    }

    public function moveOutBox(Box $box, string $destination, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($box, $destination, $data) {
            $log = $this->log($box, 'move_out', array_merge($data, [
                'from_location_id' => $box->current_location_id,
                'destination' => $destination,
            ]));

            $box->update(['current_location_id' => null, 'status' => 'moved_out']);

            return $log;
        });
    }

    public function returnBox(Box $box, int $toLocationId, array $data = []): DocumentMovementLog
    {
        return DB::transaction(function () use ($box, $toLocationId, $data) {
            $log = $this->log($box, 'return', array_merge($data, ['to_location_id' => $toLocationId]));

            $box->update(['current_location_id' => $toLocationId, 'status' => 'active']);

            return $log;
        });
    }

    /**
     * Write a movement log for a file or box.
     */
    protected function log(DocumentFile|Box $movable, string $actionType, array $data = []): DocumentMovementLog
    {
        $movableType = $movable instanceof DocumentFile ? 'document_file' : 'box';

        return DocumentMovementLog::create([
            'customer_id' => $movable->customer_id,
            'movement_no' => $data['movement_no'] ?? $this->generateMovementNo(),
            'movable_type' => $movableType,
            'movable_id' => $movable->getKey(),
            'action_type' => $actionType,
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'from_box_id' => $data['from_box_id'] ?? null,
            'to_box_id' => $data['to_box_id'] ?? null,
            'source_origin' => $data['source_origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'scanned_barcode' => $data['scanned_barcode'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'performed_by' => auth()->id(),
            'performed_at' => $data['performed_at'] ?? now(),
        ]);
    }

    public function generateMovementNo(): string
    {
        $year = Carbon::now()->year;
        $seq = SequenceGenerator::next("document_movement:{$year}");

        return sprintf('MOV-%d-%05d', $year, $seq);
    }
}
