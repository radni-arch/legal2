@props(['align' => 'right', 'width' => '48'])

@php
$alignmentClasses = [
    'left' => 'origin-top-left left-0',
    'right' => 'origin-top-right right-0',
];

$widthClasses = [
    '48' => 'w-48',
    '64' => 'w-64',
];

$classes = ($alignmentClasses[$align] ?? $alignmentClasses['right']) . ' ' . ($widthClasses[$width] ?? $widthClasses['48']);
@endphp

<div class="relative inline-block" x-data="{ open: false }">
    <div @click="open = !open">
        {{ $trigger }}
    </div>

    <div x-show="open"
         @click.away="open = false"
         class="absolute z-50 mt-2 rounded-md shadow-lg {{ $classes }}"
         style="display: none;">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-white">
            {{ $slot }}
        </div>
    </div>
</div>
