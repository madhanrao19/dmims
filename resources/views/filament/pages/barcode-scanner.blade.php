<x-filament-panels::page
    x-data="{
        play(result) {
            const tones = { found: 880, unknown: 220, inactive: 330 };
            const freq = tones[result] ?? 440;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                osc.frequency.value = freq;
                osc.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch (e) {}
        }
    }"
    x-on:scan-result.window="play($event.detail.result)"
>
    <form wire:submit="scan">
        <x-filament::section>
            <div class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-center pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                        Scan & Open
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </form>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Scanning logs every attempt and resolves products, locations, boxes, or
        document files automatically. Turn on bulk scan to keep scanning
        without leaving this page.
    </p>

    <x-filament::section class="mt-6" heading="Recent scans">
        <div class="-m-6 divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($this->recentScans as $scan)
                <div class="flex items-center justify-between gap-4 px-6 py-3">
                    <div class="flex items-center gap-3">
                        <span class="rounded-md bg-gray-100 px-2 py-1 font-mono text-sm dark:bg-white/5">{{ $scan->barcode }}</span>
                        <x-filament::badge :color="match ($scan->scan_result) {
                            'found' => 'success',
                            'inactive' => 'warning',
                            default => 'danger',
                        }">
                            {{ ucfirst($scan->scan_result) }}
                        </x-filament::badge>
                    </div>
                    <span class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                        {{ $scan->scanned_at?->diffForHumans() }}
                    </span>
                </div>
            @empty
                <p class="px-6 py-3 text-sm text-gray-500">No scans yet.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
