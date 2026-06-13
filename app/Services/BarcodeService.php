<?php

namespace App\Services;

use App\Models\BarcodeRegistry;
use App\Models\BarcodeScanLog;
use Illuminate\Support\Str;

class BarcodeService
{
    public function registerBarcode(array $data): void
    {
        BarcodeRegistry::updateOrCreate(
            ['barcode' => $data['barcode'], 'customer_id' => $data['customer_id']],
            [
                'product_id' => $data['product_id'] ?? null,
                'box_id' => $data['box_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]
        );
    }

    public function detectBarcodeType(string $barcode): ?string
    {
        if (Str::startsWith($barcode, 'BOX-')) {
            return 'box';
        }

        if (Str::startsWith($barcode, 'DOC-')) {
            return 'document';
        }

        return 'product';
    }

    public function logScan(int $customerId, int $userId, string $barcode, ?int $productId = null, ?int $boxId = null): void
    {
        BarcodeScanLog::create([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'barcode' => $barcode,
            'product_id' => $productId,
            'box_id' => $boxId,
            'scanned_at' => now(),
        ]);
    }
}
