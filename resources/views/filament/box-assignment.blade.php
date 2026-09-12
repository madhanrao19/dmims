<x-filament::section heading="Scan Document">
    <div class="space-y-4" x-data x-on:barcode-scanned.window="$nextTick(() => $refs.scanInput?.focus())">
        <p class="text-sm font-medium">Scan Mode is ON — scan each Document File barcode to add it to this box.</p>

        <form wire:submit="scanDocument" class="flex items-end gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="scannedFileBarcode"
                        x-ref="scanInput"
                        autofocus
                        autocomplete="off"
                        placeholder="Scan or enter a Document File barcode"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" icon="heroicon-o-qr-code">
                Scan
            </x-filament::button>
            <x-barcode-camera-button
                x-on:barcode-camera-decoded="await $wire.set('scannedFileBarcode', $event.detail); $wire.scanDocument()"
            />
        </form>
        @error('scannedFileBarcode')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    </div>
</x-filament::section>
