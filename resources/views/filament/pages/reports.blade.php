<x-filament-panels::page>
    @if ($this->documentDashboardVisible())
        <x-filament::section>
            <x-slot name="heading">Document Reports</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="fi-fo-field-wrp-label text-sm font-medium">From</label>
                    <input type="date" wire:model="docFrom" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                </div>
                <div>
                    <label class="fi-fo-field-wrp-label text-sm font-medium">To</label>
                    <input type="date" wire:model="docTo" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                </div>
                <div>
                    <label class="fi-fo-field-wrp-label text-sm font-medium">Status</label>
                    <select wire:model="docStatus" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="transferred">Transferred</option>
                        <option value="moved_out">Moved Out</option>
                        <option value="archived">Archived</option>
                        <option value="missing">Missing</option>
                        <option value="damaged">Damaged</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label class="fi-fo-field-wrp-label text-sm font-medium">Location / Rack</label>
                    <select wire:model="docLocationId" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">All Locations</option>
                        @foreach ($this->documentLocationOptions() as $id => $path)
                            <option value="{{ $id }}">{{ $path }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <x-filament::button wire:click="applyDocumentFilters" icon="heroicon-o-funnel">
                    Apply Filters
                </x-filament::button>
                <x-filament::button wire:click="resetDocumentFilters" color="gray" icon="heroicon-o-arrow-path">
                    Reset Filters
                </x-filament::button>
            </div>
        </x-filament::section>

        <div wire:loading.class="opacity-50" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-4">
            @php $kpis = $this->documentKpis(); @endphp
            <x-filament::section>
                <div class="text-xs font-medium uppercase text-gray-500">Total Documents</div>
                <div class="text-2xl font-bold text-primary-600">{{ $kpis['total_documents'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs font-medium uppercase text-gray-500">Dispatched Externally</div>
                <div class="text-2xl font-bold">{{ $kpis['dispatched_externally'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs font-medium uppercase text-gray-500">Missing Documents</div>
                <div class="text-2xl font-bold text-danger-600">{{ $kpis['missing_documents'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs font-medium uppercase text-gray-500">Tracked Boxes</div>
                <div class="text-2xl font-bold text-success-600">{{ $kpis['tracked_boxes'] }}</div>
            </x-filament::section>
        </div>

        @php $breakdown = $this->documentStatusBreakdown(); @endphp
        @if (count($breakdown) > 0)
            @php $max = max($breakdown); @endphp
            <x-filament::section class="mt-4">
                <x-slot name="heading">Documents by Status</x-slot>
                <div class="space-y-2">
                    @foreach ($breakdown as $status => $count)
                        <div class="flex items-center gap-2">
                            <div class="w-28 shrink-0 text-sm capitalize">{{ str_replace('_', ' ', $status) }}</div>
                            <div class="h-4 flex-1 rounded bg-gray-100 dark:bg-gray-700">
                                <div class="h-4 rounded bg-primary-500" style="width: {{ $max > 0 ? round($count / $max * 100) : 0 }}%"></div>
                            </div>
                            <div class="w-10 shrink-0 text-right text-sm font-medium">{{ $count }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <x-filament::section class="mt-4">
            <x-slot name="heading">Recently Created Documents</x-slot>
            <x-slot name="afterHeader">
                <x-filament::button wire:click="downloadDocumentsCsv" size="xs" outlined>CSV</x-filament::button>
            </x-slot>
            @php $recentDocuments = $this->recentDocuments(); @endphp
            @if ($recentDocuments->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No documents match the selected filters.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1 pr-4">Created Date</th>
                                <th class="py-1 pr-4">Barcode</th>
                                <th class="py-1 pr-4">Title / Ref</th>
                                <th class="py-1 pr-4">Status</th>
                                <th class="py-1 pr-4">Box / Location</th>
                                <th class="py-1 pr-4">Creator</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentDocuments as $doc)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-1 pr-4">{{ $doc->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="py-1 pr-4 font-mono">{{ $doc->file_barcode }}</td>
                                    <td class="py-1 pr-4">{{ $doc->title }}</td>
                                    <td class="py-1 pr-4"><x-filament::badge>{{ $doc->current_status }}</x-filament::badge></td>
                                    <td class="py-1 pr-4">{{ $doc->currentBox?->box_number ?? '—' }}</td>
                                    <td class="py-1 pr-4">{{ $doc->creator?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section class="mt-4">
            <x-slot name="heading">Recently Created Boxes</x-slot>
            <x-slot name="afterHeader">
                <x-filament::button wire:click="downloadBoxesCsv" size="xs" outlined>CSV</x-filament::button>
            </x-slot>
            @php $recentBoxes = $this->recentBoxes(); @endphp
            @if ($recentBoxes->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No boxes match the selected filters.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1 pr-4">Created Date</th>
                                <th class="py-1 pr-4">Barcode</th>
                                <th class="py-1 pr-4">Label</th>
                                <th class="py-1 pr-4">Status</th>
                                <th class="py-1 pr-4">Current Location</th>
                                <th class="py-1 pr-4">Creator</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentBoxes as $box)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-1 pr-4">{{ $box->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="py-1 pr-4 font-mono">{{ $box->box_barcode }}</td>
                                    <td class="py-1 pr-4">{{ $box->box_number }}</td>
                                    <td class="py-1 pr-4"><x-filament::badge>{{ $box->status }}</x-filament::badge></td>
                                    <td class="py-1 pr-4">{{ $box->currentLocation?->location_name ?? '—' }}</td>
                                    <td class="py-1 pr-4">{{ $box->creator?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    <x-filament::section class="mt-4">
        <x-slot name="heading">All Reports (export)</x-slot>
        <form wire:submit="download">
            <div class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-center pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                        Download
                    </x-filament::button>
                </div>
            </div>
        </form>
    </x-filament::section>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Reports are scoped to your organisation. CSV, Excel (XLSX) and PDF are
        all available.
    </p>
</x-filament-panels::page>
