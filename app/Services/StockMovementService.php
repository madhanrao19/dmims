<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records stock operations (PRD §8 / TDD §18). Each operation creates a
 * StockMovement; the StockMovementObserver applies the quantity changes to
 * product_location_stocks.
 */
class StockMovementService
{
    public function receiveIn(int $productId, int $toLocationId, float $quantity, array $data = []): StockMovement
    {
        return $this->record($productId, 'stock_in', $quantity, null, $toLocationId, $data);
    }

    public function stockOut(int $productId, int $fromLocationId, float $quantity, array $data = []): StockMovement
    {
        return $this->record($productId, 'stock_out', $quantity, $fromLocationId, null, $data);
    }

    public function transfer(int $productId, int $fromLocationId, int $toLocationId, float $quantity, array $data = []): StockMovement
    {
        return $this->record($productId, 'transfer', $quantity, $fromLocationId, $toLocationId, $data);
    }

    /**
     * Adjust stock at a location by a signed delta: positive adds, negative
     * removes.
     */
    public function adjust(int $productId, int $locationId, float $delta, array $data = []): StockMovement
    {
        $from = $delta < 0 ? $locationId : null;
        $to = $delta >= 0 ? $locationId : null;

        return $this->record($productId, 'adjustment', abs($delta), $from, $to, $data);
    }

    /**
     * Low-level recorder. customer_id is resolved from the product so platform
     * and customer users both produce correctly-scoped movements.
     */
    public function record(int $productId, string $type, float $quantity, ?int $fromLocationId, ?int $toLocationId, array $data = []): StockMovement
    {
        return DB::transaction(function () use ($productId, $type, $quantity, $fromLocationId, $toLocationId, $data) {
            $product = Product::withoutGlobalScopes()->findOrFail($productId);

            return StockMovement::create([
                'customer_id' => $product->customer_id,
                'movement_no' => $data['movement_no'] ?? $this->generateMovementNo(),
                'product_id' => $productId,
                'movement_type' => $type,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? $product->unit_cost,
                'reference_no' => $data['reference_no'] ?? null,
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'performed_by' => auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
            ]);
        });
    }

    public function generateMovementNo(): string
    {
        $year = Carbon::now()->year;
        $seq = SequenceGenerator::next("stock_movement:{$year}");

        return sprintf('MV-%d-%05d', $year, $seq);
    }
}
