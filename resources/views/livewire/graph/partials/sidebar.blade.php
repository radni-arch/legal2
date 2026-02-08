{{-- resources/views/livewire/graph/partials/sidebar.blade.php --}}
<div
    x-data="{
        open: false,
        activeTab: @js($activePanel ?? 'arguments'),
        tabs: [
            { id: 'arguments', label: 'Arguments', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
            { id: 'evidence', label: 'Evidence', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { id: 'timeline', label: 'Timeline', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        ]
    }"
    class="absolute right-0 top-0 h-full z-20 flex"
>
    {{-- Toggle button --}}
    <button
        @click="open = !open"
        class="self-center -ml-10 bg-gray-800/90 hover:bg-gray-700 rounded-l-lg p-2 shadow-lg border border-r-0 border-gray-600 backdrop-blur-sm transition-colors"
        :title="open ? 'Hide analysis panel' : 'Show analysis panel'"
    >
        <svg
            class="w-5 h-5 text-gray-300 transition-transform duration-200"
            :class="{ 'rotate-180': !open }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </button>

    {{-- Sidebar panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="w-80 bg-gray-900/95 backdrop-blur-sm border-l border-gray-700 shadow-2xl h-full overflow-hidden flex flex-col"
    >
        {{-- Tabs --}}
        <div class="flex border-b border-gray-700 bg-gray-900">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    @click="activeTab = tab.id"
                    :class="activeTab === tab.id
                        ? 'text-blue-400 border-b-2 border-blue-400 bg-gray-800/50'
                        : 'text-gray-500 hover:text-gray-300 border-b-2 border-transparent'"
                    class="flex-1 px-3 py-3 text-xs font-medium transition-all flex flex-col items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
                    </svg>
                    <span x-text="tab.label"></span>
                </button>
            </template>
        </div>

        {{-- Tab content --}}
        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <div x-show="activeTab === 'arguments'" x-transition.opacity.duration.150ms>
                @include('livewire.graph.partials.arguments-panel')
            </div>
            <div x-show="activeTab === 'evidence'" x-transition.opacity.duration.150ms>
                @include('livewire.graph.partials.evidence-panel')
            </div>
            <div x-show="activeTab === 'timeline'" x-transition.opacity.duration.150ms>
                @include('livewire.graph.partials.timeline-panel')
            </div>
        </div>
    </div>
</div>
