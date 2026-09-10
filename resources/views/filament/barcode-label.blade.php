@php
    // Render a scannable Code128 image when a barcode library is installed
    // (picqer/php-barcode-generator on the PHP 8.4 production target); otherwise
    // show the human-readable value, which can still be entered in the scanner.
    $size ??= 'medium';
    $title ??= null;
    // true when rendered as the top-level single-record print modal; false
    // when included inside batch-barcode-labels.blade.php, which owns the
    // Print button/script itself.
    $standalone ??= true;
    [$width, $height, $fontSize] = match ($size) {
        'small' => [1.5, 40, 'text-lg'],
        'large' => [3, 90, 'text-3xl'],
        default => [2, 60, 'text-2xl'],
    };
    $svg = null;
    if (class_exists(\Picqer\Barcode\BarcodeGeneratorSVG::class)) {
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
        $svg = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, $width, $height);
    }
@endphp

<div @if ($standalone) data-print-root @endif>
    <div data-print-target class="flex flex-col items-center gap-3 py-4 text-center">
        @if (! empty($title))
            <div class="font-semibold">{{ $title }}</div>
        @endif
        <div class="text-xs uppercase tracking-wide text-gray-500">{{ str($type)->headline() }}</div>

        @if ($svg)
            <div>{!! $svg !!}</div>
        @endif

        <div class="font-mono {{ $fontSize }} font-semibold tracking-widest">{{ $barcode }}</div>

        @unless ($svg)
            <p class="text-xs text-gray-500">
                Scannable image requires the barcode library (installed on production).
            </p>
        @endunless
    </div>

    @if ($standalone)
        <div class="flex justify-end gap-2 mt-2">
            <button type="button" onclick="dmimsPrintLabel(this)" class="fi-btn fi-btn-size-md inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                Print
            </button>
        </div>
    @endif
</div>

@if ($standalone)
    <script>
        // Prints the label in a separate window carrying the app's own
        // compiled stylesheets, so the printed page matches this on-screen
        // preview exactly instead of fighting the live admin page's DOM
        // with print-only visibility CSS (the previous approach — fragile,
        // and broke badly on multi-item grids in Chrome's print engine).
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
@endif
