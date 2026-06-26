<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * Resolve a scanned barcode to its record. Logs the scan attempt the
     * same way the in-panel scanner does (ScannerService::scan).
     */
    public function show(string $barcode, Request $request, ScannerService $scanner): JsonResponse
    {
        $outcome = $scanner->scan($barcode, $request->user());

        return response()->json([
            'result' => $outcome['result'],
            'barcode_type' => $outcome['registry']?->barcode_type,
            'record_type' => $outcome['registry']?->reference_table,
            'record_id' => $outcome['registry']?->reference_id,
        ], $outcome['result'] === 'found' ? 200 : 404);
    }
}
