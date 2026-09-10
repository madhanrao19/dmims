<?php

namespace App\Filament\Concerns;

use App\Services\BarcodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a "Print Barcode" table action to a resource: it generates and
 * registers the record's barcode on first use (idempotent), shows a
 * printable label with a label-size choice, and prints directly from the
 * browser (window.print(), triggered client-side from inside the modal —
 * no "mark as printed" confirmation step). printed_count is incremented the
 * moment the label is opened for viewing/printing, since there is no longer
 * a server round-trip on the print click itself to hang it off.
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
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema([
                Select::make('size')
                    ->label('Label size')
                    ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                    ->default('medium')
                    ->live(),
            ])
            ->modalContent(function (Model $record, array $data) {
                $registry = app(BarcodeService::class)->registerFor($record);
                app(BarcodeService::class)->incrementPrinted($registry);

                return view('filament.barcode-label', [
                    'barcode' => $registry->barcode,
                    'type' => $registry->barcode_type,
                    'title' => static::barcodeLabelTitle($record),
                    'size' => $data['size'] ?? 'medium',
                ]);
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
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema([
                Select::make('size')
                    ->label('Label size')
                    ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                    ->default('small')
                    ->live(),
            ])
            ->modalContent(function (Collection $records, array $data) {
                $registries = $records->map(function (Model $record) {
                    $registry = app(BarcodeService::class)->registerFor($record);
                    app(BarcodeService::class)->incrementPrinted($registry);

                    return [
                        'registry' => $registry,
                        'title' => static::barcodeLabelTitle($record),
                    ];
                });

                return view('filament.batch-barcode-labels', [
                    'registries' => $registries,
                    'size' => $data['size'] ?? 'small',
                ]);
            });
    }

    /** Short human label shown above the barcode value on a printed label. */
    protected static function barcodeLabelTitle(Model $record): ?string
    {
        return $record->title ?? $record->box_number ?? null;
    }
}
