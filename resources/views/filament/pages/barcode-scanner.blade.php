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

                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Scan & Open
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Scanning logs every attempt and resolves products, locations, boxes, or
        document files automatically. Turn on bulk scan to keep scanning
        without leaving this page.
    </p>

    <x-filament::section class="mt-6" heading="Recent scans">
        <div class="space-y-2">
            @forelse ($this->recentScans as $scan)
                <div class="flex items-center justify-between text-sm">
                    <span class="font-mono">{{ $scan->barcode }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $scan->barcode_type ?? '—' }}</span>
                    <x-filament::badge :color="match ($scan->scan_result) {
                        'found' => 'success',
                        'inactive' => 'warning',
                        default => 'danger',
                    }">
                        {{ ucfirst($scan->scan_result) }}
                    </x-filament::badge>
                    <span class="text-gray-500 dark:text-gray-400">{{ $scan->scanned_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No scans yet.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
