<div class="flex items-center space-x-1 text-xs" x-data="{ showTooltip: null }">
    {{-- OCR Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->ocrStatusColor }}"
            @mouseenter="showTooltip = 'ocr'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">OCR</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'ocr'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->ocrTooltip }}
        </div>
    </div>

    {{-- Arrow --}}
    <svg class="w-4 h-4 {{ in_array($job->status, ['completed', 'succeeded']) ? 'text-gray-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>

    {{-- Embedding Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->embeddingStatusColor }}"
            @mouseenter="showTooltip = 'embed'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">Embed</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'embed'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->embeddingTooltip }}
        </div>
    </div>

    {{-- Arrow --}}
    <svg class="w-4 h-4 {{ $job->embedding_status === 'synced' ? 'text-gray-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>

    {{-- Graph Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->graphStatusColor }}"
            @mouseenter="showTooltip = 'graph'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">Graph</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'graph'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->graphTooltip }}
        </div>
    </div>
</div>
