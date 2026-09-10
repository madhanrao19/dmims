<?php

namespace App\Filament\Concerns;

use App\Services\BarcodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a "Barcode" table action to a resource: it generates and registers the
 * record's barcode on first use (idempotent), shows a printable label, and
 * records each print.
 */
trait HasBarcodeAction
{
    public static function barcodeAction(): Action
    {
        return Action::make('barcode')
            ->label('Print Barcode')
            ->icon('heroicon-o-qr-code')
            ->authorize(fn (Model $record): bool => static::can('update', $record))
            ->modalHeading('Barcode label')
            ->modalSubmitActionLabel('Mark as printed')
            ->modalContent(function (Model $record) {
                $registry = app(BarcodeService::class)->registerFor($record);

                return view('filament.barcode-label', [
                    'barcode' => $registry->barcode,
                    'type' => $registry->barcode_type,
                    'title' => static::barcodeLabelTitle($record),
                ]);
            })
            ->action(function (Model $record): void {
                $registry = app(BarcodeService::class)->registerFor($record);
                app(BarcodeService::class)->incrementPrinted($registry);

                Notification::make()
                    ->title("Barcode printed: {$registry->barcode}")
                    ->success()
                    ->send();
            });
    }

    /**
     * Bulk sibling of barcodeAction() — one modal previewing every selected
     * record's label (reusing the existing batch-barcode-labels view built
     * for Barcode Center's own Batch Print), registering a barcode for any
     * record that doesn't have one yet, same as the single-record action.
     * Reprinting via this action never creates a duplicate registry entry:
     * BarcodeService::registerFor() is idempotent (registers once, then
     * returns the existing row for every later call).
     */
    public static function bulkBarcodeAction(): BulkAction
    {
        return BulkAction::make('bulkBarcode')
            ->label('Print Barcode')
            ->icon('heroicon-o-qr-code')
            ->authorize(fn (): bool => static::can('update'))
            ->modalHeading('Barcode labels')
            ->modalSubmitActionLabel('Mark as printed')
            ->modalContent(function (Collection $records) {
                $registries = $records->map(fn (Model $record) => [
                    'registry' => app(BarcodeService::class)->registerFor($record),
                    'title' => static::barcodeLabelTitle($record),
                ]);

                return view('filament.batch-barcode-labels', [
                    'registries' => $registries,
                    'size' => 'medium',
                ]);
            })
            ->action(function (Collection $records): void {
                $records->each(function (Model $record): void {
                    $registry = app(BarcodeService::class)->registerFor($record);
                    app(BarcodeService::class)->incrementPrinted($registry);
                });

                Notification::make()
                    ->title($records->count().' barcode(s) printed')
                    ->success()
                    ->send();
            });
    }

    /** Short human label shown above the barcode value on a printed label. */
    protected static function barcodeLabelTitle(Model $record): ?string
    {
        return $record->title ?? $record->box_number ?? null;
    }
}
