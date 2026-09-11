<?php

namespace App\Services;

use App\Models\BarcodeRegistry;
use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Generates and registers barcodes in the central registry (TDD §21).
 * Format: {PREFIX}-{COMPANYCODE}-{000001} e.g. PRD-ACME-000001.
 */
class BarcodeService
{
    /** Map of barcode_type => barcode prefix. */
    public const PREFIXES = [
        'product' => 'PRD',
        'location' => 'LOC',
        'box' => 'BOX',
        'document_file' => 'DOC',
    ];

    /** Map of model class => [barcode_type, barcode column on the model]. */
    private const MODEL_MAP = [
        Product::class => ['product', 'barcode'],
        Location::class => ['location', 'barcode'],
        Box::class => ['box', 'box_barcode'],
        DocumentFile::class => ['document_file', 'file_barcode'],
    ];

    /** Map of reference_table => model class (reverse lookup for replace()). */
    private const TABLE_MODELS = [
        'products' => Product::class,
        'locations' => Location::class,
        'boxes' => Box::class,
        'document_files' => DocumentFile::class,
    ];

    public function generate(string $type, string $companyCode, int $sequence): string
    {
        if (! isset(self::PREFIXES[$type])) {
            throw new InvalidArgumentException("Unknown barcode type: {$type}");
        }

        $code = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $companyCode) ?: 'DM');

        return sprintf('%s-%s-%06d', self::PREFIXES[$type], $code, $sequence);
    }

    /**
     * Generate, register and assign a barcode for a model record. Idempotent:
     * if the record already has a registry entry it is returned unchanged.
     */
    public function registerFor(Model $record): BarcodeRegistry
    {
        [$type, $column] = $this->resolveModel($record);

        $existing = $this->findExisting($record);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($record, $type, $column) {
            // re-check under the transaction: another request may have
            // registered this record between the check above and now.
            $existing = $this->findExisting($record, lock: true);
            if ($existing) {
                return $existing;
            }

            $companyCode = $record->customer?->company_code ?? 'DM';
            $sequence = SequenceGenerator::next("barcode:{$record->getAttribute('customer_id')}:{$type}");
            $barcode = $this->generate($type, $companyCode, $sequence);

            $registry = BarcodeRegistry::create([
                'customer_id' => $record->getAttribute('customer_id'),
                'barcode' => $barcode,
                'barcode_type' => $type,
                'reference_table' => $record->getTable(),
                'reference_id' => $record->getKey(),
                'status' => 'active',
            ]);

            $record->forceFill([$column => $barcode])->save();

            return $registry;
        });
    }

    /**
     * Pre-generate $count unused barcode labels for a customer+type, to be
     * claimed later when a matching record is created (reservation/
     * pre-printing — lets a batch of labels be printed before the boxes/
     * files they'll go on exist yet). Uses the same per-customer, per-type
     * sequence counter as registerFor(), so reserved and directly-registered
     * barcodes never collide.
     *
     * @return Collection<int, BarcodeRegistry>
     */
    public function reserve(int $customerId, string $type, int $count): Collection
    {
        if (! isset(self::PREFIXES[$type])) {
            throw new InvalidArgumentException("Unknown barcode type: {$type}");
        }

        $companyCode = Customer::withoutGlobalScopes()->findOrFail($customerId)->company_code ?? 'DM';

        return collect(range(1, $count))->map(function () use ($customerId, $type, $companyCode): BarcodeRegistry {
            $sequence = SequenceGenerator::next("barcode:{$customerId}:{$type}");
            $barcode = $this->generate($type, $companyCode, $sequence);

            return BarcodeRegistry::create([
                'customer_id' => $customerId,
                'barcode' => $barcode,
                'barcode_type' => $type,
                'reference_table' => null,
                'reference_id' => null,
                'status' => 'unused',
            ]);
        });
    }

    /**
     * Attach a reserved-but-unclaimed barcode to a newly-created record whose
     * barcode column was pre-filled from that reservation (e.g. the Scan
     * Center's "unused barcode → create form" redirect). A no-op (returns
     * null) for a manually-typed barcode that was never reserved — that
     * record keeps its barcode exactly as today, no registry row is created
     * or required for it.
     */
    public function claim(Model $record): ?BarcodeRegistry
    {
        [$type, $column] = $this->resolveModel($record);
        $barcode = $record->getAttribute($column);

        if (! $barcode) {
            return null;
        }

        return DB::transaction(function () use ($record, $type, $barcode) {
            $reservation = BarcodeRegistry::withoutGlobalScopes()
                ->where('customer_id', $record->getAttribute('customer_id'))
                ->where('barcode', $barcode)
                ->where('barcode_type', $type)
                ->where('status', 'unused')
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return null;
            }

            $reservation->update([
                'reference_table' => $record->getTable(),
                'reference_id' => $record->getKey(),
                'status' => 'active',
            ]);

            return $reservation;
        });
    }

    /**
     * Register a record's own already-set barcode value into the registry,
     * without generating a new one — the counterpart to registerFor() for a
     * barcode the user typed or scanned in themselves rather than one this
     * service generated. Used specifically for the global scan-to-create
     * flow (BarcodeScannerListener), so a barcode scanned as "unknown",
     * used to create a record, is immediately scannable again — deliberately
     * NOT called for ordinary manual entry, which keeps claim()'s existing
     * "no registry row required" behaviour unchanged.
     */
    public function registerExisting(Model $record): ?BarcodeRegistry
    {
        [$type, $column] = $this->resolveModel($record);
        $barcode = $record->getAttribute($column);

        if (! $barcode) {
            return null;
        }

        return DB::transaction(function () use ($record, $type, $barcode) {
            $existing = BarcodeRegistry::withoutGlobalScopes()
                ->where('customer_id', $record->getAttribute('customer_id'))
                ->where('barcode', $barcode)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->reference_id === null) {
                    $existing->update([
                        'reference_table' => $record->getTable(),
                        'reference_id' => $record->getKey(),
                        'status' => 'active',
                    ]);
                }

                return $existing;
            }

            return BarcodeRegistry::create([
                'customer_id' => $record->getAttribute('customer_id'),
                'barcode' => $barcode,
                'barcode_type' => $type,
                'reference_table' => $record->getTable(),
                'reference_id' => $record->getKey(),
                'status' => 'active',
            ]);
        });
    }

    /**
     * Retire a lost/damaged barcode and issue a new one for the same record.
     * The old registry row is kept (status 'retired') for history/audit.
     */
    public function replace(BarcodeRegistry $registry): BarcodeRegistry
    {
        return DB::transaction(function () use ($registry) {
            $locked = BarcodeRegistry::withoutGlobalScopes()->whereKey($registry->id)->lockForUpdate()->first();

            if (! $locked || $locked->status === 'retired') {
                throw new InvalidArgumentException('Barcode is already retired.');
            }

            $locked->update(['status' => 'retired']);

            $modelClass = self::TABLE_MODELS[$locked->reference_table] ?? null;
            if (! $modelClass) {
                throw new InvalidArgumentException("Cannot resolve model for table {$locked->reference_table}.");
            }

            $record = $modelClass::withoutGlobalScopes()->findOrFail($locked->reference_id);

            return $this->registerFor($record);
        });
    }

    protected function findExisting(Model $record, bool $lock = false): ?BarcodeRegistry
    {
        $query = BarcodeRegistry::withoutGlobalScopes()
            ->where('reference_table', $record->getTable())
            ->where('reference_id', $record->getKey())
            ->where('status', 'active');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function incrementPrinted(BarcodeRegistry $registry): void
    {
        $registry->increment('printed_count');
    }

    public function detectBarcodeType(string $barcode): ?string
    {
        $prefix = Str::before($barcode, '-');

        return array_search($prefix, self::PREFIXES, true) ?: null;
    }

    /**
     * @return array{0: string, 1: string} [barcode_type, column]
     */
    private function resolveModel(Model $record): array
    {
        foreach (self::MODEL_MAP as $class => $meta) {
            if ($record instanceof $class) {
                return $meta;
            }
        }

        throw new InvalidArgumentException('Model '.$record::class.' is not barcodable.');
    }
}
