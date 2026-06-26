<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Export;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportStatusController extends Controller
{
    /**
     * Export job status by export_no. Export doesn't use BelongsToCustomer,
     * so tenant access is checked explicitly here rather than relying on a
     * global scope.
     */
    public function show(string $exportNo, Request $request): JsonResponse
    {
        $export = Export::where('export_no', $exportNo)->firstOrFail();

        $user = $request->user();
        abort_unless($user->is_platform_user || $export->customer_id === $user->customer_id, 403);

        return response()->json([
            'export_no' => $export->export_no,
            'type' => $export->export_type,
            'status' => $export->status,
            'completed_at' => $export->completed_at,
            'downloadable' => $export->status === 'completed' && filled($export->file_path),
        ]);
    }
}
