<?php

namespace App\Filament\Concerns;

use App\Services\BarcodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as LivewireComponent;

/**
 * Adds a "Print Barcode" table action to a resource: it generates and
 * registers the record's barcode on first use (idempotent), shows a
 * printable label with a label-size choice, and prints directly from the
 * browser (window.print(), triggered client-side from inside the modal —
 * no "mark as printed" confirmation step). printed_count is incremented in
 * ->mountUsing(), which runs exactly once when the modal opens.
 *
 * modalContent()'s closure MUST read the live size/copies fields via
 * liveActionData($livewire), not `array $data` — regression found 16
 * September 2026: `array $data` resolves to Action::getData(), a
 * property only ever populated by the mounted-action SUBMIT flow
 * (Filament\Actions\Concerns\InteractsWithActions calling
 * `$action->data($schemaState)` as part of validating/calling the
 * action). This modal has `->modalSubmitAction(false)` (printing is
 * client-side window.print(), no server round trip needed for the print
 * itself), so that flow never runs — `$data` stays frozen at whatever
 * ->mountUsing()'s $schema->fill() set, for the modal's entire lifetime,
 * regardless of any ->live() field the user changes afterwards.
 * `Get $get` (Filament's usual reactive-state utility) does NOT work
 * here either — it requires a "current schema component" context
 * (Action::getSchemaComponent()) that a plain table/bulk action's
 * modalContent() never has (confirmed: `Call to a member function
 * makeGetUtility() on null`). Nor does reading
 * `$livewire->getMountedActions()[0]` directly — that returns the
 * mounted Action *object* itself (same stale ->getData() underneath),
 * not the live schema state. liveActionData() instead reads
 * `$livewire->getSchema('mountedActionSchema0')->getState()` — the
 * same cached-schema lookup Filament's own action-modal Blade partial
 * uses to render these very Size/Copies fields (confirmed against the
 * rendered DOM: the Select's own `wire:key` is literally
 * "mountedActionSchema0.size"), which the label-size/copies Selects'
 * `->live()` bindings do keep genuinely current. Confirmed via real
 * printed output before this fix: three "Small"/"Medium"/"Large" label
 * prints of the same records produced byte-for-byte identical PDFs.
 */
trait HasBarcodeAction
{
    public static function barcodeAction(): Action
    {
        return Action::make('barcode')
            ->label('Print Barcode')
            ->icon('heroicon-o-qr-code')
            // 'view', not 'update' — printing a label doesn't modify the
            // record, so a role with only the resource's "view *"
            // permission (e.g. a customer who can see Locations but not
            // manage them) must still be able to print its barcode.
            ->authorize(fn (Model $record): bool => static::can('view', $record))
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
                Toggle::make('show_name')->label('Show Name')->default(true)->live(),
                Toggle::make('show_barcode')->label('Show Barcode')->default(true)->live(),
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
            ->modalContent(function (Model $record, LivewireComponent&HasSchemas $livewire) {
                $registry = app(BarcodeService::class)->registerFor($record);
                $data = static::liveActionData($livewire);

                return view('filament.barcode-label', [
                    'barcode' => $registry->barcode,
                    'type' => $registry->barcode_type,
                    'title' => static::barcodeLabelTitle($record),
                    'size' => $data['size'] ?? 'medium',
                    'copies' => max(1, (int) ($data['copies'] ?? 1)),
                    'showName' => (bool) ($data['show_name'] ?? true),
                    'showBarcodeText' => (bool) ($data['show_barcode'] ?? true),
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
            // See barcodeAction()'s own authorize() comment.
            ->authorize(fn (): bool => static::can('view'))
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
                Toggle::make('show_name')->label('Show Name')->default(true)->live(),
                Toggle::make('show_barcode')->label('Show Barcode')->default(true)->live(),
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
            ->modalContent(function (Collection $records, LivewireComponent&HasSchemas $livewire) {
                $registries = $records->map(fn (Model $record): array => [
                    'registry' => app(BarcodeService::class)->registerFor($record),
                    'title' => static::barcodeLabelTitle($record),
                ]);
                $data = static::liveActionData($livewire);

                return view('filament.batch-barcode-labels', [
                    'registries' => $registries,
                    'size' => $data['size'] ?? 'small',
                    'copies' => max(1, (int) ($data['copies'] ?? 1)),
                    'showName' => (bool) ($data['show_name'] ?? true),
                    'showBarcodeText' => (bool) ($data['show_barcode'] ?? true),
                ]);
            });
    }

    /**
     * Short human label shown above the barcode value on a printed label —
     * "Show Name" toggles this line. Location and Product have neither
     * `title` nor `box_number`, so this fell through to null and made
     * "Show Name" a silent no-op for them; `location_name`/`product_name`
     * cover those two, `title`/`box_number` the rest.
     */
    protected static function barcodeLabelTitle(Model $record): ?string
    {
        return $record->title ?? $record->box_number ?? $record->location_name ?? $record->product_name ?? null;
    }

    /**
     * `getSchema('mountedActionSchema0')` reads the same cached-schema
     * key Filament's own action-modal Blade partial uses to render this
     * action's Size/Copies fields (confirmed against the rendered DOM:
     * the Select's own `id`/`wire:key` is literally
     * "mountedActionSchema0.size") — the "0" assumes this action is
     * never nested inside another open action's modal, true for every
     * barcodeAction()/bulkBarcodeAction() call site. `getSchema()` (not
     * `getMountedActionSchema()`, which is protected) is the public
     * equivalent, declared on `Filament\Schemas\Contracts\HasSchemas`.
     *
     * @param  LivewireComponent&HasSchemas  $livewire
     * @return array<string, mixed>
     */
    protected static function liveActionData(LivewireComponent $livewire): array
    {
        return $livewire->getSchema('mountedActionSchema0')?->getState() ?? [];
    }
}
