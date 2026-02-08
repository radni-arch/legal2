@props(['text', 'position' => 'top'])

<div class="relative inline-block" x-data="{ show: false }">
    <div x-on:mouseenter="show = true" x-on:mouseleave="show = false">
        {{ $slot }}
    </div>
    <div x-show="show"
         x-transition
         class="absolute z-10 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg {{ $position === 'top' ? 'bottom-full mb-2' : 'top-full mt-2' }}"
         style="display: none;">
        {{ $text }}
    </div>
</div>
