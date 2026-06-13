<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Category;
use App\Models\DocumentFile;
use App\Models\Export;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExportService
{
    protected string $disk = 'local';

    protected string $directory = 'exports';

    /**
     * The entity types that can be exported, mapped to their model and the
     * columns written to the CSV.
     *
     * @return array<string, array{model: class-string, columns: list<string>}>
     */
    public static function exportableTypes(): array
    {
        return [
            'products' => ['model' => Product::class, 'columns' => ['id', 'sku', 'barcode', 'product_name', 'category_id', 'reorder_level', 'unit_cost', 'unit_price', 'status']],
            'categories' => ['model' => Category::class, 'columns' => ['id', 'category_code', 'category_name', 'description', 'status']],
            'locations' => ['model' => Location::class, 'columns' => ['id', 'location_code', 'location_name', 'location_type_id', 'barcode', 'status']],
            'boxes' => ['model' => Box::class, 'columns' => ['id', 'box_number', 'box_barcode', 'current_location_id', 'status']],
            'document_files' => ['model' => DocumentFile::class, 'columns' => ['id', 'file_reference_no', 'title', 'document_type_id', 'current_box_id', 'current_status']],
            'stock_movements' => ['model' => StockMovement::class, 'columns' => ['id', 'movement_no', 'product_id', 'from_location_id', 'to_location_id', 'quantity', 'movement_type', 'performed_at']],
        ];
    }

    /**
     * Generate a CSV export for the given entity type and return the record.
     */
    public function export(string $type, ?int $customerId = null, array $data = []): Export
    {
        $types = static::exportableTypes();

        if (! isset($types[$type])) {
            throw new InvalidArgumentException("Unknown export type: {$type}");
        }

        $exportNo = $data['export_no'] ?? $this->generateExportNo();
        $fileName = "{$exportNo}.csv";

        $export = Export::create([
            'customer_id' => $customerId,
            'export_no' => $exportNo,
            'export_type' => $type,
            'file_name' => $fileName,
            'status' => 'processing',
            'requested_by' => $data['requested_by'] ?? auth()->id(),
        ]);

        try {
            $relativePath = $this->writeCsv($types[$type], $fileName, $customerId);

            $export->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'failed']);

            throw $e;
        }

        return $export->refresh();
    }

    protected function writeCsv(array $definition, string $fileName, ?int $customerId): string
    {
        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $columns = $definition['columns'];

        Storage::disk($this->disk)->makeDirectory($this->directory);
        $relativePath = "{$this->directory}/{$fileName}";
        $absolutePath = Storage::disk($this->disk)->path($relativePath);

        $handle = fopen($absolutePath, 'w');
        fputcsv($handle, $columns);

        $query = $model::query();

        // Scope to the customer when the model is tenant-bound.
        if ($customerId && in_array('customer_id', (new $model)->getFillable(), true)) {
            $query->where(function (Builder $q) use ($customerId) {
                $q->where('customer_id', $customerId)->orWhereNull('customer_id');
            });
        }

        $query->chunk(500, function ($records) use ($handle, $columns) {
            foreach ($records as $record) {
                fputcsv($handle, array_map(fn ($c) => $this->stringify($record->{$c}), $columns));
            }
        });

        fclose($handle);

        return $relativePath;
    }

    protected function stringify($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) ($value ?? '');
    }

    protected function generateExportNo(): string
    {
        return 'EXP-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }
}
