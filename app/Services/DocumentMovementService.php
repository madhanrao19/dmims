<?php

namespace App\Services;

use App\Models\DocumentMovementLog;

class DocumentMovementService
{
    public function logMovement(array $data): void
    {
        DocumentMovementLog::create([
            'customer_id' => $data['customer_id'],
            'movement_no' => $data['movement_no'] ?? null,
            'movable_type' => $data['movable_type'] ?? null,
            'movable_id' => $data['movable_id'] ?? null,
            'action_type' => $data['action_type'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'from_box_id' => $data['from_box_id'] ?? null,
            'to_box_id' => $data['to_box_id'] ?? null,
            'source_origin' => $data['source_origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'scanned_barcode' => $data['scanned_barcode'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'performed_by' => $data['performed_by'] ?? auth()->id(),
            'performed_at' => $data['performed_at'] ?? now(),
        ]);
    }
}
