@props([
    'variant' => 'default', // default, success, error, warning, info
    'size' => 'md' // sm, md, lg
])

@php
$variants = [
    'default' => 'bg-gray-100 text-gray-800',
    'success' => 'bg-green-100 text-green-800',
    'error' => 'bg-red-100 text-red-800',
    'warning' => 'bg-yellow-100 text-yellow-800',
    'info' => 'bg-blue-100 text-blue-800',
];

$sizes = [
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-1 text-sm',
    'lg' => 'px-3 py-1.5 text-base',
];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium {$variants[$variant]} {$sizes[$size]}"]) }}>
    {{ $slot }}
</span>
