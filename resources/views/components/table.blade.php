@props([
    'headers' => [],
    'sortable' => false,
    'striped' => true,
    'hoverable' => true
])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200']) }}>
        @if(count($headers) > 0)
            <thead class="bg-gray-50">
                <tr>
                    @foreach($headers as $header)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ $header }}
                            @if($sortable)
                                <button class="ml-2">↕</button>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="{{ $striped ? 'divide-y divide-gray-200' : '' }}">
            {{ $slot }}
        </tbody>
    </table>
</div>
