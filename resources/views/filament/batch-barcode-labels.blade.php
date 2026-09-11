<div data-print-root>
    <div class="flex justify-end mb-2">
        <button type="button" data-dmims-print class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
            Print
        </button>
    </div>
    <div data-print-target class="grid grid-cols-2 gap-4">
        @foreach ($registries as $item)
            @php
                // $item is either a plain BarcodeRegistry (Barcode Center's own
                // Batch Print) or ['registry' => ..., 'title' => ...] (Box/
                // Document File bulk "Print Barcode", which knows the record's
                // descriptive title, e.g. a box label or document title).
                $registry = $item['registry'] ?? $item;
                $itemTitle = $item['title'] ?? null;
            @endphp
            {{-- No border/box around the label: printed output should be just the
                 barcode graphic and its text labels. dmims-barcode-item is targeted
                 by the print handler's injected CSS to keep one label from being
                 split across a page break in a multi-page print. --}}
            <div class="dmims-barcode-item">
                @include('filament.barcode-label', ['barcode' => $registry->barcode, 'type' => $registry->barcode_type, 'size' => $size ?? 'small', 'title' => $itemTitle, 'standalone' => false, 'copies' => $copies ?? 1])
            </div>
        @endforeach
    </div>
</div>
{{-- dmimsPrintLabel() is defined once, globally, by FilamentPanelProvider's
     BODY_END render hook (see barcode-label.blade.php for why it can't live
     in this modal partial). --}}
