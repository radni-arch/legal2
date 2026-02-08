{{-- Precedential Value Badge Component --}}
@php
    $tooltipTexts = [
        'binding' => 'This precedent must be followed by lower courts within the same jurisdiction.',
        'persuasive' => 'This precedent may be considered but is not legally binding.',
        'informational' => 'This decision provides context but does not establish legal precedent.',
    ];

    $labels = [
        'binding' => 'Binding precedent',
        'persuasive' => 'Persuasive precedent',
        'informational' => 'Informational precedent',
    ];

    $displayValue = ucfirst($value ?? 'unknown');
    $tooltipText = $tooltipTexts[$value] ?? '';
    $ariaLabel = $labels[$value] ?? ucfirst($value ?? 'unknown');
@endphp

<span
    class="badge badge-{{ $value }} relative inline-flex items-center"
    x-data="{ showTooltip: false }"
    @mouseenter="showTooltip = true"
    @mouseleave="showTooltip = false"
    @focus="showTooltip = true"
    @blur="showTooltip = false"
    role="status"
    aria-label="{{ $ariaLabel }}"
    tabindex="0"
    dusk="precedential-badge-{{ $value }}"
>
    <span class="badge-text">{{ $displayValue }}</span>

    <span
        x-show="showTooltip"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-sm rounded-lg shadow-lg whitespace-normal max-w-xs z-50"
        role="tooltip"
        dusk="tooltip-{{ $value }}"
        style="display: none;"
    >
        {{ $tooltipText }}
        <span class="absolute top-full left-1/2 transform -translate-x-1/2 -mt-1">
            <svg class="w-3 h-3 text-gray-900" viewBox="0 0 12 12">
                <path fill="currentColor" d="M6 12L0 0h12z"/>
            </svg>
        </span>
    </span>
</span>
