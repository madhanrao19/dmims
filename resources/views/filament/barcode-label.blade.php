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
        // Prints the label via a hidden same-page iframe carrying the app's
        // own compiled stylesheets, so the printed page matches this
        // on-screen preview exactly. Deliberately not window.open(): a
        // popup can be silently blocked by the browser with no visible
        // error, which is the most likely cause of "clicking Print does
        // nothing" — an iframe appended to the current page has no such
        // blocker to fight. A short timeout backs up the iframe's load
        // event in case it fires before the linked stylesheet finishes
        // applying (fonts/table layout still render fine either way; this
        // just avoids ever silently not printing at all).
        function dmimsPrintLabel(btn) {
            var target = btn.closest('[data-print-root]').querySelector('[data-print-target]');
            var styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map(function (n) { return n.outerHTML; }).join('');
            var iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
            document.body.appendChild(iframe);
            iframe.contentDocument.open();
            iframe.contentDocument.write('<html><head><title>Print</title>' + styles + '</head><body style="padding:24px">' + target.outerHTML + '</body></html>');
            iframe.contentDocument.close();
            var printed = false;
            var doPrint = function () {
                if (printed) return;
                printed = true;
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(function () { iframe.remove(); }, 1000);
            };
            iframe.onload = doPrint;
            setTimeout(doPrint, 500);
        }
    </script>
@endif
