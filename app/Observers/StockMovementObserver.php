<?php

namespace App\Observers;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductLocationStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

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
            $from = ProductLocationStock::lockForUpdate()->firstOrCreate([
                'customer_id' => $m->customer_id,
                'product_id' => $m->product_id,
                'location_id' => $m->from_location_id,
            ], [
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
            ]);

            $newOnHand = ($from->quantity_on_hand ?? 0) - $m->quantity;
            $newAvailable = ($from->available_quantity ?? 0) - $m->quantity;

            if ($newOnHand < 0 || $newAvailable < 0) {
                Log::warning('stock_movement.insufficient_stock', [
                    'movement_no' => $m->movement_no,
                    'product_id' => $m->product_id,
                    'from_location_id' => $m->from_location_id,
                    'requested' => $m->quantity,
                    'available' => $from->available_quantity,
                ]);

                throw new InsufficientStockException(
                    "Insufficient stock for product #{$m->product_id} at location #{$m->from_location_id}: ".
                    "movement {$m->movement_no} requests {$m->quantity}, only {$from->available_quantity} available."
                );
            }

            $from->quantity_on_hand = $newOnHand;
            $from->available_quantity = $newAvailable;
            $from->last_movement_at = $m->performed_at ?? now();
            $from->save();
        }

        // increment to_location
        if ($m->to_location_id) {
            $to = ProductLocationStock::lockForUpdate()->firstOrCreate([
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
