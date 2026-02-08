{{-- resources/views/livewire/graph/force-graph.blade.php --}}
<div
    x-data="ForceGraph()"
    x-init="loadData(@js($graphData))"
    @graph-data-loaded.window="loadData($event.detail.graphData)"
    @connected-nodes-loaded.window="addConnectedNodes($event.detail.nodes, $event.detail.edges)"
    @server-search-results.window="handleServerSearchResults($event.detail.results)"
    class="relative"
>
    {{-- Graph container with border and empty state --}}
    <div class="relative rounded-xl border border-gray-700/60 bg-gray-900 shadow-lg overflow-hidden">

        {{-- Toolbar --}}
        <div class="absolute top-4 left-4 z-10 flex gap-2 flex-wrap">
            {{-- Zoom controls --}}
            <div class="bg-gray-800/90 backdrop-blur-sm rounded-lg p-1 flex gap-1 border border-gray-700/50 shadow-md">
                <button @click="zoomIn()" class="p-2 hover:bg-gray-700 rounded transition-colors" title="Zoom In">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
                    </svg>
                </button>
                <button @click="zoomOut()" class="p-2 hover:bg-gray-700 rounded transition-colors" title="Zoom Out">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                    </svg>
                </button>
                <button @click="fitToView()" class="p-2 hover:bg-gray-700 rounded transition-colors" title="Fit to View">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                </button>
            </div>

            {{-- Pending updates badge --}}
            <div x-show="pendingUpdates > 0" class="bg-gray-800/90 backdrop-blur-sm rounded-lg p-1 flex items-center gap-2 border border-gray-700/50 shadow-md px-3">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                </span>
                <span class="text-sm text-gray-300">
                    <span x-text="pendingUpdates"></span> update<span x-show="pendingUpdates > 1">s</span>
                </span>
                <button
                    @click="refreshGraph()"
                    class="text-blue-400 hover:text-blue-300 text-sm font-medium"
                >
                    Refresh
                </button>
            </div>

            {{-- Search --}}
            <div class="bg-gray-800/90 backdrop-blur-sm rounded-lg p-1 flex items-center border border-gray-700/50 shadow-md">
                <svg class="w-4 h-4 text-gray-500 ml-2 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    x-model.debounce.300ms="searchQuery"
                    @input="searchNodes(); if(searchQuery.trim().length >= 2) $wire.searchGraph(searchQuery)"
                    placeholder="Search graph..."
                    class="bg-transparent border-none text-sm text-gray-300 placeholder-gray-500 focus:ring-0 w-48"
                >
                <button
                    x-show="searchQuery"
                    @click="searchQuery = ''; clearSearch()"
                    class="p-1 hover:bg-gray-700 rounded"
                >
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Loading indicator --}}
        <div x-show="isLoading" class="absolute top-4 right-4 z-10">
            <div class="bg-gray-800/90 backdrop-blur-sm rounded-lg px-3 py-2 flex items-center gap-2 border border-gray-700/50 shadow-md">
                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm text-gray-300">Loading...</span>
            </div>
        </div>

        {{-- Performance mode indicator --}}
        <div x-show="performanceMode" class="absolute top-4 left-1/2 -translate-x-1/2 z-10">
            <div class="bg-yellow-900/80 text-yellow-200 text-xs px-3 py-1 rounded-full border border-yellow-700/50">
                Performance mode (labels hidden, <span x-text="nodes.length"></span> nodes)
            </div>
        </div>

        {{-- Search results --}}
        <div x-show="searchResults.length > 0 || serverSearchResults.length > 0" class="absolute top-16 left-4 z-20 bg-gray-800/95 backdrop-blur-sm rounded-lg shadow-xl max-h-80 overflow-y-auto w-72 border border-gray-700/50">
            {{-- Local results (already loaded nodes) --}}
            <template x-if="searchResults.length > 0">
                <div>
                    <div class="px-3 py-1.5 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-700/50">
                        Loaded Nodes
                    </div>
                    <template x-for="result in searchResults" :key="'local-' + result.id">
                        <button
                            @click="focusOnNode(result)"
                            class="w-full px-3 py-2 text-left text-sm hover:bg-gray-700 flex items-center gap-2 transition-colors"
                        >
                            <span
                                class="w-3 h-3 rounded-full flex-shrink-0"
                                :style="`background-color: ${nodeColors[result.type] || '#6B7280'}`"
                            ></span>
                            <span class="text-gray-300 truncate" x-text="result.label || result.name || result.id"></span>
                            <span class="text-xs text-gray-500" x-text="result.type"></span>
                        </button>
                    </template>
                </div>
            </template>


            {{-- Server results (from Neo4j search) --}}
            <template x-if="serverSearchResults.length > 0">
                <div>
                    <div class="px-3 py-1.5 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-700/50"
                         :class="searchResults.length > 0 ? 'border-t' : ''">
                        Database Results
                    </div>
                    <template x-for="result in serverSearchResults" :key="'server-' + result.id">
                        <button
                            @click="$wire.loadNodeGraph(result.id); searchQuery = ''; searchResults = []; serverSearchResults = []"
                            class="w-full px-3 py-2 text-left text-sm hover:bg-gray-700 flex items-center gap-2 transition-colors"
                        >
                            <span
                                class="w-3 h-3 rounded-full flex-shrink-0"
                                :style="`background-color: ${nodeColors[result.type] || '#6B7280'}`"
                            ></span>
                            <span class="text-gray-300 truncate flex-1" x-text="result.label || result.id"></span>
                            <span class="text-xs text-gray-500 flex-shrink-0" x-text="result.type"></span>
                            <svg class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Empty state (shown when no graph data is loaded) --}}
        <div x-show="nodes.length === 0 && !isLoading" class="absolute inset-0 flex items-center justify-center z-[5]">
            <div class="text-center max-w-sm">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-400 mb-2">No graph data loaded</h3>
                <p class="text-sm text-gray-500">Use the search bar above to find cases, laws, or legal topics and start exploring connections.</p>
            </div>
        </div>

        {{-- Graph SVG container --}}
        <div x-ref="container" class="w-full h-[600px]"></div>

        {{-- Legend with filters --}}
        <div class="absolute bottom-4 left-4 z-10 bg-gray-800/90 backdrop-blur-sm rounded-lg p-3 max-w-xs border border-gray-700/50 shadow-md">
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-400 font-medium">Node Types</span>
                <div class="flex gap-1">
                    <button
                        @click="filters.activeTypes = [...filters.nodeTypes]; updateGraph()"
                        class="text-xs text-blue-400 hover:text-blue-300"
                    >All</button>
                    <span class="text-gray-600">|</span>
                    <button
                        @click="filters.activeTypes = []; updateGraph()"
                        class="text-xs text-blue-400 hover:text-blue-300"
                    >None</button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs max-h-48 overflow-y-auto">
                <template x-for="type in filters.nodeTypes" :key="type">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="filters.activeTypes.includes(type)"
                            @change="toggleNodeType(type)"
                            class="rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500"
                        >
                        <span
                            class="w-3 h-3 rounded-full"
                            :style="`background-color: ${nodeColors[type] || '#6B7280'}`"
                        ></span>
                        <span class="text-gray-300" x-text="type"></span>
                    </label>
                </template>
            </div>

            {{-- Relationship Type Filters --}}
            <div class="mt-3 pt-3 border-t border-gray-700">
                <h4 class="text-xs font-medium text-gray-400 mb-2">Relationship Types</h4>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="relType in filters.relationshipTypes" :key="relType">
                        <button
                            @click="toggleRelationship(relType)"
                            :class="filters.activeRelationships.includes(relType)
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-700 text-gray-400'"
                            class="px-2.5 py-1 text-xs rounded-full transition-colors"
                            x-text="relType.replace('_', ' ')"
                        ></button>
                    </template>
                </div>
                <div class="flex gap-2 mt-2">
                    <button @click="selectAllRelationships()" class="text-xs text-blue-400 hover:underline">
                        Select All
                    </button>
                    <button @click="clearAllRelationships()" class="text-xs text-gray-500 hover:underline">
                        Clear All
                    </button>
                </div>
            </div>

            {{-- Relationship Legend --}}
            <div class="mt-3 pt-3 border-t border-gray-700">
                <h4 class="text-xs font-medium text-gray-400 mb-2">Edge Colors</h4>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-0.5 bg-blue-400 rounded-full"></span>
                        <span class="text-gray-300">CITES</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-0.5 bg-red-400 rounded-full"></span>
                        <span class="text-gray-300">OPPOSES</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-0.5 bg-green-400 rounded-full"></span>
                        <span class="text-gray-300">SUPPORTS</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-0.5 bg-emerald-400 rounded-full"></span>
                        <span class="text-gray-300">REFERENCES</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Instructions --}}
        <div class="absolute bottom-4 right-4 z-10 bg-gray-800/90 backdrop-blur-sm rounded-lg p-3 text-xs text-gray-400 border border-gray-700/50 shadow-md space-y-1">
            <div><kbd class="bg-gray-700 px-1.5 py-0.5 rounded text-gray-300 font-mono text-[10px]">Click</kbd> Select node</div>
            <div><kbd class="bg-gray-700 px-1.5 py-0.5 rounded text-gray-300 font-mono text-[10px]">Double-click</kbd> Expand connections</div>
            <div><kbd class="bg-gray-700 px-1.5 py-0.5 rounded text-gray-300 font-mono text-[10px]">Drag</kbd> Move node</div>
            <div><kbd class="bg-gray-700 px-1.5 py-0.5 rounded text-gray-300 font-mono text-[10px]">Scroll</kbd> Zoom</div>
        </div>

        {{-- Node Metadata Panel --}}
        @include('livewire.graph.partials.node-metadata-panel')

        {{-- Sidebar with analysis panels --}}
        @include('livewire.graph.partials.sidebar')
    </div>

    {{-- Pinned Nodes Workspace --}}
    @include('livewire.graph.partials.pinned-workspace')

    {{-- Session Manager --}}
    @include('livewire.graph.partials.session-manager')

    {{-- Alert Panel (Phase 3: Contradiction Radar) --}}
    @auth
        <livewire:graph.alert-panel-controller />
    @endauth
</div>
