<?php

namespace App\Services;

use App\Models\StockMovement;

class StockMovementService
{
    public function recordMovement(array $data): void
    {
        StockMovement::create([
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'quantity' => $data['quantity'] ?? 0,
            'movement_type' => $data['movement_type'] ?? 'adjustment',
            'performed_by' => $data['performed_by'] ?? auth()->id(),
            'remarks' => $data['remarks'] ?? null,
            'performed_at' => $data['performed_at'] ?? now(),
        ]);
    }
}
