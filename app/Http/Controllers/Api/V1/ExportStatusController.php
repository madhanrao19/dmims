<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Export;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportStatusController extends Controller
{
    /**
     * Export job status by export_no. Export is tenant-scoped via
     * BelongsToCustomer, so a tenant user's query never returns another
     * tenant's export (cross-tenant access yields 404). The explicit check
     * below is defense-in-depth for the platform-user and shared-record paths.
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
