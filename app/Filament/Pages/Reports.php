<?php

namespace App\Filament\Pages;

use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Services\AccessControlService;
use App\Services\ModuleAccessService;
use App\Services\ReportExportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property Schema $form
 */
class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    public ?array $data = [];

    /**
     * Document Reports dashboard filters (date range/status/location) — see
     * documentDashboardVisible(). Deliberately plain public properties +
     * wire:model (not a Filament Schema) since this is a read-only live
     * dashboard, not a record form; matches the reference "Apply Filters"/
     * "Reset Filters" button pair (Livewire only re-renders on the next
     * network round-trip, i.e. clicking a button, not on every keystroke).
     */
    public ?string $docFrom = null;

    public ?string $docTo = null;

    public ?string $docStatus = null;

    public ?int $docLocationId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        return $user->can('view reports')
            && app(AccessControlService::class)->moduleEnabled($user->customer_id, 'reports');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $options = [];
        foreach (ReportExportService::availableTo(auth()->user()) as $key => $def) {
            $options[$key] = "{$def['group']} — {$def['label']}";
        }

        return $schema
            ->components([
                Select::make('report')
                    ->label('Report')
                    ->options($options)
                    ->searchable()
                    ->required(),
                Select::make('format')
                    ->label('Format')
                    ->options(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'])
                    ->default('csv')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function download(): StreamedResponse
    {
        $state = $this->form->getState();
        $key = $state['report'];
        $format = $state['format'] ?? 'csv';

        // Guard against requesting a report the user is not entitled to.
        abort_unless(array_key_exists($key, ReportExportService::availableTo(auth()->user())), 403);

        return app(ReportExportService::class)->generate($key, $format);
    }

    /**
     * The live Document Reports dashboard (filters/KPIs/tables below the
     * existing export form) is scoped to tenant users with Document
     * Tracking access — matches the reference screenshot's own context.
     * Platform users span every tenant with no single customer_id to scope
     * this dashboard's aggregates to, so they keep the plain multi-report
     * export form above instead (documented scoping decision, not an
     * oversight).
     */
    public function documentDashboardVisible(): bool
    {
        $user = auth()->user();

        if (! $user || $user->is_platform_user || ! $user->customer_id) {
            return false;
        }

        return ($user->can('manage documents') || $user->can('view documents'))
            && app(ModuleAccessService::class)->isModuleEnabled($user->customer_id, 'document_tracking');
    }

    public function applyDocumentFilters(): void
    {
        // Properties are already bound via wire:model by the time this
        // fires; the method body just needs to exist so the "Apply
        // Filters" button has something to wire:click that triggers the
        // network round-trip / re-render, matching the reference's explicit
        // Apply step rather than filtering on every keystroke.
    }

    public function resetDocumentFilters(): void
    {
        $this->docFrom = null;
        $this->docTo = null;
        $this->docStatus = null;
        $this->docLocationId = null;
    }

    /** @return Builder<DocumentFile> */
    protected function filteredDocumentFilesQuery()
    {
        return DocumentFile::query()
            ->when($this->docFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->docFrom))
            ->when($this->docTo, fn ($q) => $q->whereDate('created_at', '<=', $this->docTo))
            ->when($this->docStatus, fn ($q) => $q->where('current_status', $this->docStatus))
            ->when($this->docLocationId, fn ($q) => $q->whereHas(
                'currentBox',
                fn ($bq) => $bq->where('current_location_id', $this->docLocationId)
            ));
    }

    /** @return Builder<Box> */
    protected function filteredBoxesQuery()
    {
        return Box::query()
            ->when($this->docFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->docFrom))
            ->when($this->docTo, fn ($q) => $q->whereDate('created_at', '<=', $this->docTo))
            ->when($this->docStatus, fn ($q) => $q->where('status', $this->docStatus))
            ->when($this->docLocationId, fn ($q) => $q->where('current_location_id', $this->docLocationId));
    }

    /** @return array<string, int> */
    public function documentKpis(): array
    {
        $documents = $this->filteredDocumentFilesQuery();

        return [
            'total_documents' => (clone $documents)->count(),
            'dispatched_externally' => (clone $documents)->where('current_status', 'moved_out')->count(),
            'missing_documents' => (clone $documents)->where('current_status', 'missing')->count(),
            'tracked_boxes' => $this->filteredBoxesQuery()->count(),
        ];
    }

    /** Status distribution for the simple bar breakdown — no new charting dependency, just relative widths. */
    public function documentStatusBreakdown(): array
    {
        return $this->filteredDocumentFilesQuery()
            ->selectRaw('current_status, count(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status')
            ->all();
    }

    public function recentDocuments()
    {
        return $this->filteredDocumentFilesQuery()
            ->with(['currentBox.currentLocation', 'creator'])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    public function recentBoxes()
    {
        return $this->filteredBoxesQuery()
            ->with(['currentLocation', 'creator'])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    /** @return array<int, string> */
    public function documentLocationOptions(): array
    {
        return Location::ancestryPathMap();
    }

    public function downloadDocumentsCsv(): StreamedResponse
    {
        abort_unless($this->documentDashboardVisible(), 403);

        $rows = $this->filteredDocumentFilesQuery()->with(['currentBox.currentLocation', 'creator'])->get();

        return Response::streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Created Date', 'Barcode', 'Title/Ref', 'Status', 'Box/Location', 'Creator']);
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::csvSafe(...), [
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->file_barcode,
                    $row->title,
                    $row->current_status,
                    $row->currentBox?->box_number,
                    $row->creator?->name,
                ]));
            }
            fclose($out);
        }, 'documents-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function downloadBoxesCsv(): StreamedResponse
    {
        abort_unless($this->documentDashboardVisible(), 403);

        $rows = $this->filteredBoxesQuery()->with(['currentLocation', 'creator'])->get();

        return Response::streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Created Date', 'Barcode', 'Label', 'Status', 'Current Location', 'Creator']);
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::csvSafe(...), [
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->box_barcode,
                    $row->box_number,
                    $row->status,
                    $row->currentLocation?->location_name,
                    $row->creator?->name,
                ]));
            }
            fclose($out);
        }, 'boxes-report.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Prevents CSV formula injection: several of the values above (title,
     * box_number, creator name) are free text a user typed, and a leading
     * =/+/-/@ is interpreted as a formula by Excel/Sheets when the file is
     * opened, not literal text. Prefixing with a single quote forces
     * spreadsheet apps to treat it as text while keeping the value
     * otherwise unchanged (the quote itself is never displayed).
     */
    private static function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
