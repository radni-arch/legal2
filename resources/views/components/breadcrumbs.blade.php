@if(count($items) > 0)
{{-- JSON-LD Structured Data for SEO --}}
@php
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->map(function ($crumb, $index) {
        $item = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb->label,
        ];
        if ($crumb->url) {
            $item['item'] = url($crumb->url);
        }
        return $item;
    })->values()->all(),
];
@endphp
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>

<nav class="text-sm mb-2" style="color: var(--muted, #94a3b8);" aria-label="Breadcrumb">
    <ol class="flex items-center flex-wrap gap-1">
        @foreach($items as $index => $crumb)
            <li class="flex items-center">
                @if($index > 0)
                    <span class="mx-2 select-none" aria-hidden="true">{{ $separator }}</span>
                @endif

                @if($crumb->isActive)
                    <span class="font-medium" style="color: var(--fg, #e5e7eb);" aria-current="page">
                        {{ $crumb->label }}
                    </span>
                @elseif($crumb->url)
                    <a href="{{ $crumb->url }}"
                       class="hover:underline transition-colors duration-150"
                       style="color: var(--muted, #94a3b8);">
                        {{ $crumb->label }}
                    </a>
                @else
                    <span>{{ $crumb->label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
