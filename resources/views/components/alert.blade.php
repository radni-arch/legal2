@props([
    'type' => 'info', // success, error, warning, info
    'dismissible' => false,
    'icon' => true
])

@php
$config = [
    'success' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle'],
    'error' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'exclamation-circle'],
    'warning' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'exclamation-triangle'],
    'info' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'information-circle']
][$type];

$iconPaths = [
    'check-circle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'exclamation-circle' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'exclamation-triangle' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'information-circle' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
];
@endphp

<div {{ $attributes->merge(['class' => "{$config['bg']} {$config['text']} p-4 rounded-lg"]) }}
     @if($dismissible) x-data="{ show: true }" x-show="show" @endif>
    <div class="flex items-start">
        @if($icon)
        <svg class="w-5 h-5 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths[$config['icon']] }}" />
        </svg>
        @endif
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
        <button x-on:click="show = false" class="ml-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        @endif
    </div>
</div>
