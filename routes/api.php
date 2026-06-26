<?php

use App\Http\Controllers\Api\V1\BarcodeController;
use App\Http\Controllers\Api\V1\ExportStatusController;
use App\Http\Controllers\Api\V1\StockController;
use Illuminate\Support\Facades\Route;

/**
 * Versioned internal API seam (production-readiness review: "introduce an
 * integration boundary"). Narrow, read-only, token-authenticated endpoints
 * only — extend this file rather than exposing Filament resources directly.
 */
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/barcodes/{barcode}', [BarcodeController::class, 'show']);
    Route::get('/products/{product}/stock', [StockController::class, 'show']);
    Route::get('/exports/{exportNo}', [ExportStatusController::class, 'show']);
});
