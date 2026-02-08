@props([
    'label' => '',
    'value' => '',
    'change' => null,
    'trend' => null
])

<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <dt class="text-sm font-medium text-gray-500 truncate">{{ $label }}</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $value }}</dd>
        @if($change)
            <p class="mt-2 text-sm {{ $trend === 'up' ? 'text-green-600' : ($trend === 'down' ? 'text-red-600' : 'text-gray-600') }}">
                {{ $change }}
            </p>
        @endif
    </div>
</div>
