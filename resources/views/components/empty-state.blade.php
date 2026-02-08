@props([
    'title' => null,
    'message' => null,
    'description' => null
])

<div class="text-center py-12">
    @if($title)
        <h3 class="mt-2 text-lg font-medium text-gray-900">{{ $title }}</h3>
    @endif

    @if($message)
        <p class="mt-1 text-sm text-gray-500">{{ $message }}</p>
    @endif

    @if($description)
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    @endif

    @if($slot->isNotEmpty())
        <div class="mt-6">
            {{ $slot }}
        </div>
    @endif
</div>
