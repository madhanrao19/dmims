<div class="grid grid-cols-2 gap-4 print:grid-cols-3">
    @foreach ($registries as $registry)
        <div class="rounded border border-gray-200 dark:border-gray-700">
            @include('filament.barcode-label', ['barcode' => $registry->barcode, 'type' => $registry->barcode_type, 'size' => $size ?? 'small'])
        </div>
    @endforeach
</div>
