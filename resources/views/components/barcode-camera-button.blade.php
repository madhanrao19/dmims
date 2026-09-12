{{--
    Live camera barcode scan button — feature-detected (x-show="supported",
    see resources/js/barcode-camera.js), so it simply doesn't render on a
    browser without camera support rather than showing a control that would
    fail. Dispatches a 'barcode-camera-decoded' CustomEvent on every
    successful decode; each call site binds x-on:barcode-camera-decoded to
    whatever it needs (e.g. $wire.scan($event.detail)) — this component has
    no opinion about what happens with the decoded string.
--}}
<div x-data="barcodeCamera" x-show="supported" x-cloak {{ $attributes }}>
    <x-filament::button type="button" color="gray" icon="heroicon-o-camera" x-on:click="openScanner()">
        Scan with Camera
    </x-filament::button>

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="closeScanner()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <div class="w-full max-w-sm space-y-3 rounded-lg bg-white p-4 dark:bg-gray-900" x-on:click.outside="closeScanner()">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium">Scan Barcode</h3>
                <button type="button" x-on:click="closeScanner()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <div :id="readerId" class="w-full overflow-hidden rounded"></div>

            <p x-show="error" x-text="error" class="text-sm text-danger-600 dark:text-danger-400"></p>
        </div>
    </div>
</div>
