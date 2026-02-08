<a href="{{ $href }}"
   {{ $attributes->merge([
       'class' => 'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors duration-150',
       'style' => 'background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: 0.5rem;'
   ]) }}>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
    {{ $label }}
</a>
