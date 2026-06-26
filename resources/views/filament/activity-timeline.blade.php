<div class="space-y-6">
    @forelse ($entries as $day => $dayEntries)
        <div>
            <p class="mb-2 text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $day }}</p>
            <ol class="ml-2 space-y-4 border-l border-gray-300 dark:border-gray-600">
                @foreach ($dayEntries as $entry)
                    <li class="relative pl-4">
                        <span class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-primary-500"></span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $entry['time']?->format('H:i') }}</p>
                        <p class="font-medium">{{ $entry['title'] }} <span class="text-gray-500 dark:text-gray-400">by {{ $entry['actor'] }}</span></p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $entry['detail'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    @empty
        <p class="text-sm text-gray-500">No activity recorded yet.</p>
    @endforelse
</div>
