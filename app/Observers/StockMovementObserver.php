<?php

namespace App\Observers;

use App\Models\ProductLocationStock;
use App\Models\StockMovement;

class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        $this->applyMovement($movement);
    }

    protected function applyMovement(StockMovement $m): void
    {
        // decrement from_location
        if ($m->from_location_id) {
            $from = ProductLocationStock::firstOrCreate([
                'customer_id' => $m->customer_id,
                'product_id' => $m->product_id,
                'location_id' => $m->from_location_id,
            ], [
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
            ]);

            $from->quantity_on_hand = max(0, ($from->quantity_on_hand ?? 0) - $m->quantity);
            $from->available_quantity = max(0, ($from->available_quantity ?? 0) - $m->quantity);
            $from->last_movement_at = $m->performed_at ?? now();
            $from->save();
        }

        // increment to_location
        if ($m->to_location_id) {
            $to = ProductLocationStock::firstOrCreate([
                'customer_id' => $m->customer_id,
                'product_id' => $m->product_id,
                'location_id' => $m->to_location_id,
            ], [
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
            ]);

            $to->quantity_on_hand = ($to->quantity_on_hand ?? 0) + $m->quantity;
            $to->available_quantity = ($to->available_quantity ?? 0) + $m->quantity;
            $to->last_movement_at = $m->performed_at ?? now();
            $to->save();
        }
    }
}
