<?php

namespace App\Filament\Concerns;

use App\Services\BarcodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a "Print Barcode" table action to a resource: it generates and
 * registers the record's barcode on first use (idempotent), shows a
 * printable label with a label-size choice, and prints directly from the
 * browser (window.print(), triggered client-side from inside the modal —
 * no "mark as printed" confirmation step). printed_count is incremented in
 * ->mountUsing(), which runs exactly once when the modal opens — unlike
 * ->modalContent(), which Filament re-evaluates on every Livewire render
 * (including the label-size Select's own ->live() updates), so incrementing
 * there counted every preview re-render as a print, not just the one open.
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
                TextInput::make('copies')
                    ->label('Copies')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(),
            ])
            // Reads the field's default (1, unless overridden) rather than a
            // live-updated value: printing itself is fully client-side
            // (window.print(), no server round trip — see this file's own
            // top comment), so there is no reliable later hook to catch
            // whatever "Copies" was actually set to at the moment of the
            // real print click. printed_count is therefore "modal opens",
            // an honest lower bound, not an exact physical-copy count.
            ->mountUsing(function (Schema $schema, Model $record): void {
                $schema->fill();
                app(BarcodeService::class)->incrementPrinted(
                    app(BarcodeService::class)->registerFor($record),
                    (int) ($schema->getState()['copies'] ?? 1),
                );
            })
            ->modalContent(function (Model $record, array $data) {
                $registry = app(BarcodeService::class)->registerFor($record);

                return view('filament.barcode-label', [
                    'barcode' => $registry->barcode,
                    'type' => $registry->barcode_type,
                    'title' => static::barcodeLabelTitle($record),
                    'size' => $data['size'] ?? 'medium',
                    'copies' => max(1, (int) ($data['copies'] ?? 1)),
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
                TextInput::make('copies')
                    ->label('Copies (each)')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(),
            ])
            // See barcodeAction()'s own mountUsing() comment — same "opens,
            // not an exact physical-copy count" reasoning, applied per
            // selected record.
            ->mountUsing(function (Schema $schema, Collection $records): void {
                $schema->fill();
                $copies = (int) ($schema->getState()['copies'] ?? 1);
                $records->each(fn (Model $record) => app(BarcodeService::class)->incrementPrinted(
                    app(BarcodeService::class)->registerFor($record),
                    $copies,
                ));
            })
            ->modalContent(function (Collection $records, array $data) {
                $registries = $records->map(fn (Model $record): array => [
                    'registry' => app(BarcodeService::class)->registerFor($record),
                    'title' => static::barcodeLabelTitle($record),
                ]);

                return view('filament.batch-barcode-labels', [
                    'registries' => $registries,
                    'size' => $data['size'] ?? 'small',
                    'copies' => max(1, (int) ($data['copies'] ?? 1)),
                ]);
            });
    }

    /** Short human label shown above the barcode value on a printed label. */
    protected static function barcodeLabelTitle(Model $record): ?string
    {
        return $record->title ?? $record->box_number ?? null;
    }
}
