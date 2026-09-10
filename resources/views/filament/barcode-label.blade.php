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
