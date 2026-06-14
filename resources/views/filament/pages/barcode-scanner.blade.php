<x-filament-panels::page>
    <form wire:submit="scan">
        <x-filament::section>
            <div class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Scan & Open
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Scanning logs every attempt and opens the linked product, location, box or document file.
    </p>
</x-filament-panels::page>
