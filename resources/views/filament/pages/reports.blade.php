<x-filament-panels::page>
    <form wire:submit="download">
        <x-filament::section>
            <div class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-center pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                        Download
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </form>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Reports are scoped to your organisation. CSV, Excel (XLSX) and PDF are
        all available.
    </p>
</x-filament-panels::page>
