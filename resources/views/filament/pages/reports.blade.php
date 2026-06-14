<x-filament-panels::page>
    <form wire:submit="download">
        <x-filament::section>
            <div class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                    Download CSV
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Reports are scoped to your organisation. CSV is available now; Excel and
        PDF are produced when the converter library is installed on the server.
    </p>
</x-filament-panels::page>
