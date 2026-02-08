@props([
    'label' => null,
    'error' => null
])

<div class="mb-4">
    <div class="flex items-center">
        <input
            {{ $attributes->merge([
                'type' => 'checkbox',
                'class' => 'h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500'
            ]) }}
        />
        @if($label)
            <label @if($attributes->has('id')) for="{{ $attributes->get('id') }}" @elseif($attributes->has('name')) for="{{ $attributes->get('name') }}" @endif class="ml-2 block text-sm text-gray-700">
                {{ $label }}
            </label>
        @endif
    </div>

    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
