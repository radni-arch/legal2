@props([
    'title' => null,
    'footer' => null,
    'padding' => 'p-4',
    'variant' => 'default' // default, dark, bordered
])

@php
$classes = match($variant) {
    'dark' => 'bg-gray-800 text-white rounded-lg shadow-lg',
    'bordered' => 'bg-white border-2 border-gray-200 rounded-lg',
    default => 'bg-white rounded-lg shadow-md'
};
@endphp

<div {{ $attributes->merge(['class' => "$classes $padding"]) }}>
    @if($title)
    <div class="border-b {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pb-3 mb-3">
        <h3 class="text-lg font-semibold {{ $variant === 'dark' ? 'text-white' : 'text-gray-900' }}">
            {{ $title }}
        </h3>
    </div>
    @endif

    <div class="{{ $variant === 'dark' ? 'text-gray-300' : 'text-gray-800' }}">
        {{ $slot }}
    </div>

    @if($footer)
    <div class="border-t {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pt-3 mt-3">
        {{ $footer }}
    </div>
    @endif
</div>
