<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\ProductResource;
use App\Models\BarcodeScanLog;
use App\Models\Box;
use App\Models\DocumentFile;
use App\Services\AccessControlService;
use App\Services\DocumentMovementService;
use App\Services\ScannerService;
use Filament\Actions\Action as NotificationAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Universal Scan Center (production-readiness roadmap #2): scan anything,
 * auto-detect what it is (product/location/box/document file/unknown), and
 * either open the record or — in bulk mode — keep scanning without
 * navigating away. Built on the existing ScannerService/BarcodeScanLog.
 *
 * @property Schema $form
 */
class BarcodeScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Scan Center';

    protected string $view = 'filament.pages.barcode-scanner';

    public ?array $data = [];

    public bool $bulkMode = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        return app(AccessControlService::class)->moduleEnabled($user->customer_id, 'barcode_scanning')
            && ($user->can('manage inventory') || $user->can('manage documents'));
    }

    public function mount(): void
    {
        // Supports Box::ViewBox's "Scan Documents In" header action, which
        // links here with ?target_box_id=… pre-selected so an operator
        // doesn't have to search for the box they just came from.
        $this->form->fill([
            'target_box_id' => request()->integer('target_box_id') ?: null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('barcode')
                    ->label('Scan or enter a barcode')
                    ->placeholder('e.g. PRD-ACME-000001')
                    ->autofocus()
                    ->extraInputAttributes(['autocomplete' => 'off'])
                    ->required(),
                Toggle::make('bulkMode')
                    ->label('Bulk scan (keep scanning without opening records)')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn ($state) => $this->bulkMode = (bool) $state),
                Select::make('target_box_id')
                    ->label('Add Document Mode — Target Box')
                    ->helperText('Set a box, then scan Document File barcodes to assign each one into it.')
                    ->searchable(['box_number', 'box_barcode'])
                    ->getSearchResultsUsing(fn (string $search): array => Box::searchByNumberOrBarcode($search)->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Box::find($value)?->box_number),
            ])
            ->statePath('data');
    }

    /**
     * Last 20 scans for this tenant, most recent first — gives operators a
     * visible audit trail without leaving the page.
     */
    public function getRecentScansProperty(): Collection
    {
        $user = auth()->user();

        return BarcodeScanLog::query()
            ->when(! $user->is_platform_user, fn ($q) => $q->where('customer_id', $user->customer_id))
            ->latest('scanned_at')
            ->limit(20)
            ->get();
    }

    public function scan(): void
    {
        // Read both fields from a single getState() call, before mutating
        // $this->data below — calling getState() again afterward would
        // re-validate the (now cleared) required 'barcode' field and abort
        // the rest of this method via a ValidationException.
        $state = $this->form->getState();
        $barcode = trim((string) ($state['barcode'] ?? ''));
        $targetBoxId = $state['target_box_id'] ?? null;

        if ($barcode === '') {
            return;
        }

        $scanner = app(ScannerService::class);
        $outcome = $scanner->scan($barcode, auth()->user());

        $this->dispatch('scan-result', result: $outcome['result']);
        $this->data['barcode'] = '';

        if ($outcome['result'] === 'found'
            && $targetBoxId
            && $outcome['registry']?->reference_table === 'document_files'
            && $outcome['record'] instanceof DocumentFile) {
            $this->assignScannedFileToBox($outcome['record'], (int) $targetBoxId);

            return;
        }

        if ($outcome['result'] === 'found' && $outcome['registry']) {
            if ($this->bulkMode) {
                Notification::make()
                    ->title("Found: {$outcome['registry']->barcode}")
                    ->success()
                    ->send();

                return;
            }

            $url = $scanner->recordUrl($outcome['registry']);

            if ($url) {
                $this->redirect($url);

                return;
            }
        }

        // A reserved-but-unclaimed label (BarcodeService::reserve()) — its
        // type is already known, so redirect straight to that resource's
        // create form pre-filled with the barcode, rather than the 3-button
        // "what are you scanning?" prompt unknown barcodes get below.
        if ($outcome['result'] === 'unused' && $outcome['registry']) {
            $url = match ($outcome['registry']->barcode_type) {
                'document_file' => DocumentFileResource::getUrl('create', ['file_barcode' => $barcode]),
                'box' => BoxResource::getUrl('create', ['box_barcode' => $barcode]),
                'location' => LocationResource::getUrl('create', ['barcode' => $barcode]),
                // barcode_type's DB enum only allows these 4 values — 'product'
                // is the only one left once the others above are excluded.
                default => ProductResource::getUrl('create', ['barcode' => $barcode]),
            };

            if ($url) {
                $this->redirect($url);

                return;
            }
        }

        if ($outcome['result'] === 'unknown') {
            Notification::make()
                ->title('Unknown barcode')
                ->body("\"{$barcode}\" isn't registered yet. What are you scanning?")
                ->warning()
                ->actions([
                    NotificationAction::make('createDocument')
                        ->label('New Document')
                        ->url(DocumentFileResource::getUrl('create', ['file_barcode' => $barcode]))
                        ->button(),
                    NotificationAction::make('createBox')
                        ->label('New Box')
                        ->url(BoxResource::getUrl('create', ['box_barcode' => $barcode]))
                        ->button(),
                    NotificationAction::make('createLocation')
                        ->label('New Location')
                        ->url(LocationResource::getUrl('create', ['barcode' => $barcode]))
                        ->button(),
                ])
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(match ($outcome['result']) {
                'inactive' => 'Barcode is inactive',
                default => 'Barcode not found',
            })
            ->body("No open record for \"{$barcode}\".")
            ->warning()
            ->send();
    }

    /**
     * "Add Document Mode": while a target box is set, scanning a Document
     * File assigns it into that box (receiveInFile if it was unboxed,
     * transferFile if it's moving from another box) instead of just
     * looking it up — then stays on this page so the operator can keep
     * scanning, same as bulk mode's clear-and-stay behavior.
     */
    protected function assignScannedFileToBox(DocumentFile $file, int $boxId): void
    {
        $box = Box::find($boxId);

        if (! $box) {
            Notification::make()->title('Target box not found')->danger()->send();

            return;
        }

        // Platform users' queries aren't customer-scoped (BelongsToCustomer
        // skips them by design), so without this check a platform user
        // could scan Customer A's file while Customer B's box is the scan
        // target — silently moving a file across tenants and corrupting
        // both customers' box/file counts.
        if ($file->customer_id !== $box->customer_id) {
            Notification::make()
                ->title('Cannot assign: file and box belong to different customers')
                ->danger()
                ->send();

            return;
        }

        // canAccess() for this page only requires 'manage inventory' OR
        // 'manage documents' (it's a shared multi-purpose scanner), which is
        // not enough to permit *writing* to Document Files/Boxes — without
        // this, a Stock Inventory user (no document permission at all) could
        // reassign files they can't even see in Document Files, and it also
        // skips the license/module gating that only DocumentFileResource's
        // own authorization enforces for writes.
        if (! DocumentFileResource::can('update', $file) || ! BoxResource::can('update', $box)) {
            Notification::make()->title('You do not have permission to assign this file')->danger()->send();

            return;
        }

        try {
            $changed = app(DocumentMovementService::class)->assignFileToBox($file, $box);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Cannot assign file')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($changed
                ? "Added to box {$box->box_number}: {$file->file_barcode}"
                : "Already in box {$box->box_number}: {$file->file_barcode}")
            ->success()
            ->send();
    }
}
