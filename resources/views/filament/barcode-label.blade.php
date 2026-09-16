@php
    // Render a scannable Code128 image when a barcode library is installed
    // (picqer/php-barcode-generator on the PHP 8.4 production target); otherwise
    // show the human-readable value, which can still be entered in the scanner.
    $size ??= 'medium';
    $title ??= null;
    $copies ??= 1;
    $showName ??= true;
    $showBarcodeText ??= true;
    // true when rendered as the top-level single-record print modal; false
    // when included inside batch-barcode-labels.blade.php, which owns the
    // Print button/script itself.
    $standalone ??= true;
    // Some records (e.g. system-generated document files with no
    // descriptive title) have their $title default to the barcode value
    // itself, which would otherwise print the same code twice: once as
    // the heading, once again below the scannable image. Also gated on the
    // "Show Name" toggle.
    $showTitle = $showName && ! empty($title) && trim((string) $title) !== trim((string) $barcode);
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
    {{-- One data-print-target wrapping every copy: the global print handler
         (FilamentPanelProvider) grabs a single [data-print-target] element's
         outerHTML, so "Copies" repeats the label inside it rather than
         adding more [data-print-target] elements it would never see. --}}
    <div data-print-target>
        @for ($i = 0; $i < max(1, (int) $copies); $i++)
            <div class="dmims-barcode-item flex flex-col items-center gap-3 py-4 text-center">
                @if ($showTitle)
                    <div class="font-semibold">{{ $title }}</div>
                @endif
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ str($type)->headline() }}</div>

                @if ($svg)
                    {{-- The SVG generator sets explicit pixel width/height attributes,
                         which at the "Large" size (module width 3) can exceed a
                         batch-print grid column and overflow into the next one; this
                         constrains it back to its container, which browsers scale
                         proportionally (SVG intrinsic aspect ratio is preserved, same
                         as an <img>). @once keeps this from repeating once per label
                         when this partial is included many times in a batch. --}}
                    @once
                        <style>.dmims-barcode-item svg { max-width: 100%; height: auto; }</style>
                    @endonce
                    <div>{!! $svg !!}</div>
                @endif

                {{-- "Show Barcode" hides this text line only — the scannable
                     image above is never hidden by it. When no image
                     generator is installed, the text stays forced on
                     regardless: it's the only thing left identifying the
                     label at all. --}}
                @if ($showBarcodeText || ! $svg)
                    <div class="font-mono {{ $fontSize }} font-semibold tracking-widest">{{ $barcode }}</div>
                @endif

                @unless ($svg)
                    <p class="text-xs text-gray-500">
                        Scannable image requires the barcode library (installed on production).
                    </p>
                @endunless
            </div>
        @endfor
    </div>

    @if ($standalone)
        <div class="flex justify-end gap-2 mt-2">
            <button type="button" data-dmims-print class="fi-btn fi-btn-size-md inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                Print
            </button>
        </div>
    @endif
</div>

{{-- The print click is handled by a single delegated listener bound once,
     globally, by FilamentPanelProvider's BODY_END render hook — see that
     file for why (both this modal's own content and Filament's page
     navigation inject HTML without executing any <script> inside it, so
     neither an inline onclick handler nor a per-modal <script> tag here
     can ever run). --}}
