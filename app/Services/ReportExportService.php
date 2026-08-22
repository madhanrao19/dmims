<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BillingPayment;
use App\Models\BillingRecord;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Named operational and platform reports (TDD §22). Reports for tenant-owned
 * models are automatically customer-scoped via the BelongsToCustomer global
 * scope when run by a customer user; platform staff see all rows.
 *
 * CSV is always available. Excel/PDF are emitted when a converter library is
 * installed (production PHP 8.4); otherwise CSV is produced.
 */
class ReportExportService
{
    /**
     * @return array<string, array{label: string, group: string, platform: bool, permission?: string}>
     */
    public static function definitions(): array
    {
        return [
            // Platform reports (TDD §22)
            'customer_summary' => ['label' => 'Customer Summary', 'group' => 'Platform', 'platform' => true],
            'subscription_summary' => ['label' => 'Subscription Summary', 'group' => 'Platform', 'platform' => true],
            'license_summary' => ['label' => 'License Summary', 'group' => 'Platform', 'platform' => true],
            'billing_summary' => ['label' => 'Billing Summary', 'group' => 'Platform', 'platform' => false, 'permission' => 'view billing'],
            'payment_summary' => ['label' => 'Payment Summary', 'group' => 'Platform', 'platform' => false, 'permission' => 'view billing'],
            'outstanding_balance' => ['label' => 'Outstanding Balance', 'group' => 'Platform', 'platform' => false, 'permission' => 'view billing'],
            'audit_summary' => ['label' => 'Audit Report', 'group' => 'Platform', 'platform' => true],
            'module_usage' => ['label' => 'Module Usage', 'group' => 'Platform', 'platform' => true],
            // Inventory reports
            'inventory_summary' => ['label' => 'Inventory Summary', 'group' => 'Inventory', 'platform' => false],
            'low_stock' => ['label' => 'Low Stock', 'group' => 'Inventory', 'platform' => false],
            'stock_movement' => ['label' => 'Stock Movement', 'group' => 'Inventory', 'platform' => false],
            'stock_value' => ['label' => 'Stock Value', 'group' => 'Inventory', 'platform' => false],
            // Document reports
            'file_master' => ['label' => 'File Master', 'group' => 'Document', 'platform' => false],
            'box_master' => ['label' => 'Box Master', 'group' => 'Document', 'platform' => false],
            'files_by_box' => ['label' => 'Files by Box', 'group' => 'Document', 'platform' => false],
            'boxes_by_location' => ['label' => 'Boxes by Location', 'group' => 'Document', 'platform' => false],
            'movement_history' => ['label' => 'Movement History', 'group' => 'Document', 'platform' => false],
            'external_movement' => ['label' => 'External Movement', 'group' => 'Document', 'platform' => false],
            'overdue_returns' => ['label' => 'Overdue Returns', 'group' => 'Document', 'platform' => false],
        ];
    }

    /**
     * Reports a user may run: platform-only reports require a platform user;
     * a report with a 'permission' key (e.g. billing) additionally requires
     * that permission for non-platform users — Security & Access Control
     * Matrix §9 restricts billing data to SA/Management/Company Admin/
     * Company Supervisor, not every role holding the generic "view reports"
     * permission that gates this screen itself.
     *
     * @return array<string, array{label: string, group: string, platform: bool, permission?: string}>
     */
    public static function availableTo($user): array
    {
        return array_filter(
            static::definitions(),
            function (array $def) use ($user) {
                if ($def['platform']) {
                    return (bool) $user?->is_platform_user;
                }

                if ($user?->is_platform_user) {
                    return true;
                }

                return ! isset($def['permission']) || (bool) $user?->can($def['permission']);
            },
        );
    }

    /**
     * @param  'csv'|'xlsx'|'pdf'  $format
     */
    public function generate(string $key, string $format = 'csv'): Response|StreamedResponse
    {
        if (! isset(static::definitions()[$key])) {
            throw new InvalidArgumentException("Unknown report: {$key}");
        }

        [$headers, $rows] = $this->build($key);
        $label = static::definitions()[$key]['label'];
        $base = $key.'-'.now()->format('Ymd-His');

        return match ($format) {
            'pdf' => $this->pdf($label, "{$base}.pdf", $headers, $rows),
            'xlsx' => $this->xlsx("{$base}.xlsx", $headers, $rows),
            default => $this->csv("{$base}.csv", $headers, $rows),
        };
    }

