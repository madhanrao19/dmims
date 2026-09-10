{{-- Print only the label grid below, not the heading/toolbar/close button
     around it — same "hide everything, unhide .dmims-print-label" trick as
     the single-record label view, defined once here rather than once per
     included label (barcode-label.blade.php skips its own copy when
     standalone:false). --}}
<style>
    @media print {
        body * { visibility: hidden !important; }
        .dmims-print-label, .dmims-print-label * { visibility: visible !important; }
        .dmims-print-label-grid { position: fixed; inset: 0; margin: 0; }
    }
</style>

<div class="flex justify-end print:hidden mb-2">
    <button type="button" onclick="window.print()" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
        Print
    </button>
</div>
<div class="dmims-print-label-grid grid grid-cols-2 gap-4 print:grid-cols-3">
    @foreach ($registries as $item)
        @php
            // $item is either a plain BarcodeRegistry (Barcode Center's own
            // Batch Print) or ['registry' => ..., 'title' => ...] (Box/
            // Document File bulk "Print Barcode", which knows the record's
            // descriptive title, e.g. a box label or document title).
            $registry = $item['registry'] ?? $item;
            $itemTitle = $item['title'] ?? null;
        @endphp
        <div class="rounded border border-gray-200 dark:border-gray-700 print:border-0">
            @include('filament.barcode-label', ['barcode' => $registry->barcode, 'type' => $registry->barcode_type, 'size' => $size ?? 'small', 'title' => $itemTitle, 'standalone' => false])
        </div>
    @endforeach
</div>
