<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLocationStock;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    /**
     * Stock-on-hand inquiry for a product, broken down by location. Route
     * model binding applies the product's tenant scope automatically, so a
     * product belonging to another customer resolves as 404.
     */
    public function show(Product $product): JsonResponse
    {
        $byLocation = ProductLocationStock::where('product_id', $product->id)
            ->get(['location_id', 'quantity_on_hand', 'reserved_quantity', 'available_quantity']);

        return response()->json([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'locations' => $byLocation,
            'total_available' => $byLocation->sum('available_quantity'),
        ]);
    }
}
