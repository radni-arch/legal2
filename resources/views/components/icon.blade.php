@props(['name'])

@php
$icons = [
    'save' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 16v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-7"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 21v-6a2 2 0 012-2h1a2 2 0 012 2v6"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l4 4v10"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6"></path>',
    'delete' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>',
    'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>',
    'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>',
    'x' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>',
    'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>',
    'spinner' => '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>',
];

$iconPath = $icons[$name] ?? $icons['save'];
@endphp

<svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    {!! $iconPath !!}
</svg>
