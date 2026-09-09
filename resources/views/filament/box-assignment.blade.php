<x-filament::section heading="Add Document Mode">
    <div class="space-y-4">
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="addDocumentMode" class="fi-checkbox-input rounded" />
            <span class="text-sm font-medium">Toggle ON, then scan each Document File barcode to add it to this box.</span>
        </label>

        @if ($this->addDocumentMode)
            <form wire:submit="scanDocument" class="flex items-end gap-3">
                <div class="flex-1">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="scannedFileBarcode"
                            autofocus
                            autocomplete="off"
                            placeholder="Scan or enter a Document File barcode"
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-qr-code">
                    Scan
                </x-filament::button>
            </form>
            @error('scannedFileBarcode')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        @endif
    </div>
</x-filament::section>
