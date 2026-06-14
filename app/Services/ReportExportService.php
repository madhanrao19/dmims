<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BillingPayment;
use App\Models\BillingRecord;
use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\StockMovement;
use InvalidArgumentException;
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
     * @return array<string, array{label: string, group: string, platform: bool}>
     */
    public static function definitions(): array
    {
        return [
            // Platform reports (TDD §22)
            'customer_summary' => ['label' => 'Customer Summary', 'group' => 'Platform', 'platform' => true],
            'subscription_summary' => ['label' => 'Subscription Summary', 'group' => 'Platform', 'platform' => true],
            'license_summary' => ['label' => 'License Summary', 'group' => 'Platform', 'platform' => true],
            'billing_summary' => ['label' => 'Billing Summary', 'group' => 'Platform', 'platform' => false],
            'payment_summary' => ['label' => 'Payment Summary', 'group' => 'Platform', 'platform' => false],
            'audit_summary' => ['label' => 'Audit Report', 'group' => 'Platform', 'platform' => true],
            // Inventory reports
            'inventory_summary' => ['label' => 'Inventory Summary', 'group' => 'Inventory', 'platform' => false],
            'low_stock' => ['label' => 'Low Stock', 'group' => 'Inventory', 'platform' => false],
            'stock_movement' => ['label' => 'Stock Movement', 'group' => 'Inventory', 'platform' => false],
            'stock_value' => ['label' => 'Stock Value', 'group' => 'Inventory', 'platform' => false],
            // Document reports
            'file_master' => ['label' => 'File Master', 'group' => 'Document', 'platform' => false],
            'box_master' => ['label' => 'Box Master', 'group' => 'Document', 'platform' => false],
            'movement_history' => ['label' => 'Movement History', 'group' => 'Document', 'platform' => false],
            'overdue_returns' => ['label' => 'Overdue Returns', 'group' => 'Document', 'platform' => false],
        ];
    }

    /**
     * Reports a user may run: platform-only reports require a platform user.
     *
     * @return array<string, array{label: string, group: string, platform: bool}>
     */
    public static function availableTo($user): array
    {
        return array_filter(
            static::definitions(),
            fn (array $def) => ! $def['platform'] || (bool) $user?->is_platform_user,
        );
    }

    public function generate(string $key): StreamedResponse
    {
        if (! isset(static::definitions()[$key])) {
            throw new InvalidArgumentException("Unknown report: {$key}");
        }

        [$headers, $rows] = $this->build($key);
        $fileName = $key.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: list<string>, 1: iterable<array>}
     */
    public function build(string $key): array
    {
        return match ($key) {
            'customer_summary' => [
                ['ID', 'Company', 'Code', 'Status', 'Created'],
                Customer::query()->get()->map(fn ($c) => [$c->id, $c->company_name, $c->company_code, $c->status, (string) $c->created_at]),
            ],
            'subscription_summary' => [
                ['Subscription No', 'Company', 'Status', 'Valid From', 'Valid To'],
                CustomerSubscription::with('customer')->get()->map(fn ($s) => [$s->subscription_no, $s->customer?->company_name, $s->status, (string) $s->valid_from, (string) $s->valid_to]),
            ],
            'license_summary' => [
                ['License No', 'Company', 'Status', 'Access Mode', 'Valid To'],
                License::with('customer')->get()->map(fn ($l) => [$l->license_no, $l->customer?->company_name, $l->status, $l->technical_access_mode, (string) $l->valid_to]),
            ],
            'billing_summary' => [
                ['Invoice No', 'Company', 'Total', 'Billing Status', 'Payment Status', 'Due Date'],
                BillingRecord::with('customer')->get()->map(fn ($b) => [$b->invoice_no, $b->customer?->company_name, $b->total_amount, $b->billing_status, $b->payment_status, (string) $b->due_date]),
            ],
            'payment_summary' => [
                ['Payment No', 'Invoice', 'Amount', 'Method', 'Date'],
                BillingPayment::with('billingRecord')->get()->map(fn ($p) => [$p->payment_no, $p->billingRecord?->invoice_no, $p->amount, $p->payment_method, (string) $p->payment_date]),
            ],
            'audit_summary' => [
                ['Date', 'Module', 'Action', 'User ID', 'Auditable'],
                AuditLog::latest()->limit(5000)->get()->map(fn ($a) => [(string) $a->created_at, $a->module, $a->action, $a->user_id, class_basename((string) $a->auditable_type).'#'.$a->auditable_id]),
            ],
            'inventory_summary' => [
                ['SKU', 'Product', 'Reorder Level', 'Unit Cost', 'Unit Price', 'Status'],
                Product::query()->get()->map(fn ($p) => [$p->sku, $p->product_name, $p->reorder_level, $p->unit_cost, $p->unit_price, $p->status]),
            ],
            'low_stock' => $this->lowStock(),
            'stock_movement' => [
                ['Movement No', 'Product ID', 'Type', 'Qty', 'From', 'To', 'Performed At'],
                StockMovement::query()->latest('performed_at')->get()->map(fn ($m) => [$m->movement_no, $m->product_id, $m->movement_type, $m->quantity, $m->from_location_id, $m->to_location_id, (string) $m->performed_at]),
            ],
            'stock_value' => $this->stockValue(),
            'file_master' => [
                ['Reference No', 'Title', 'Status', 'Box ID'],
                DocumentFile::query()->get()->map(fn ($f) => [$f->file_reference_no, $f->title, $f->current_status, $f->current_box_id]),
            ],
            'box_master' => [
                ['Box No', 'Barcode', 'Location ID', 'Status'],
                Box::query()->get()->map(fn ($b) => [$b->box_number, $b->box_barcode, $b->current_location_id, $b->status]),
            ],
            'movement_history' => [
                ['Movement No', 'Action', 'From Box', 'To Box', 'Performed At'],
                DocumentMovementLog::query()->latest('performed_at')->get()->map(fn ($m) => [$m->movement_no, $m->action_type, $m->from_box_id, $m->to_box_id, (string) $m->performed_at]),
            ],
            'overdue_returns' => $this->overdueReturns(),
            default => throw new InvalidArgumentException("Unknown report: {$key}"),
        };
    }

    private function lowStock(): array
    {
        $rows = Product::query()
            ->where('reorder_level', '>', 0)
            ->get()
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
        $rows = Product::query()->get()->map(function ($p) {
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
            ->get()
            ->map(fn ($m) => [$m->movement_no, $m->action_type, $m->movable_type ? class_basename($m->movable_type) : '', $m->movable_id, (string) $m->performed_at]);

        return [['Movement No', 'Action', 'Item Type', 'Item ID', 'Moved Out At'], $rows];
    }
}
