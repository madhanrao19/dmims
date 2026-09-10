<div data-print-root>
    <div class="flex justify-end mb-2">
        <button type="button" onclick="dmimsPrintLabel(this)" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
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
            <div class="rounded border border-gray-200 dark:border-gray-700">
                @include('filament.barcode-label', ['barcode' => $registry->barcode, 'type' => $registry->barcode_type, 'size' => $size ?? 'small', 'title' => $itemTitle, 'standalone' => false])
            </div>
        @endforeach
    </div>
</div>

<script>
    {{-- Same print-in-a-separate-window approach as barcode-label.blade.php's
         own standalone Print button (see that file's comment) — printing
         the grid exactly as shown here, not a stripped-down/repositioned
         version fighting the live page's DOM with print-only CSS. --}}
    function dmimsPrintLabel(btn) {
        var target = btn.closest('[data-print-root]').querySelector('[data-print-target]');
        var styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map(function (n) { return n.outerHTML; }).join('');
        var w = window.open('', '_blank');
        w.document.write('<html><head><title>Print</title>' + styles + '</head><body style="padding:24px">' + target.outerHTML + '</body></html>');
        w.document.close();
        w.onload = function () { w.focus(); w.print(); };
        w.onafterprint = function () { w.close(); };
    }
</script>