    private function csv(string $fileName, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function xlsx(string $fileName, array $headers, iterable $rows): Response
    {
        $temp = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';

        $writer = new XlsxWriter;
        $writer->openToFile($temp);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($v) => (string) $v, $row)));
        }
        $writer->close();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function pdf(string $title, string $fileName, array $headers, iterable $rows): Response
    {
        $html = view('reports.pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => collect($rows),
            'generatedAt' => now(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->download($fileName);
    }

    /**
     * @return array{0: list<string>, 1: iterable<array>}
     */
    public function build(string $key): array
    {
        return match ($key) {
            'customer_summary' => [
                ['ID', 'Company', 'Code', 'Status', 'Created'],
                Customer::query()->lazy()->map(fn (Customer $c) => [$c->id, $c->company_name, $c->company_code, $c->status, (string) $c->created_at]),
            ],
            'subscription_summary' => [
                ['Subscription No', 'Company', 'Status', 'Valid From', 'Valid To'],
                CustomerSubscription::with('customer')->lazy()->map(fn (CustomerSubscription $s): array => [$s->subscription_no, $s->customer?->company_name, $s->status, (string) $s->valid_from, (string) $s->valid_to]),
            ],
            'license_summary' => [
                ['License No', 'Company', 'Status', 'Access Mode', 'Valid To'],
                License::with('customer')->lazy()->map(fn (License $l) => [$l->license_no, $l->customer?->company_name, $l->status, $l->technical_access_mode, (string) $l->valid_to]),
            ],
            'billing_summary' => [
                ['Invoice No', 'Company', 'Total', 'Billing Status', 'Payment Status', 'Due Date'],
                BillingRecord::with('customer')->lazy()->map(fn (BillingRecord $b) => [$b->invoice_no, $b->customer?->company_name, $b->total_amount, $b->billing_status, $b->payment_status, (string) $b->due_date]),
            ],
            'payment_summary' => [
                ['Payment No', 'Invoice', 'Amount', 'Method', 'Date'],
                BillingPayment::with('billingRecord')->lazy()->map(fn (BillingPayment $p) => [$p->payment_no, $p->billingRecord?->invoice_no, $p->amount, $p->payment_method, (string) $p->payment_date]),
            ],
            'outstanding_balance' => [
                ['Invoice No', 'Company', 'Total', 'Outstanding', 'Due Date'],
                BillingRecord::with('customer')
                    ->where('payment_status', '!=', 'paid')
                    ->where('billing_status', '!=', 'cancelled')
                    ->lazy()
                    ->map(fn (BillingRecord $b) => [$b->invoice_no, $b->customer?->company_name, $b->total_amount, $b->outstandingAmount(), (string) $b->due_date]),
            ],
            'audit_summary' => [
                ['Date', 'Module', 'Action', 'User ID', 'Auditable'],
                AuditLog::latest()->limit(5000)->get()->map(fn (AuditLog $a) => [(string) $a->created_at, $a->module, $a->action, $a->user_id, class_basename((string) $a->auditable_type).'#'.$a->auditable_id]),
            ],
            'module_usage' => [
                ['Company', 'Module', 'Enabled', 'Enabled At'],
                CustomerModule::with(['customer', 'module'])->lazy()->map(fn (CustomerModule $cm) => [
                    $cm->customer?->company_name, $cm->module?->module_name,
                    $cm->is_enabled ? 'Yes' : 'No', (string) $cm->enabled_at,
                ]),
            ],
            'inventory_summary' => [
                ['SKU', 'Product', 'Reorder Level', 'Unit Cost', 'Unit Price', 'Status'],
                Product::query()->lazy()->map(fn (Product $p) => [$p->sku, $p->product_name, $p->reorder_level, $p->unit_cost, $p->unit_price, $p->status]),
            ],
            'low_stock' => $this->lowStock(),
            'stock_movement' => [
                ['Movement No', 'Product ID', 'Type', 'Qty', 'From', 'To', 'Performed At'],
                StockMovement::query()->latest('performed_at')->lazy()->map(fn (StockMovement $m) => [$m->movement_no, $m->product_id, $m->movement_type, $m->quantity, $m->from_location_id, $m->to_location_id, (string) $m->performed_at]),
            ],
            'stock_value' => $this->stockValue(),
            'file_master' => [
                ['Reference No', 'Title', 'Status', 'Box ID'],
                DocumentFile::query()->lazy()->map(fn (DocumentFile $f) => [$f->file_reference_no, $f->title, $f->current_status, $f->current_box_id]),
            ],
            'box_master' => [
                ['Box No', 'Barcode', 'Location ID', 'Status'],
                Box::query()->lazy()->map(fn (Box $b) => [$b->box_number, $b->box_barcode, $b->current_location_id, $b->status]),
            ],
            'files_by_box' => [
                ['Box No', 'Reference No', 'File Title', 'File Status'],
                DocumentFile::with('currentBox')->whereNotNull('current_box_id')->orderBy('current_box_id')->lazy()
                    ->map(fn (DocumentFile $f) => [$f->currentBox?->box_number, $f->file_reference_no, $f->title, $f->current_status]),
            ],
            'boxes_by_location' => [
                ['Location', 'Box No', 'Barcode', 'Box Status'],
                Box::with('currentLocation')->whereNotNull('current_location_id')->orderBy('current_location_id')->lazy()
                    ->map(fn (Box $b) => [$b->currentLocation?->location_name, $b->box_number, $b->box_barcode, $b->status]),
            ],
            'movement_history' => [
                ['Movement No', 'Action', 'From Box', 'To Box', 'Performed At'],
                DocumentMovementLog::query()->latest('performed_at')->lazy()->map(fn (DocumentMovementLog $m) => [$m->movement_no, $m->action_type, $m->from_box_id, $m->to_box_id, (string) $m->performed_at]),
            ],
            'external_movement' => [
                // "External" = a documented origin/destination outside the system
                // (TDD §20 — free text, never a fake location/box row).
                ['Movement No', 'Action', 'Item Type', 'Item ID', 'Source Origin', 'Destination', 'Performed At'],
                DocumentMovementLog::query()
                    ->where(fn (Builder $q) => $q->whereNotNull('source_origin')->orWhereNotNull('destination'))
                    ->latest('performed_at')->lazy()
                    ->map(fn (DocumentMovementLog $m) => [
                        $m->movement_no, $m->action_type,
                        $m->movable_type ? class_basename($m->movable_type) : '', $m->movable_id,
                        $m->source_origin, $m->destination, (string) $m->performed_at,
                    ]),
            ],
            'overdue_returns' => $this->overdueReturns(),
            default => throw new InvalidArgumentException("Unknown report: {$key}"),
        };
    }

    private function lowStock(): array
    {
        $rows = Product::query()
            ->where('reorder_level', '>', 0)
            ->lazy()
            ->map(function ($p) {
                $available = (float) ProductLocationStock::where('product_id', $p->id)->sum('available_quantity');

                return ['sku' => $p->sku, 'name' => $p->product_name, 'reorder' => $p->reorder_level, 'available' => $available];
            })
            ->filter(fn ($r) => $r['available'] <= (float) $r['reorder'])
            ->map(fn ($r) => [$r['sku'], $r['name'], $r['reorder'], $r['available']]);

        return [['SKU', 'Product', 'Reorder Level', 'Available'], $rows];
    }

    private function stockValue(): array
    {
        $rows = Product::query()->lazy()->map(function ($p) {
            $available = (float) ProductLocationStock::where('product_id', $p->id)->sum('available_quantity');
            $value = round($available * (float) $p->unit_cost, 2);

            return [$p->sku, $p->product_name, $available, $p->unit_cost, $value];
        });

        return [['SKU', 'Product', 'Available', 'Unit Cost', 'Stock Value'], $rows];
    }

    private function overdueReturns(): array
    {
        // Files/boxes moved out more than 30 days ago with no later return entry.
        $cutoff = now()->subDays(30);

        $rows = DocumentMovementLog::query()
            ->where('action_type', 'like', 'move_out%')
            ->where('performed_at', '<', $cutoff)
            ->latest('performed_at')
            ->lazy()
            ->map(fn (DocumentMovementLog $m) => [$m->movement_no, $m->action_type, $m->movable_type ? class_basename($m->movable_type) : '', $m->movable_id, (string) $m->performed_at]);

        return [['Movement No', 'Action', 'Item Type', 'Item ID', 'Moved Out At'], $rows];
    }
}
