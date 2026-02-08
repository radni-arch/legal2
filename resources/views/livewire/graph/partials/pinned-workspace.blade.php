{{-- resources/views/livewire/graph/partials/pinned-workspace.blade.php --}}
<div x-show="pinnedNodes.length > 0" class="mb-4">
    <div class="bg-gray-800 rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
            <h4 class="text-sm font-medium text-gray-300">
                Pinned Nodes (<span x-text="pinnedNodes.length"></span>)
            </h4>
            <button
                @click="pinnedNodes = []"
                class="text-xs text-gray-500 hover:text-red-400"
            >
                Clear All
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <template x-for="node in pinnedNodes" :key="node.id">
                <div class="flex items-center gap-1 px-2 py-1 bg-gray-700 rounded-full text-sm">
                    <span
                        class="w-2 h-2 rounded-full"
                        :style="'background-color: ' + (nodeColors[node.type] || '#6B7280')"
                    ></span>
                    <span
                        class="text-gray-300 cursor-pointer hover:text-white max-w-32 truncate"
                        @click="focusOnNode(node.id)"
                        x-text="node.label"
                    ></span>
                    <button
                        @click="unpinNode(node.id)"
                        class="text-gray-500 hover:text-red-400"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
