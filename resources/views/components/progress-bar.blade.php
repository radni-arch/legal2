@props([
    'percentage' => 0,
    'label' => null,
    'color' => 'blue'
])

@php
    $cappedPercentage = min($percentage, 100);
    $colorClass = match($color) {
        'green' => 'bg-green-600',
        'red' => 'bg-red-600',
        'yellow' => 'bg-yellow-600',
        default => 'bg-blue-600',
    };
@endphp

<div class="w-full">
    @if($label)
        <div class="flex justify-between mb-1">
            <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
            <span class="text-sm font-medium text-gray-700">{{ $cappedPercentage }}%</span>
        </div>
    @endif
    <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div class="{{ $colorClass }} h-2.5 rounded-full" style="width: {{ $cappedPercentage }}%"></div>
    </div>
    @if(!$label)
        <div class="text-right mt-1">
            <span class="text-xs text-gray-600">{{ $cappedPercentage }}%</span>
        </div>
    @endif
</div>
