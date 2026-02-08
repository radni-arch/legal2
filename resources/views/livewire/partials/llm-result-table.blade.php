{{-- resources/views/livewire/partials/llm-result-table.blade.php --}}
@props(['results'])

@if(count($results) > 0 && is_array($results[0]))
    @php
        $keys = array_keys($results[0]);
        $isSimple = count($keys) <= 5;
    @endphp

    @if($isSimple)
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs text-gray-400 uppercase bg-gray-800">
                    <tr>
                        @foreach($keys as $key)
                            <th class="px-4 py-3">{{ $key }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $row)
                        <tr class="border-b border-gray-700 hover:bg-gray-800">
                            @foreach($keys as $key)
                                <td class="px-4 py-3">
                                    @if(is_array($row[$key] ?? null))
                                        <code class="text-xs">{{ json_encode($row[$key]) }}</code>
                                    @else
                                        {{ Str::limit($row[$key] ?? '-', 50) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <pre class="bg-gray-900 border border-gray-700 rounded-lg p-4 overflow-x-auto"><code class="text-sm text-gray-300">{{ json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
    @endif
@else
    <pre class="bg-gray-900 border border-gray-700 rounded-lg p-4 overflow-x-auto"><code class="text-sm text-gray-300">{{ json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
@endif
