{{-- resources/views/livewire/graph/partials/node-metadata-panel.blade.php --}}
<div
    x-show="metadataPanel.isOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-x-4"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-4"
    class="absolute right-0 top-0 w-80 h-full bg-gray-900/95 backdrop-blur-sm border-l border-gray-700 overflow-y-auto z-30"
>
    {{-- Header --}}
    <div class="sticky top-0 bg-gray-900 border-b border-gray-700 p-4 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-white" x-text="metadataPanel.node?.type || 'Node Details'"></h3>
        <button @click="closeMetadataPanel()" class="text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Loading state --}}
    <div x-show="metadataPanel.loading" class="p-4 flex justify-center">
        <svg class="animate-spin h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    {{-- Node properties --}}
    <div x-show="!metadataPanel.loading && metadataPanel.node" class="p-4 space-y-4">
        {{-- ID --}}
        <div>
            <label class="text-xs text-gray-500 uppercase">ID</label>
            <p class="text-sm text-gray-300 font-mono" x-text="metadataPanel.node?.id"></p>
        </div>

        {{-- Type --}}
        <div>
            <label class="text-xs text-gray-500 uppercase">Type</label>
            <p class="text-sm text-gray-300" x-text="metadataPanel.node?.type"></p>
        </div>

        {{-- Properties (dynamic) --}}
        <template x-if="metadataPanel.node?.properties">
            <div class="space-y-3">
                <template x-for="(value, key) in metadataPanel.node.properties" :key="key">
                    <div>
                        <label class="text-xs text-gray-500 uppercase" x-text="key.replace(/_/g, ' ')"></label>
                        <p class="text-sm text-gray-300 break-words" x-text="value"></p>
                    </div>
                </template>
            </div>
        </template>

        {{-- Connection counts --}}
        <div class="pt-4 border-t border-gray-700">
            <label class="text-xs text-gray-500 uppercase mb-2 block">Connections</label>
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-gray-800 rounded p-2">
                    <p class="text-xs text-gray-500">Outgoing</p>
                    <p class="text-lg font-semibold text-blue-400"
                       x-text="edges.filter(e => (e.source.id || e.source) === metadataPanel.node?.id).length">
                    </p>
                </div>
                <div class="bg-gray-800 rounded p-2">
                    <p class="text-xs text-gray-500">Incoming</p>
                    <p class="text-lg font-semibold text-green-400"
                       x-text="edges.filter(e => (e.target.id || e.target) === metadataPanel.node?.id).length">
                    </p>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-700 flex gap-2">
            <button
                @click="isPinned(metadataPanel.node?.id) ? unpinNode(metadataPanel.node?.id) : pinNode(metadataPanel.node)"
                :class="isPinned(metadataPanel.node?.id) ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700'"
                class="flex-1 px-3 py-2 text-white text-sm rounded"
            >
                <span x-text="isPinned(metadataPanel.node?.id) ? 'Unpin' : 'Pin to Workspace'"></span>
            </button>
            <button
                @click="expandNode(metadataPanel.node)"
                class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded"
            >
                Expand Connections
            </button>
        </div>
    </div>
</div>
