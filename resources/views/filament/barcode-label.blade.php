@php
    // Render a scannable Code128 image when a barcode library is installed
    // (picqer/php-barcode-generator on the PHP 8.4 production target); otherwise
    // show the human-readable value, which can still be entered in the scanner.
    $size ??= 'medium';
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

<div class="flex flex-col items-center gap-3 py-4 text-center print:break-inside-avoid">
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
