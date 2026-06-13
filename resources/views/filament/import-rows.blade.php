<div class="overflow-x-auto text-sm">
    <table class="w-full text-left">
        <thead>
            <tr class="border-b">
                <th class="py-2 pr-4 font-semibold">#</th>
                <th class="py-2 pr-4 font-semibold">Status</th>
                <th class="py-2 pr-4 font-semibold">Data</th>
                <th class="py-2 font-semibold">Errors</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b align-top">
                    <td class="py-2 pr-4">{{ $row->row_number }}</td>
                    <td class="py-2 pr-4">
                        <span @class([
                            'rounded px-2 py-0.5 text-xs',
                            'bg-success-100 text-success-700' => $row->validation_status === 'imported',
                            'bg-danger-100 text-danger-700' => in_array($row->validation_status, ['invalid', 'failed']),
                            'bg-gray-100 text-gray-700' => ! in_array($row->validation_status, ['imported', 'invalid', 'failed']),
                        ])>
                            {{ $row->validation_status }}
                        </span>
                    </td>
                    <td class="py-2 pr-4">
                        <code class="text-xs">{{ \Illuminate\Support\Str::limit(json_encode($row->row_data), 120) }}</code>
                    </td>
                    <td class="py-2 text-danger-600 text-xs">
                        {{ $row->error_messages ? implode('; ', \Illuminate\Support\Arr::flatten($row->error_messages)) : '' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-2 text-gray-500">No rows.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
