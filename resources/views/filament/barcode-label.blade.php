@php
    // Render a scannable Code128 image when a barcode library is installed
    // (picqer/php-barcode-generator on the PHP 8.4 production target); otherwise
    // show the human-readable value, which can still be entered in the scanner.
    $size ??= 'medium';
    $title ??= null;
    // true when rendered as the top-level single-record print modal; false
    // when included inside batch-barcode-labels.blade.php, which owns the
    // print-only-the-labels <style> block itself (avoids repeating one
    // identical <style> tag per label in a bulk print of N labels).
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

@if ($standalone)
    {{-- Printing only the label itself, not the modal heading/size selector/
         Print/Close buttons around it: hide everything in the page except
         elements inside .dmims-print-label, then take that element out of
         the modal's cropped/scrolled layout for the print pass. --}}
    <style>
        @media print {
            body * { visibility: hidden !important; }
            .dmims-print-label, .dmims-print-label * { visibility: visible !important; }
            .dmims-print-label { position: fixed; inset: 0; margin: 0; }
        }
    </style>
@endif

<div class="dmims-print-label flex flex-col items-center gap-3 py-4 text-center print:break-inside-avoid">
    @if (! empty($title))
        <div class="font-semibold">{{ $title }}</div>
    @endif
    <div class="text-xs uppercase tracking-wide text-gray-500 print:hidden">{{ str($type)->headline() }}</div>

    @if ($svg)
        <div>{!! $svg !!}</div>
    @endif

    <div class="font-mono {{ $fontSize }} font-semibold tracking-widest">{{ $barcode }}</div>

    @unless ($svg)
        <p class="text-xs text-gray-500 print:hidden">
            Scannable image requires the barcode library (installed on production).
        </p>
    @endunless
</div>

@if ($standalone)
    <div class="flex justify-end gap-2 print:hidden mt-2">
        <button type="button" onclick="window.print()" class="fi-btn fi-btn-size-md inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
            Print
        </button>
    </div>
@endif
