@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl', // sm, md, lg, xl, 2xl
    'closable' => true
])

<div x-data="{ show: false, name: '{{ $name }}' }"
     x-show="show"
     x-on:open-modal.window="$event.detail === name ? show = true : null"
     x-on:close-modal.window="$event.detail === name ? show = false : null"
     x-on:keydown.escape.window="show = false"
     style="display: none"
     class="fixed inset-0 z-50 overflow-y-auto">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-50" x-on:click="show = false"></div>

    {{-- Modal --}}
    <div class="flex items-center justify-center min-h-screen p-4">
        <div {{ $attributes->merge(['class' => "bg-white rounded-lg shadow-xl max-w-{$maxWidth} w-full relative"]) }}>
            @if($title)
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                @if($closable)
                <button x-on:click="show = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                @endif
            </div>
            @endif

            <div class="px-6 py-4">
                {{ $slot }}
            </div>

            @if(isset($footer))
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>
