<x-filament-panels::page>
    <x-filament::section>
        <nav class="flex flex-wrap items-center gap-1 text-sm">
            <button type="button" wire:click="selectLocation(null)" class="text-primary-600 hover:underline">
                All Warehouses
            </button>
            @foreach ($this->breadcrumb as $crumb)
                <span class="text-gray-400">/</span>
                <button type="button" wire:click="selectLocation({{ $crumb->id }})" class="text-primary-600 hover:underline">
                    {{ $crumb->location_name }}
                </button>
            @endforeach
        </nav>
    </x-filament::section>

    <x-filament::section heading="Locations" class="mt-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @forelse ($this->childLocations as $child)
                <button
                    type="button"
                    wire:click="selectLocation({{ $child->id }})"
                    class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 p-4 text-center hover:border-primary-400 dark:border-gray-700"
                >
                    <x-filament::icon icon="heroicon-o-building-storefront" class="h-8 w-8 text-gray-400" />
                    <span class="font-medium">{{ $child->location_name }}</span>
                    <span class="text-xs text-gray-500">{{ $child->locationType?->type_name }}</span>
                    @if ($child->box_capacity)
                        <x-filament::badge :color="$child->box_capacity_percent >= 100 ? 'danger' : ($child->box_capacity_percent >= 80 ? 'warning' : 'success')">
                            {{ $child->boxes_used_count }}/{{ $child->box_capacity }} boxes
                        </x-filament::badge>
                    @endif
                </button>
            @empty
                <p class="text-sm text-gray-500">No sub-locations here.</p>
            @endforelse
        </div>
    </x-filament::section>

    @if ($this->locationId)
        <x-filament::section heading="Boxes stored here" class="mt-4">
            <div class="space-y-2">
                @forelse ($this->boxesHere as $box)
                    <a href="{{ $this->boxUrl($box->id) }}" class="flex items-center justify-between rounded border border-gray-200 p-2 hover:border-primary-400 dark:border-gray-700">
                        <span class="font-mono">{{ $box->box_number }}</span>
                        <span class="text-sm text-gray-500">{{ $box->current_file_count }} files</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No boxes stored directly here.</p>
                @endforelse
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
