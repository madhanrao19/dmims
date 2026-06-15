<?php

namespace App\Services;

use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportService
{
    protected string $disk = 'local';

    /**
     * Importable entity types: the target model, the columns accepted from the
     * CSV header, and which of those are required for a row to be valid.
     *
     * @return array<string, array{model: class-string, columns: list<string>, required: list<string>}>
     */
    public static function importableTypes(): array
    {
        return [
            'products' => [
                'model' => Product::class,
                'columns' => ['sku', 'barcode', 'product_name', 'description', 'reorder_level', 'unit_cost', 'unit_price', 'status'],
                'required' => ['sku', 'product_name'],
                'unique' => 'sku',
            ],
            'boxes' => [
                'model' => Box::class,
                'columns' => ['box_number', 'box_barcode', 'source_origin', 'capacity_limit', 'status', 'remarks'],
                'required' => ['box_number'],
                'unique' => 'box_number',
            ],
            'document_files' => [
                'model' => DocumentFile::class,
                'columns' => ['file_reference_no', 'title', 'owner_name', 'source_origin', 'destination', 'current_status', 'remarks'],
                'required' => ['title'],
                'unique' => 'file_reference_no',
            ],
        ];
    }

    /**
     * Parse the uploaded CSV, validate each row, and create the target records.
     */
    public function process(Import $import): Import
    {
        $types = static::importableTypes();

        if (! isset($types[$import->import_type])) {
            throw new RuntimeException("Unknown import type: {$import->import_type}");
        }

        if (! $import->file_path || ! Storage::disk($this->disk)->exists($import->file_path)) {
            throw new RuntimeException('Import file is missing.');
        }

        $definition = $types[$import->import_type];
        $import->update(['status' => 'processing']);

        $handle = fopen(Storage::disk($this->disk)->path($import->file_path), 'r');

        $header = $this->readHeader($handle);
        $rowNumber = 0;
        $total = 0;
        $success = 0;
        $failed = 0;
        $uniqueField = $definition['unique'] ?? null;
        $seen = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlank($row)) {
                    continue;
                }

                $rowNumber++;
                $total++;
                $data = $this->mapRow($header, $row, $definition['columns']);

                $errors = $this->validateRow($data, $definition['required']);

                // Duplicate detection: within the file and against existing data.
                if (! $errors && $uniqueField && ! empty($data[$uniqueField])) {
                    $value = $data[$uniqueField];

                    if (isset($seen[$value])) {
                        $errors[$uniqueField] = "Duplicate {$uniqueField} '{$value}' within the file (row {$seen[$value]}).";
                    } elseif ($this->existsInDatabase($definition['model'], $uniqueField, $value, $import->customer_id)) {
                        $errors[$uniqueField] = "A record with {$uniqueField} '{$value}' already exists.";
                    } else {
                        $seen[$value] = $rowNumber;
                    }
                }

                if ($errors) {
                    $failed++;
                    $this->recordRow($import, $rowNumber, $data, 'invalid', $errors);

                    continue;
                }

                try {
                    $definition['model']::create(array_merge($data, [
                        'customer_id' => $import->customer_id,
                        'created_by' => $import->uploaded_by,
                    ]));
                    $success++;
                    $this->recordRow($import, $rowNumber, $data, 'imported');
                } catch (Throwable $e) {
                    $failed++;
                    $this->recordRow($import, $rowNumber, $data, 'failed', ['exception' => $e->getMessage()]);
                }
            }
        } finally {
            fclose($handle);
        }

        $import->update([
            'status' => 'completed',
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
        ]);

        if ($failed > 0) {
            app(NotificationService::class)->notify(
                'import_failed',
                "Import {$import->import_no} had errors",
                "{$failed} of {$total} rows failed to import.",
                $import->customer_id,
                $import->uploaded_by,
            );
        }

        return $import->refresh();
    }

    /**
     * @return list<string>
     */
    protected function readHeader($handle): array
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            throw new RuntimeException('Import file is empty.');
        }

        // Strip a UTF-8 BOM that spreadsheets often prepend to the first cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        return array_map(fn ($h) => trim((string) $h), $header);
    }

    protected function mapRow(array $header, array $row, array $allowedColumns): array
    {
        $data = [];

        foreach ($header as $i => $column) {
            if (in_array($column, $allowedColumns, true)) {
                $value = $row[$i] ?? null;
                $data[$column] = ($value === '' ? null : $value);
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected function validateRow(array $data, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (! isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        return $errors;
    }

    protected function isBlank(array $row): bool
    {
        return count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0;
    }

    /**
     * Whether a record with the given unique value already exists for the
     * customer (duplicate detection against existing data).
     */
    protected function existsInDatabase(string $model, string $field, mixed $value, ?int $customerId): bool
    {
        return $model::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->where($field, $value)
            ->exists();
    }

    /**
     * Stream a CSV of the failed/invalid rows for this import (TDD §25 error
     * file download).
     */
    public function errorFileResponse(Import $import): StreamedResponse
    {
        $rows = $import->rows()
            ->whereIn('validation_status', ['invalid', 'failed'])
            ->orderBy('row_number')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row_number', 'validation_status', 'errors', 'data']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->row_number,
                    $row->validation_status,
                    $row->error_messages ? implode('; ', Arr::flatten($row->error_messages)) : '',
                    json_encode($row->row_data),
                ]);
            }

            fclose($out);
        }, "{$import->import_no}-errors.csv", ['Content-Type' => 'text/csv']);
    }

    protected function recordRow(Import $import, int $rowNumber, array $data, string $status, ?array $errors = null): void
    {
        ImportRow::create([
            'import_id' => $import->id,
            'row_number' => $rowNumber,
            'row_data' => $data,
            'validation_status' => $status,
            'error_messages' => $errors,
        ]);
    }
}
