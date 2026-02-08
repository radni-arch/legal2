@props([
    'label' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'rows' => '4'
])

<div class="mb-4">
    @if($label)
        <label @if($attributes->has('id')) for="{{ $attributes->get('id') }}" @elseif($attributes->has('name')) for="{{ $attributes->get('name') }}" @endif class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <textarea
        {{ $attributes->merge([
            'rows' => $rows,
            'class' => 'w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ' .
                ($error ? 'border-red-500' : 'border-gray-300')
        ]) }}
    >{{ $slot }}</textarea>

    @if($hint)
        <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
