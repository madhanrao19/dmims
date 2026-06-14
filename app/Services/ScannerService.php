<?php

namespace App\Services;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\ProductResource;
use App\Models\BarcodeRegistry;
use App\Models\BarcodeScanLog;
use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves scanned barcodes to their records and logs every scan (TDD §21).
 */
class ScannerService
{
    private const TABLE_MODELS = [
        'products' => Product::class,
        'locations' => Location::class,
        'boxes' => Box::class,
        'document_files' => DocumentFile::class,
    ];

    public function resolve(string $barcode, ?int $customerId): ?BarcodeRegistry
    {
        $query = BarcodeRegistry::withoutGlobalScopes()->where('barcode', $barcode);

        // Platform users (no customer) may resolve any tenant's barcode.
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->first();
    }

    public function resolveRecord(BarcodeRegistry $registry): ?Model
    {
        $model = self::TABLE_MODELS[$registry->reference_table] ?? null;

        return $model
            ? $model::withoutGlobalScopes()->find($registry->reference_id)
            : null;
    }

    /**
     * Scan a barcode for a user: log the attempt and return the outcome.
     *
     * @return array{result: string, registry: ?BarcodeRegistry, record: ?Model}
     */
    public function scan(string $barcode, User $user): array
    {
        $customerId = $user->is_platform_user ? null : $user->customer_id;
        $registry = $this->resolve($barcode, $customerId);

        $result = match (true) {
            ! $registry => 'unknown',
            $registry->status !== 'active' => 'inactive',
            default => 'found',
        };

        $record = $result === 'found' ? $this->resolveRecord($registry) : null;

        BarcodeScanLog::create([
            'customer_id' => $registry?->customer_id ?? $user->customer_id,
            'barcode' => $barcode,
            'barcode_type' => $registry?->barcode_type,
            'reference_table' => $registry?->reference_table,
            'reference_id' => $registry?->reference_id,
            'scan_result' => $result,
            'action_taken' => $result === 'found' ? 'open' : null,
            'scanned_by' => $user->id,
            'scanned_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        if ($result === 'found') {
            $registry->update(['last_scanned_at' => now()]);
        }

        return ['result' => $result, 'registry' => $registry, 'record' => $record];
    }

    /**
     * The Filament resource URL for a resolved record, if one maps to it.
     */
    public function recordUrl(BarcodeRegistry $registry): ?string
    {
        $resource = match ($registry->reference_table) {
            'products' => ProductResource::class,
            'locations' => LocationResource::class,
            'boxes' => BoxResource::class,
            'document_files' => DocumentFileResource::class,
            default => null,
        };

        return $resource ? $resource::getUrl('edit', ['record' => $registry->reference_id]) : null;
    }
}
