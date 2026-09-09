<?php

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Models\Box;
use App\Models\DocumentFile;
use App\Services\DocumentMovementService;
use App\Services\ScannerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use InvalidArgumentException;

/**
 * Single-page Box View — Identification/Current Location/Contents/Notes
 * cards via BoxResource::infolist() (a deliberate, scoped exception to this
 * app's "no infolist() override" convention — see that method's own
 * doc-comment), plus a "Scan Mode" header toggle for the inline
 * scan-to-assign form. Documents in this Box / Physical Movement History /
 * System Activity Log render as inline RelationManager tabs below this
 * page's content (BoxResource::getRelations()), not as separate
 * sub-navigation pages.
 */
class ViewBox extends ViewRecord
{
    protected static string $resource = BoxResource::class;

    protected static ?string $navigationLabel = 'Overview';

    /**
     * Demo script (Scenario 1) expects an ON/OFF scan mode right on the
     * box's own detail page, not only via the separate Scan Center. These
     * back the `filament.box-assignment` view prepended in content() and
     * the "Scan Mode" header action below.
     */
    public bool $addDocumentMode = false;

    public string $scannedFileBarcode = '';

    /**
     * Scanning a box's barcode lands here (ScannerService::recordUrl()) —
     * without these, an operator has to go back to the Boxes list to
     * transfer/dispatch/return the very box they just scanned.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->scanModeToggleAction(),
            BoxResource::scanDocumentsInAction(),
            BoxResource::transferBoxAction(),
            BoxResource::moveOutBoxAction(),
            BoxResource::returnBoxAction(),
            BoxResource::timelineAction(),
            EditAction::make(),
        ];
    }

    /**
     * Header toggle for the "Add Document Mode" scan form below — flips
     * $addDocumentMode and re-renders; the actual scan-to-assign logic
     * lives in scanDocument() below, unchanged.
     */
    protected function scanModeToggleAction(): Action
    {
        return Action::make('scanModeToggle')
            ->label(fn (): string => $this->addDocumentMode ? 'Scan Mode: ON' : 'Scan Mode: OFF')
            ->icon(fn (): string => $this->addDocumentMode ? 'heroicon-o-bolt' : 'heroicon-o-pause')
            ->color(fn (): string => $this->addDocumentMode ? 'success' : 'gray')
            ->visible(fn (): bool => BoxResource::can('update', $this->getRecord()))
            ->action(function (): void {
                $this->addDocumentMode = ! $this->addDocumentMode;
            });
    }

    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            ViewComponent::make('filament.box-assignment')
                ->visible(fn (): bool => $this->addDocumentMode && BoxResource::can('update', $this->getRecord())),
            ...$schema->getComponents(),
        ]);
    }

    /**
     * Mirrors BarcodeScanner::assignScannedFileToBox() but scoped to this
     * box, so an operator can run the whole "toggle on, scan each file,
     * toggle off" step of the demo script without leaving this page.
     */
    public function scanDocument(): void
    {
        if (! $this->addDocumentMode) {
            return;
        }

        /** @var Box $box */
        $box = $this->getRecord();

        if (! BoxResource::can('update', $box)) {
            return;
        }

        $this->validate([
            'scannedFileBarcode' => ['required', 'string', 'max:150'],
        ]);

        $barcode = trim($this->scannedFileBarcode);
        $outcome = app(ScannerService::class)->scan($barcode, auth()->user());
        $this->scannedFileBarcode = '';

        if ($outcome['result'] !== 'found' || ! $outcome['record'] instanceof DocumentFile) {
            Notification::make()
                ->title('Not a Document File barcode')
                ->body("\"{$barcode}\" did not resolve to a Document File.")
                ->warning()
                ->send();

            return;
        }

        $file = $outcome['record'];

        // Platform users' queries aren't customer-scoped (BelongsToCustomer
        // skips them by design), so without this check a platform user
        // viewing Customer B's box could scan Customer A's file into it.
        if ($file->customer_id !== $box->customer_id) {
            Notification::make()
                ->title('Cannot assign: file and box belong to different customers')
                ->danger()
                ->send();

            return;
        }

        // The box's own 'update' permission (checked above) doesn't imply
        // permission to write to this particular Document File.
        if (! DocumentFileResource::can('update', $file)) {
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

        $this->record->refresh();
    }
}
