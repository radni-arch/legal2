@props([
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'disabled' => false,
    'icon' => null,
    'iconPosition' => 'left'
])

@php
$variants = [
    'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
    'secondary' => 'bg-gray-600 hover:bg-gray-700 text-white',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    'success' => 'bg-green-600 hover:bg-green-700 text-white',
    'outline' => 'border-2 border-gray-300 hover:bg-gray-50 text-gray-700',
];

$sizes = [
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-base',
    'lg' => 'px-6 py-3 text-lg',
];

$classes = "{$variants[$variant]} {$sizes[$size]} rounded-lg font-medium transition-colors duration-200";
if ($disabled || $loading) {
    $classes .= ' opacity-50 cursor-not-allowed';
}
@endphp

<button {{ $attributes->merge(['class' => $classes, 'disabled' => $disabled || $loading]) }}>
    <div class="flex items-center justify-center gap-2">
        @if($loading)
            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif

        {{ $slot }}
    </div>
</button>
