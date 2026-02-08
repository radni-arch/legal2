<div class="min-h-screen" style="background: #0b1220; color: var(--fg, #e5e7eb);">
    {{-- Unified Header --}}
    <x-page-header
        title="Neo4j Graph Viewer"
        subtitle="Explore legal knowledge graph connections and relationships"
        route-name="graph.viewer"
    >
        <x-slot:actions>
            <button
                dusk="citation-analysis-button"
                wire:click="openCitationAnalysis"
                wire:loading.attr="disabled"
                wire:target="openCitationAnalysis"
                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-weight: 500; font-size: 0.875rem; transition: all 0.2s;"
                title="Citation Analysis"
            >
                <span wire:loading.remove wire:target="openCitationAnalysis">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    Citation Analysis
                </span>
                <span wire:loading wire:target="openCitationAnalysis">
                    <svg class="animate-spin h-5 w-5 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Opening...
                </span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Error States -->
        @if($hasConnectionError)
        <div dusk="connection-error" class="bg-red-900/50 border border-red-500 rounded-lg p-4 mb-4 text-center">
            <p class="text-red-200">{{ $error ?? 'Graph database unavailable' }}</p>
            <button wire:click="$refresh" class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 rounded text-white text-sm">
                Retry Connection
            </button>
        </div>
        @endif

        @if($searchTerm && empty($graphData) && !$hasConnectionError && $error && str_contains($error, 'No nodes found'))
        <div dusk="empty-results" class="text-center py-8 text-gray-400">
            <p>No results found for "{{ $searchTerm }}"</p>
        </div>
        @endif

        <!-- Statistics Cards -->
        @if($statistics)
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Total Nodes</div>
                <div class="stat-value">{{ number_format($statistics['totalNodes']) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Relationships</div>
                <div class="stat-value">{{ number_format($statistics['totalRelationships']) }}</div>
            </div>
            @foreach($statistics['nodes'] as $label => $count)
                @if($loop->index < 4)
                <div class="stat-card">
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ number_format($count) }}</div>
                </div>
                @endif
            @endforeach
        </div>
        @endif


        <!-- Graph Analytics Metrics -->
        @if($metricsLoaded && $showMetrics)
        <div dusk="metrics-panel" class="mb-6 space-y-6">
            <!-- Influential Decisions Panel -->
            @if(!empty($influentialDecisions))
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title">🏆 Top Influential Decisions</h2>
                            <p class="text-sm mt-1" style="color: var(--muted);">Based on PageRank algorithm analyzing citation patterns</p>
                        </div>
                        <button
                            dusk="toggle-metrics-button"
                            wire:click="toggleMetrics"
                            wire:loading.attr="disabled"
                            wire:target="toggleMetrics"
                            class="btn-secondary"
                            title="Hide Metrics"
                        >
                            <span wire:loading.remove wire:target="toggleMetrics">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                            </span>
                            <span wire:loading wire:target="toggleMetrics">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table dusk="influential-decisions-list" class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Rank</th>
                                    <th>Case Number</th>
                                    <th>Court</th>
                                    <th>Date</th>
                                    <th style="width: 120px;">Score</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($influentialDecisions as $index => $decision)
                                <tr>
                                    <td class="text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full"
                                              style="background: @if($index < 3) rgba(234, 179, 8, 0.2) @else rgba(56, 189, 248, 0.1) @endif; color: @if($index < 3) #fbbf24 @else var(--accent) @endif; font-weight: 600;">
                                            {{ $index + 1 }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-medium" style="color: var(--fg);">{{ $decision['case_number'] ?? 'N/A' }}</span>
                                        @if(!empty($decision['ecli']))
                                        <div class="text-xs" style="color: var(--muted);">{{ $decision['ecli'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-sm" style="color: var(--muted);">{{ $decision['court'] ?? 'Unknown' }}</td>
                                    <td class="text-sm" style="color: var(--muted);">
                                        {{ isset($decision['date']) ? date('Y-m-d', strtotime($decision['date'])) : 'N/A' }}
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 bg-slate-700 rounded-full h-2 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500"
                                                     style="width: {{ min(100, ($decision['rank'] ?? 0) * 100) }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium" style="color: var(--accent);">{{ number_format(($decision['rank'] ?? 0), 3) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <button
                                            wire:click="loadDecisionFromMetrics('{{ $decision['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="loadDecisionFromMetrics('{{ $decision['id'] }}')"
                                            class="btn-secondary text-xs px-3 py-1"
                                            title="View in Graph"
                                        >
                                            <span wire:loading.remove wire:target="loadDecisionFromMetrics('{{ $decision['id'] }}')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                View
                                            </span>
                                            <span wire:loading wire:target="loadDecisionFromMetrics('{{ $decision['id'] }}')">...</span>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Citation Clusters Panel -->
            @if(!empty($citationClusters))
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">📊 Emerging Citation Clusters</h2>
                        <p class="text-sm mt-1" style="color: var(--muted);">Detected communities using Louvain modularity optimization</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($citationClusters as $cluster)
                        <div class="cluster-card">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-lg font-bold" style="color: var(--accent);">Cluster #{{ $cluster['community_id'] ?? '?' }}</h3>
                                    <p class="text-sm" style="color: var(--muted);">{{ count($cluster['members'] ?? []) }} decisions</p>
                                </div>
                                <span class="cluster-badge">
                                    {{ $cluster['modularity'] ?? 'N/A' }}
                                </span>
                            </div>

                            @if(!empty($cluster['members']))
                            <div class="space-y-2 mb-4">
                                @foreach(array_slice($cluster['members'], 0, 3) as $member)
                                <div class="text-xs p-2 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                                    <div class="font-medium" style="color: var(--fg);">{{ $member['case_number'] ?? 'N/A' }}</div>
                                    <div style="color: var(--muted);">{{ $member['court'] ?? 'Unknown Court' }}</div>
                                </div>
                                @endforeach
                                @if(count($cluster['members']) > 3)
                                <div class="text-xs text-center" style="color: var(--muted);">
                                    +{{ count($cluster['members']) - 3 }} more
                                </div>
                                @endif
                            </div>
                            @endif

                            <button
                                dusk="view-cluster-{{ $cluster['community_id'] }}"
                                wire:click="viewCluster({{ $cluster['community_id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="viewCluster({{ $cluster['community_id'] }})"
                                class="btn-primary w-full text-sm"
                            >
                                <span wire:loading.remove wire:target="viewCluster({{ $cluster['community_id'] }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                    Explore Cluster
                                </span>
                                <span wire:loading wire:target="viewCluster({{ $cluster['community_id'] }})">
                                    <svg class="animate-spin h-4 w-4 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading...
                                </span>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
        @elseif($metricsLoaded && !$showMetrics)
        <div class="mb-6">
            <button
                dusk="toggle-metrics-button"
                wire:click="toggleMetrics"
                wire:loading.attr="disabled"
                wire:target="toggleMetrics"
                class="btn-secondary"
            >
                <span wire:loading.remove wire:target="toggleMetrics">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    Show Graph Analytics
                </span>
                <span wire:loading wire:target="toggleMetrics">
                    <svg class="animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading...
                </span>
            </button>
        </div>
        @endif

        <!-- Main Content Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Sidebar - Controls & Search -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Search Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Search & Select</h2>
                    </div>
                    <div class="card-body">
                        <!-- Node Type -->
                        <div class="form-group">
                            <label for="node-type-select" class="form-label">Node Type</label>
                            <select
                                id="node-type-select"
                                dusk="node-type-filter"
                                wire:model="selectedNodeType"
                                class="form-select"
                                aria-label="Select node type to search"
                            >
                                @foreach($nodeTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="form-group">
                            <label for="searchTerm" class="form-label">Search</label>
                            <input
                                type="text"
                                id="searchTerm"
                                name="searchTerm"
                                dusk="search-input"
                                wire:model.debounce.500ms="searchTerm"
                                wire:keydown.enter="searchNodes"
                                placeholder="Enter search term..."
                                class="form-input"
                                aria-label="Search for nodes in the graph"
                                autocomplete="off"
                            >
                        </div>

                        <button
                            wire:click="searchNodes"
                            wire:loading.attr="disabled"
                            wire:target="searchNodes"
                            class="btn-primary w-full"
                        >
                            <span wire:loading.remove wire:target="searchNodes">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Search
                            </span>
                            <span wire:loading wire:target="searchNodes">
                                <svg class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Searching...
                            </span>
                        </button>

                        @if($selectedNode)
                        <button
                            wire:click="resetGraph"
                            wire:loading.attr="disabled"
                            wire:target="resetGraph"
                            class="btn-secondary w-full mt-2"
                        >
                            <span wire:loading.remove wire:target="resetGraph">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                Reset
                            </span>
                            <span wire:loading wire:target="resetGraph">
                                <svg class="animate-spin h-4 w-4 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Resetting...
                            </span>
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Graph Settings -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Graph Settings</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="relationship-type-select" class="form-label">Relationship Type</label>
                            <select
                                id="relationship-type-select"
                                wire:model="relationshipType"
                                wire:change="$refresh"
                                class="form-select"
                                aria-label="Filter by relationship type"
                            >
                                @foreach($relationshipTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="depth-input" class="form-label">Depth (1-3)</label>
                            <input
                                id="depth-input"
                                type="number"
                                wire:model="depth"
                                min="1"
                                max="3"
                                class="form-input"
                                aria-label="Set graph traversal depth"
                                aria-describedby="depth-help"
                            >
                            <span id="depth-help" class="sr-only">Controls how many levels of connections to display</span>
                        </div>

                        <div class="form-group">
                            <label for="limit-input" class="form-label">Max Nodes</label>
                            <input
                                id="limit-input"
                                type="number"
                                wire:model="limit"
                                min="10"
                                max="200"
                                step="10"
                                class="form-input"
                                aria-label="Set maximum number of nodes to display"
                                aria-describedby="limit-help"
                            >
                            <span id="limit-help" class="sr-only">Maximum number of nodes to display in the graph</span>
                        </div>

                        @if($selectedNodeId)
                        <button
                            dusk="load-graph-button"
                            wire:click="loadNodeGraph"
                            wire:loading.attr="disabled"
                            wire:target="loadNodeGraph"
                            class="btn-primary w-full"
                        >
                            <span wire:loading.remove wire:target="loadNodeGraph">Reload Graph</span>
                            <span wire:loading wire:target="loadNodeGraph">
                                <svg class="animate-spin h-4 w-4 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Recent Nodes -->
                @if(count($recentNodes) > 0)
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Nodes</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="divide-y divide-slate-200">
                            @foreach($recentNodes as $node)
                            <button
                                wire:click="selectRecentNode('{{ $node['label'] }}', '{{ $node['id'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="selectRecentNode('{{ $node['label'] }}', '{{ $node['id'] }}')"
                                class="w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors relative"
                            >
                                <div wire:loading.remove wire:target="selectRecentNode('{{ $node['label'] }}', '{{ $node['id'] }}')">
                                    <div class="text-sm font-medium text-slate-900">{{ $node['display'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $node['label'] }}</div>
                                </div>
                                <div wire:loading wire:target="selectRecentNode('{{ $node['label'] }}', '{{ $node['id'] }}')" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm">Loading...</span>
                                </div>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Right Content - Graph & Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Error Message -->
                @if($error)
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $error }}</span>
                </div>
                @endif

                <!-- Graph Visualization -->
                <div class="card">
                    <div class="card-header">
                        <div class="flex items-center justify-between">
                            <h2 class="card-title">Graph Visualization</h2>
                            @if($graphData)
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <span class="badge-info">{{ $graphData['nodeCount'] }} nodes</span>
                                <span class="badge-success">{{ $graphData['edgeCount'] }} edges</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0 relative">
                        <div
                            x-data="ForceGraph()"
                            x-init="
                                await init();
                                @if($graphData)
                                    loadData(@js($graphData));
                                @endif
                            "
                            @graph-data-updated.window="loadData($event.detail?.graphData ?? $event.detail)"
                            @node-selected.window="
                                const nodeData = $event.detail?.node;
                                if (nodeData) {
                                    $wire.call('selectNodeFromGraph', nodeData.id, nodeData.type);
                                }
                            "
                            wire:ignore
                            dusk="graph-canvas"
                            class="w-full relative"
                            style="min-height: 600px;"
                        >
                            <div x-ref="container" class="w-full" style="height: 600px; background: #0b1220;"></div>

                            <!-- Inline toolbar (zoom + layout + export) -->
                            <div class="absolute top-4 left-4 z-10 flex gap-2">
                                <div class="bg-gray-800/90 rounded-lg p-1 flex gap-1">
                                    <button @click="zoomIn()" class="p-2 hover:bg-gray-700 rounded" title="Zoom In">
                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                    <button @click="zoomOut()" class="p-2 hover:bg-gray-700 rounded" title="Zoom Out">
                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                                        </svg>
                                    </button>
                                    <button @click="fitToView()" class="p-2 hover:bg-gray-700 rounded" title="Fit to View">
                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                                <!-- Layout selector -->
                                <div class="bg-gray-800/90 rounded-lg p-1 flex gap-1">
                                    <button @click="setLayout('force')" :class="layout === 'force' ? 'bg-blue-600' : 'hover:bg-gray-700'" class="px-2 py-1 text-xs text-gray-300 rounded" title="Force Layout">Force</button>
                                    <button @click="setLayout('radial')" :class="layout === 'radial' ? 'bg-blue-600' : 'hover:bg-gray-700'" class="px-2 py-1 text-xs text-gray-300 rounded" title="Radial Layout">Radial</button>
                                    <button @click="setLayout('hierarchical')" :class="layout === 'hierarchical' ? 'bg-blue-600' : 'hover:bg-gray-700'" class="px-2 py-1 text-xs text-gray-300 rounded" title="Hierarchical Layout">Tree</button>
                                </div>
                                <!-- Export buttons -->
                                <div class="bg-gray-800/90 rounded-lg p-1 flex gap-1">
                                    <button @click="exportPNG()" class="p-2 hover:bg-gray-700 rounded" title="Export PNG">
                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <button @click="exportSVG()" class="p-2 hover:bg-gray-700 rounded" title="Export SVG">
                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Search within graph -->
                            <div class="absolute top-4 right-4 z-10">
                                <div class="bg-gray-800/90 rounded-lg p-1 flex items-center">
                                    <input type="text" x-model.debounce.300ms="searchQuery" @input="searchNodes()"
                                           placeholder="Search in graph..." class="bg-transparent border-none text-sm text-gray-300 placeholder-gray-500 focus:ring-0 w-48">
                                </div>
                                <div x-show="searchResults.length > 0"
                                     class="mt-1 bg-gray-800 rounded-lg shadow-lg max-h-48 overflow-y-auto w-full">
                                    <template x-for="result in searchResults" :key="result.id">
                                        <button @click="focusOnNode(result)"
                                                class="w-full px-3 py-2 text-left text-sm hover:bg-gray-700 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" :style="`background:${nodeColors[result.type]||'#6B7280'}`"></span>
                                            <span class="text-gray-300 truncate" x-text="result.label || result.id"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Loading indicator -->
                            <div x-show="isLoading" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20">
                                <div class="bg-gray-800/90 rounded-lg px-4 py-3 flex items-center gap-3">
                                    <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span class="text-sm text-gray-300">Expanding...</span>
                                </div>
                            </div>

                            <!-- Performance mode indicator -->
                            <div x-show="performanceMode" class="absolute top-4 left-1/2 -translate-x-1/2 z-10">
                                <div class="bg-yellow-900/80 text-yellow-200 text-xs px-3 py-1 rounded-full">
                                    Performance mode (<span x-text="nodes.length"></span> nodes)
                                </div>
                            </div>

                            <!-- Minimap -->
                            <canvas x-ref="minimap" width="160" height="100"
                                    class="absolute bottom-16 left-4 z-10 border border-gray-700 rounded bg-gray-900/80"
                                    style="width: 160px; height: 100px;">
                            </canvas>

                            <!-- Node type legend/filter -->
                            <div class="absolute bottom-4 left-4 z-10 bg-gray-800/90 rounded-lg p-3 max-w-xs max-h-48 overflow-y-auto">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs text-gray-400 font-medium">Node Types</span>
                                    <div class="flex gap-1">
                                        <button @click="filters.activeTypes = [...filters.nodeTypes]; updateGraph()"
                                                class="text-xs text-blue-400 hover:text-blue-300">All</button>
                                        <span class="text-gray-600">|</span>
                                        <button @click="filters.activeTypes = []; updateGraph()"
                                                class="text-xs text-blue-400 hover:text-blue-300">None</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                    <template x-for="type in filters.nodeTypes" :key="type">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="checkbox" :checked="filters.activeTypes.includes(type)"
                                                   @change="toggleNodeType(type)"
                                                   class="rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 w-3 h-3">
                                            <span class="w-2 h-2 rounded-full" :style="`background:${nodeColors[type]||'#6B7280'}`"></span>
                                            <span class="text-gray-300 truncate" x-text="type"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <!-- Instructions -->
                            <div class="absolute bottom-4 right-4 z-10 bg-gray-800/90 rounded-lg p-2 text-xs text-gray-500">
                                <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Click</kbd> Select</div>
                                <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Dbl-click</kbd> Expand</div>
                                <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Right-click</kbd> Menu</div>
                                <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Scroll</kbd> Zoom</div>
                            </div>

                            <!-- Metadata side panel -->
                            <div x-show="metadataPanel.isOpen"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-x-4"
                                 x-transition:enter-end="opacity-100 translate-x-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-x-0"
                                 x-transition:leave-end="opacity-0 translate-x-4"
                                 class="absolute right-0 top-0 w-72 h-full bg-gray-900/95 border-l border-gray-700 overflow-y-auto z-30">
                                <div class="sticky top-0 bg-gray-900 border-b border-gray-700 p-3 flex justify-between items-center">
                                    <h4 class="text-sm font-semibold text-white" x-text="metadataPanel.node?.type || 'Node'"></h4>
                                    <button @click="closeMetadataPanel()" class="text-gray-400 hover:text-white">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                <div x-show="metadataPanel.node" class="p-3 space-y-3 text-sm">
                                    <div>
                                        <label class="text-xs text-gray-500 uppercase">Label</label>
                                        <p class="text-gray-200" x-text="metadataPanel.node?.label || metadataPanel.node?.id"></p>
                                    </div>
                                    <template x-if="metadataPanel.node?.properties">
                                        <div class="space-y-2">
                                            <template x-for="(value, key) in metadataPanel.node.properties" :key="key">
                                                <div x-show="key !== 'id' && value">
                                                    <label class="text-xs text-gray-500 uppercase" x-text="key.replace(/_/g, ' ')"></label>
                                                    <p class="text-gray-300 text-xs break-words" x-text="typeof value === 'string' && value.length > 100 ? value.substring(0, 100) + '...' : value"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <div class="pt-3 border-t border-gray-700 flex gap-2">
                                        <button @click="expandNode(metadataPanel.node)"
                                                class="flex-1 px-2 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">
                                            Expand
                                        </button>
                                        <button @click="$wire.call('loadNodeGraph', metadataPanel.node?.type, metadataPanel.node?.id)"
                                                class="flex-1 px-2 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-xs rounded">
                                            Load as Center
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Context Menu -->
                            <div x-data="{ ctxMenu: { visible: false, x: 0, y: 0, node: null } }"
                                 @graph-context-menu.window="
                                     ctxMenu.visible = true;
                                     ctxMenu.x = $event.detail.x;
                                     ctxMenu.y = $event.detail.y;
                                     ctxMenu.node = $event.detail.node;
                                 "
                                 @click.window="ctxMenu.visible = false"
                                 @keydown.escape.window="ctxMenu.visible = false">
                                <div x-show="ctxMenu.visible"
                                     :style="`position:fixed; left:${ctxMenu.x}px; top:${ctxMenu.y}px; z-index:100;`"
                                     class="bg-gray-800 border border-gray-600 rounded-lg shadow-2xl py-1 min-w-48"
                                     x-transition>
                                    <button @click="expandNode(ctxMenu.node); ctxMenu.visible = false"
                                            class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                                        Expand Connections
                                    </button>
                                    <button @click="$wire.call('loadNodeGraph', ctxMenu.node?.type, ctxMenu.node?.id); ctxMenu.visible = false"
                                            class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                                        Load as Center
                                    </button>
                                    <template x-if="ctxMenu.node?.type === 'CourtDecisionDocument'">
                                        <button @click="$wire.call('openCitationAnalysis'); $wire.set('analysisDecisionId', ctxMenu.node?.id); ctxMenu.visible = false"
                                                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                                            Citation Analysis
                                        </button>
                                    </template>
                                    <template x-if="ctxMenu.node?.type === 'CourtDecisionDocument'">
                                        <button @click="$wire.call('getPrecedentChain', ctxMenu.node?.id); ctxMenu.visible = false"
                                                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                                            Precedent Chain
                                        </button>
                                    </template>
                                    <div class="border-t border-gray-700 my-1"></div>
                                    <button @click="navigator.clipboard.writeText(ctxMenu.node?.id); ctxMenu.visible = false"
                                            class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                                        Copy Node ID
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if(!$graphData)
                        <div class="absolute inset-0 flex items-center justify-center" style="background: #0b1220;">
                            <div class="text-center" style="color: #64748b;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <p class="text-lg">Select a node to visualize</p>
                                <p class="text-sm mt-2">Use the search or click a recent node</p>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <div class="flex items-center gap-4 text-xs">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" style="background: #8b5cf6;"></div>
                                <span style="color: var(--muted);">Selected Node</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" style="background: #38bdf8;"></div>
                                <span style="color: var(--muted);">Connected Nodes</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-0.5" style="background: #64748b;"></div>
                                <span style="color: var(--muted);">Relationships</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Node Details -->
                @if($selectedNode)
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Node Details</h2>
                    </div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 gap-4">
                            @foreach($selectedNode as $key => $value)
                            <div>
                                <dt class="text-sm font-medium text-slate-600">{{ $key }}</dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    @if(is_array($value))
                                        <pre class="bg-slate-100 p-2 rounded text-xs overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                </div>

                {{-- Judge Panel (Phase 5 Enhancement) --}}
                @if($selectedNodeType === 'CourtDecisionDocument' && !empty($selectedNode['judge']))
                <div class="card mt-4">
                    <div class="card-header">
                        <h2 class="card-title">Presiding Judge(s)</h2>
                    </div>
                    <div class="card-body">
                        @foreach(explode(',', $selectedNode['judge']) as $judge)
                        <div class="flex items-center gap-2 py-2 border-b border-gray-700 last:border-0">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="text-gray-200">{{ trim($judge) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Precedent Status Indicators (Phase 5 Enhancement) --}}
                @if($selectedNodeType === 'CourtDecisionDocument')
                <div class="flex flex-wrap gap-2 mt-4">
                    @if(isset($selectedNode['precedential_value']) && $selectedNode['precedential_value'])
                    <span class="px-2 py-1 text-xs rounded
                        @if($selectedNode['precedential_value'] === 'binding') bg-green-600
                        @elseif($selectedNode['precedential_value'] === 'persuasive') bg-yellow-600
                        @else bg-gray-600 @endif text-white">
                        {{ ucfirst($selectedNode['precedential_value']) }}
                    </span>
                    @endif

                    @if(isset($selectedNode['outcome']) && $selectedNode['outcome'])
                    <span class="px-2 py-1 text-xs rounded
                        @if($selectedNode['outcome'] === 'affirmed') bg-green-600
                        @elseif($selectedNode['outcome'] === 'reversed') bg-red-600
                        @elseif($selectedNode['outcome'] === 'remanded') bg-yellow-600
                        @else bg-gray-600 @endif text-white">
                        {{ ucfirst($selectedNode['outcome']) }}
                    </span>
                    @endif

                    {{-- Dissent Count Badge (Phase 5.3 Enhancement) --}}
                    @if(isset($selectedNode['dissent_count']) && $selectedNode['dissent_count'] > 0)
                    <span dusk="dissent-count-badge" class="px-2 py-1 text-xs rounded bg-red-700 text-white flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ $selectedNode['dissent_count'] }} {{ $selectedNode['dissent_count'] == 1 ? 'Dissent' : 'Dissents' }}
                    </span>
                    @endif

                    {{-- Concurrence Count Badge (Phase 5.3 Enhancement) --}}
                    @if(isset($selectedNode['concurrence_count']) && $selectedNode['concurrence_count'] > 0)
                    <span dusk="concurrence-count-badge" class="px-2 py-1 text-xs rounded bg-blue-700 text-white flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ $selectedNode['concurrence_count'] }} {{ $selectedNode['concurrence_count'] == 1 ? 'Concurrence' : 'Concurrences' }}
                    </span>
                    @endif
                </div>
                @endif

                {{-- Holding Panel (Phase 5.3 Enhancement) --}}
                @if($selectedNodeType === 'CourtDecisionDocument' && !empty($selectedNode['holding']))
                <div dusk="holding-panel" class="card mt-4">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Holding
                        </h2>
                    </div>
                    <div class="card-body">
                        <p class="text-gray-200 text-sm italic">
                            "{{ $selectedNode['holding'] }}"
                        </p>
                    </div>
                </div>
                @endif

                {{-- Amendment Tracking Panels (Phase 5.4 Enhancement) - LawDocument only --}}
                @if($selectedNodeType === 'LawDocument')
                    {{-- Amendments List --}}
                    @if(!empty($selectedNode['amendments']) && is_array($selectedNode['amendments']) && count($selectedNode['amendments']) > 0)
                    <div dusk="amendments-panel" class="card mt-4">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Amendments ({{ count($selectedNode['amendments']) }})
                            </h2>
                        </div>
                        <div class="card-body">
                            <ul class="space-y-2">
                                @foreach($selectedNode['amendments'] as $amendment)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-gray-200">{{ $amendment }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif

                    {{-- Repeal Status --}}
                    @if(!empty($selectedNode['repealed_by']) || !empty($selectedNode['repeal_date']))
                    <div dusk="repeal-panel" class="card mt-4 border-red-700">
                        <div class="card-header bg-red-900/30">
                            <h2 class="card-title flex items-center gap-2 text-red-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Repealed
                            </h2>
                        </div>
                        <div class="card-body">
                            <dl class="space-y-2 text-sm">
                                @if(!empty($selectedNode['repeal_date']))
                                <div>
                                    <dt class="text-gray-500">Repeal Date</dt>
                                    <dd class="text-gray-200">{{ $selectedNode['repeal_date'] }}</dd>
                                </div>
                                @endif
                                @if(!empty($selectedNode['repealed_by']))
                                <div>
                                    <dt class="text-gray-500">Repealed By</dt>
                                    <dd class="text-gray-200">{{ $selectedNode['repealed_by'] }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                    @endif

                    {{-- Parent Law Reference --}}
                    @if(!empty($selectedNode['parent_law_number']))
                    <div dusk="parent-law-panel" class="card mt-4">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2">
                                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                                </svg>
                                Amends Original Law
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="text-gray-200">{{ $selectedNode['parent_law_number'] }}</p>
                        </div>
                    </div>
                    @endif

                    {{-- Consolidation Date --}}
                    @if(!empty($selectedNode['consolidation_date']))
                    <div dusk="consolidation-panel" class="card mt-4">
                        <div class="card-header">
                            <h2 class="card-title flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Consolidated Text (Pročišćeni tekst)
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="text-gray-200">Last consolidated: {{ $selectedNode['consolidation_date'] }}</p>
                        </div>
                    </div>
                    @endif
                @endif

                {{-- Citation Analysis Button (only for Court Decisions) --}}
                @if($selectedNodeType === 'CourtDecisionDocument' && $selectedNodeId)
                <div class="mt-4">
                    <button
                        wire:click="openCitationAnalysis"
                        wire:loading.attr="disabled"
                        wire:target="openCitationAnalysis"
                        dusk="citation-analysis-button"
                        class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="openCitationAnalysis">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Citation Analysis
                        </span>
                        <span wire:loading wire:target="openCitationAnalysis" class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Opening...
                        </span>
                    </button>
                </div>
                @endif
                @endif

                <!-- Relationships Table -->
                @if($graphData && count($graphData['edges']) > 0)
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Relationships</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Properties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($graphData['edges'] as $edge)
                                    <tr>
                                        <td>
                                            <span class="badge-info">{{ $edge['type'] }}</span>
                                        </td>
                                        <td class="text-xs">{{ \Illuminate\Support\Str::limit($edge['source'], 30) }}</td>
                                        <td class="text-xs">{{ \Illuminate\Support\Str::limit($edge['target'], 30) }}</td>
                                        <td class="text-xs text-slate-600">
                                            @if(!empty($edge['properties']))
                                                {{ count($edge['properties']) }} properties
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </main>

    <style>
        /* Dark Blue Theme - matching TextractManager */
        :root {
            --bg: #0b1220;
            --surface: #0f172a;
            --card: #111827;
            --border: #1f2937;
            --fg: #e5e7eb;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --accent-hover: #0ea5e9;
        }

        .card {
            background: var(--card);
            border-radius: 1rem;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--fg);
        }

        .card-body {
            padding: 1.5rem;
            background: var(--card);
            color: var(--fg);
        }

        .card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--bg);
        }

        .stat-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #0ea5e9);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            border-color: var(--accent);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            margin-top: 0.25rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--fg);
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: var(--bg);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
            transform: translateY(-1px);
        }

        .form-input:disabled, .form-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #080d18;
        }

        .form-input::placeholder {
            color: #64748b;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            background: linear-gradient(180deg, var(--accent), var(--accent-hover));
            color: white;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            border: 1px solid #0284c7;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, #0ea5e9, #0284c7);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            background: linear-gradient(180deg, #475569, #334155);
            border-color: #475569;
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(56, 189, 248, 0.2);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--bg);
            color: var(--fg);
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #131b2e;
            border-color: #2d3748;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .btn-secondary:active:not(:disabled) {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .badge-info {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(14, 165, 233, 0.15);
            color: #7dd3fc;
            border: 1px solid rgba(14, 165, 233, 0.35);
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge-success {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.35);
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .data-table {
            width: 100%;
            font-size: 0.875rem;
            color: var(--fg);
        }

        .data-table thead {
            background: var(--bg);
        }

        .data-table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--fg);
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: var(--bg);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.1);
        }

        /* Graph specific styles */
        #graph-container {
            position: relative;
        }

        .graph-node {
            cursor: pointer;
            transition: all 0.3s;
        }

        .graph-node:hover {
            filter: brightness(1.2);
        }

        .graph-link {
            stroke: #64748b;
            stroke-opacity: 0.6;
        }

        .graph-link-label {
            font-size: 10px;
            fill: var(--muted);
        }

        .graph-node-label {
            font-size: 11px;
            font-weight: 500;
            pointer-events: none;
            fill: var(--fg);
        }

        /* Additional dark theme tweaks */
        .divide-y > * {
            border-color: var(--border) !important;
        }

        button[wire\:click*="selectRecentNode"] {
            background: var(--card);
            border-bottom: 1px solid var(--border);
        }

        button[wire\:click*="selectRecentNode"]:hover:not(:disabled) {
            background: var(--bg) !important;
            transform: translateX(4px);
        }

        button[wire\:click*="selectRecentNode"]:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        button[wire\:click*="selectRecentNode"] {
            transition: all 0.2s ease;
        }

        button[wire\:click*="selectRecentNode"] .text-slate-900 {
            color: var(--fg) !important;
        }

        button[wire\:click*="selectRecentNode"] .text-slate-500 {
            color: var(--muted) !important;
        }

        .text-slate-600 {
            color: var(--muted) !important;
        }

        .text-slate-900 {
            color: var(--fg) !important;
        }

        .bg-slate-100 {
            background: var(--bg) !important;
        }

        .text-slate-300 {
            color: var(--muted) !important;
        }

        .card-footer .text-slate-500 {
            color: var(--muted) !important;
        }

        /* Cluster cards */
        .cluster-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            transition: all 0.2s;
        }

        .cluster-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.15);
            transform: translateY(-4px);
        }

        .cluster-badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent);
            border: 1px solid rgba(56, 189, 248, 0.35);
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
        }

        /* Pulse animation for loading states */
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.4);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(56, 189, 248, 0);
            }
        }

        button[wire\:loading] {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        /* Fade-in animation for content */
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card, .stat-card, .cluster-card {
            animation: fade-in 0.4s ease-out;
        }

        /* Loading overlay improvements */
        [wire\:loading] {
            position: relative;
        }

        /* Smooth page transitions */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Screen reader only utility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>

    <!-- Graph rendering is handled by the ForceGraph Alpine component registered in ForceGraph.js -->

    {{-- Citation Analysis Modal --}}
    @if($showCitationAnalysis)
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
         dusk="citation-analysis-panel">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                <h3 class="text-xl font-semibold text-white">Citation Network Analysis</h3>
                <button
                    wire:click="closeCitationAnalysis"
                    wire:loading.attr="disabled"
                    wire:target="closeCitationAnalysis"
                    class="text-gray-400 hover:text-white transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    aria-label="Close citation analysis panel"
                >
                    <span wire:loading.remove wire:target="closeCitationAnalysis">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                    <span wire:loading wire:target="closeCitationAnalysis">
                        <svg class="animate-spin w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
            </div>

            {{-- Controls Section --}}
            <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
                <div class="flex flex-wrap items-end gap-4">
                    {{-- Decision ID Input --}}
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-sm font-medium text-gray-300 block mb-1">Decision ID</label>
                        <input
                            type="text"
                            wire:model.debounce.300ms="analysisDecisionId"
                            dusk="analysis-decision-input"
                            placeholder="Enter decision ID..."
                            class="w-full bg-gray-700 text-white rounded px-3 py-2 text-sm border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500 font-mono"
                        >
                    </div>

                    {{-- Analysis Type --}}
                    <div>
                        <label class="text-sm font-medium text-gray-300 block mb-1">Analysis Type</label>
                        <select
                            wire:model="citationOperation"
                            dusk="analysis-operation"
                            class="bg-gray-700 text-white rounded px-3 py-2 text-sm border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                            <option value="graph">Citation Graph</option>
                            <option value="authority">Authority Metrics</option>
                            <option value="patterns">Citation Patterns</option>
                            <option value="influence">Influence Spread</option>
                        </select>
                    </div>

                    {{-- Run Analysis Button --}}
                    <button
                        wire:click="runCitationAnalysis"
                        wire:loading.attr="disabled"
                        wire:target="runCitationAnalysis"
                        dusk="run-analysis-button"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded transition-all duration-200 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="runCitationAnalysis">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Run Analysis
                        </span>
                        <span wire:loading wire:target="runCitationAnalysis" class="flex items-center justify-center">
                            <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Analyzing...
                        </span>
                    </button>
                </div>
            </div>

            {{-- Results Area --}}
            <div class="flex-1 overflow-y-auto p-6">
                <div wire:loading wire:target="analyzeCitations,runCitationAnalysis" class="flex items-center justify-center h-64">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mx-auto"></div>
                        <p class="text-gray-400 mt-4">Analyzing citation network...</p>
                    </div>
                </div>

                <div wire:loading.remove wire:target="analyzeCitations,runCitationAnalysis">
                @if($citationAnalysisResults)
                    {{-- Authority Metrics Panel --}}
                    @if($citationOperation === 'authority')
                        <div dusk="authority-metrics-panel" class="space-y-6">
                            <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
                                Authority Metrics
                            </h4>

                            {{-- Key Metrics Grid --}}
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                {{-- H-Index --}}
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">H-Index</div>
                                    <div dusk="h-index-value" class="text-2xl font-bold text-purple-400">
                                        {{ $citationAnalysisResults['h_index'] ?? 0 }}
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1">
                                        Citation Impact Score
                                    </div>
                                </div>

                                {{-- Influence Rank --}}
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Influence Rank</div>
                                    <div dusk="influence-rank-value" class="text-lg font-semibold">
                                        @php
                                            $rank = $citationAnalysisResults['influence_rank'] ?? 'unknown';
                                            $rankColors = [
                                                'highly_influential' => 'text-green-400',
                                                'influential' => 'text-blue-400',
                                                'moderately_influential' => 'text-yellow-400',
                                                'emerging' => 'text-orange-400',
                                                'limited' => 'text-gray-400',
                                            ];
                                            $color = $rankColors[$rank] ?? 'text-gray-400';
                                        @endphp
                                        <span class="{{ $color }}">
                                            {{ ucwords(str_replace('_', ' ', $rank)) }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Citation Count (outgoing) --}}
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Citations Made</div>
                                    <div dusk="citation-count-value" class="text-2xl font-bold text-blue-400">
                                        {{ $citationAnalysisResults['citation_count'] ?? 0 }}
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1">
                                        Outgoing Citations
                                    </div>
                                </div>

                                {{-- Cited-By Count (incoming) --}}
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Times Cited</div>
                                    <div dusk="cited-by-count-value" class="text-2xl font-bold text-green-400">
                                        {{ $citationAnalysisResults['cited_by_count'] ?? 0 }}
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1">
                                        Incoming Citations
                                    </div>
                                </div>

                                {{-- Authority Score --}}
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Authority Score</div>
                                    <div dusk="authority-score-value" class="text-2xl font-bold text-purple-400">
                                        {{ number_format($citationAnalysisResults['authority_score'] ?? 0, 2) }}
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1">
                                        Weighted Metric
                                    </div>
                                </div>

                                {{-- Citation Velocity (if available) --}}
                                @if(isset($citationAnalysisResults['citation_velocity']))
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Citation Velocity</div>
                                    <div class="text-2xl font-bold text-yellow-400">
                                        {{ $citationAnalysisResults['citation_velocity'] }}
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1">
                                        Citations per Month
                                    </div>
                                </div>
                                @endif
                            </div>

                            {{-- Explanation --}}
                            <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                <h5 class="text-white font-medium mb-2">Metrics Explanation</h5>
                                <ul class="text-sm text-gray-400 space-y-1">
                                    <li>• <strong class="text-white">H-Index:</strong> A decision with h-index of N has N citations, each cited at least N times</li>
                                    <li>• <strong class="text-white">Influence Rank:</strong> Categorical ranking based on citation patterns and authority</li>
                                    <li>• <strong class="text-white">Authority Score:</strong> Weighted combination of incoming and outgoing citations</li>
                                </ul>
                            </div>
                        </div>
                    @endif

                    {{-- Citation Graph Panel --}}
                    @if($citationOperation === 'graph')
                        <div dusk="citation-graph-panel" class="space-y-6">
                            <div class="flex items-center justify-between border-b border-gray-700 pb-2">
                                <h4 class="text-lg font-semibold text-white">Citation Graph</h4>

                                {{-- Legend --}}
                                <div dusk="graph-legend" class="flex items-center gap-4 text-xs">
                                    <div class="flex items-center gap-1">
                                        <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                                        <span class="text-gray-400">Root Decision</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                        <span class="text-gray-400">Citing Decisions</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                        <span class="text-gray-400">Cited Decisions</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Graph Statistics --}}
                            <div dusk="graph-stats" class="grid grid-cols-4 gap-4 text-sm">
                                <div class="bg-gray-750 rounded p-3 border border-gray-700">
                                    <div class="text-gray-400">Nodes</div>
                                    <div class="text-xl font-bold text-white">
                                        {{ count($citationAnalysisResults['nodes'] ?? []) }}
                                    </div>
                                </div>
                                <div dusk="citation-graph-edges" class="bg-gray-750 rounded p-3 border border-gray-700">
                                    <div class="text-gray-400">Edges</div>
                                    <div class="text-xl font-bold text-white">
                                        {{ count($citationAnalysisResults['edges'] ?? []) }}
                                    </div>
                                </div>
                                <div class="bg-gray-750 rounded p-3 border border-gray-700">
                                    <div class="text-gray-400">Total Citations</div>
                                    <div class="text-xl font-bold text-white">
                                        {{ count($citationAnalysisResults['edges'] ?? []) }}
                                    </div>
                                </div>
                                <div class="bg-gray-750 rounded p-3 border border-gray-700">
                                    <div class="text-gray-400">Root</div>
                                    <div class="text-sm font-medium text-purple-400">
                                        {{ $citationAnalysisResults['root_decision'] ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>

                            {{-- D3.js Graph Canvas --}}
                            <div
                                id="citation-graph-canvas"
                                dusk="citation-graph-canvas"
                                class="bg-gray-900 rounded-lg border border-gray-700 min-h-[600px]"
                                x-data
                                x-init="
                                    $nextTick(() => {
                                        if (window.renderCitationGraph) {
                                            window.renderCitationGraph('citation-graph-canvas', @js($citationAnalysisResults));
                                        }
                                    });
                                "
                            ></div>

                            <p class="text-gray-500 text-xs text-center">
                                Drag nodes to rearrange. Purple = selected decision, Blue = citing decisions, Green = cited decisions.
                            </p>
                        </div>
                    @endif

                    {{-- Citation Patterns Panel --}}
                    @if($citationOperation === 'patterns')
                        <div dusk="citation-patterns-panel" class="space-y-6">
                            <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
                                Citation Patterns
                            </h4>

                            {{-- Temporal Distribution --}}
                            <div>
                                <h5 class="text-white font-medium mb-3">Temporal Distribution</h5>
                                <div dusk="temporal-distribution-chart" class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                    @php
                                        $temporal = $citationAnalysisResults['temporal_distribution'] ?? [];
                                    @endphp

                                    @if(!empty($temporal))
                                        <div class="space-y-2">
                                            @foreach($temporal as $period => $count)
                                                <div class="flex items-center gap-3">
                                                    <div class="text-sm text-gray-400 w-24">{{ $period }}</div>
                                                    <div class="flex-1 bg-gray-800 rounded-full h-6 overflow-hidden">
                                                        <div
                                                            class="bg-purple-500 h-full flex items-center justify-end pr-2"
                                                            style="width: {{ min(100, ($count / max($temporal)) * 100) }}%"
                                                        >
                                                            <span class="text-xs text-white font-medium">{{ $count }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center py-4">No temporal data available</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Citation Types Breakdown --}}
                            <div>
                                <h5 class="text-white font-medium mb-3">Citation Types</h5>
                                <div dusk="citation-types-breakdown" class="grid grid-cols-2 gap-4">
                                    @php
                                        $types = $citationAnalysisResults['citation_types'] ?? [];
                                    @endphp

                                    <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                        <div class="text-gray-400 text-sm mb-1">Direct Citations</div>
                                        <div class="text-2xl font-bold text-blue-400">
                                            {{ $types['direct'] ?? 0 }}
                                        </div>
                                    </div>

                                    <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                        <div class="text-gray-400 text-sm mb-1">Indirect Citations</div>
                                        <div class="text-2xl font-bold text-green-400">
                                            {{ $types['indirect'] ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Detected Patterns --}}
                            <div>
                                <h5 class="text-white font-medium mb-3">Detected Patterns</h5>
                                <div dusk="patterns-list" class="space-y-2">
                                    @php
                                        $patterns = $citationAnalysisResults['patterns'] ?? [];
                                    @endphp

                                    @forelse($patterns as $pattern)
                                        <div class="bg-gray-750 rounded-lg p-3 border border-gray-700">
                                            <div class="flex items-start gap-2">
                                                <svg class="w-5 h-5 text-purple-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-sm text-gray-300">{{ $pattern }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-gray-500 text-center py-4">No patterns detected</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Influence Spread Panel --}}
                    @if($citationOperation === 'influence')
                        <div dusk="influence-spread-panel" class="space-y-6">
                            <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
                                Influence Spread
                            </h4>

                            {{-- Key Metrics --}}
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Direct Influences</div>
                                    <div dusk="direct-influence-count" class="text-2xl font-bold text-blue-400">
                                        {{ count($citationAnalysisResults['direct_influences'] ?? []) }}
                                    </div>
                                </div>

                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Indirect Influences</div>
                                    <div dusk="indirect-influence-count" class="text-2xl font-bold text-green-400">
                                        {{ count($citationAnalysisResults['indirect_influences'] ?? []) }}
                                    </div>
                                </div>

                                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                                    <div class="text-gray-400 text-sm mb-1">Total Reach</div>
                                    <div dusk="total-reach-value" class="text-2xl font-bold text-purple-400">
                                        {{ $citationAnalysisResults['total_reach'] ?? 0 }}
                                    </div>
                                </div>
                            </div>

                            {{-- Influence Visualization --}}
                            <div>
                                <h5 class="text-white font-medium mb-3">Influence Spread Chart</h5>
                                <div dusk="influence-spread-chart" class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                                    <div class="flex items-center justify-center gap-8">
                                        {{-- Root Decision --}}
                                        <div class="text-center">
                                            <div class="w-20 h-20 rounded-full bg-purple-600 flex items-center justify-center mb-2">
                                                <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <div class="text-xs text-gray-400">Root Decision</div>
                                        </div>

                                        {{-- Arrow --}}
                                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                        </svg>

                                        {{-- Direct Influences --}}
                                        <div class="text-center">
                                            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center mb-2">
                                                <span class="text-2xl font-bold text-white">
                                                    {{ count($citationAnalysisResults['direct_influences'] ?? []) }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-400">Direct</div>
                                        </div>

                                        {{-- Arrow --}}
                                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                        </svg>

                                        {{-- Indirect Influences --}}
                                        <div class="text-center">
                                            <div class="w-16 h-16 rounded-full bg-green-600 flex items-center justify-center mb-2">
                                                <span class="text-2xl font-bold text-white">
                                                    {{ count($citationAnalysisResults['indirect_influences'] ?? []) }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-400">Indirect</div>
                                        </div>
                                    </div>

                                    <p class="text-center text-gray-500 text-xs mt-6">
                                        Influence spreads from root decision through direct citations to indirect citations
                                    </p>
                                </div>
                            </div>

                            {{-- Influenced Decisions List --}}
                            <div>
                                <h5 class="text-white font-medium mb-3">Influenced Decisions</h5>
                                <div dusk="influenced-decisions-list" class="space-y-2 max-h-96 overflow-y-auto">
                                    @php
                                        $allInfluenced = array_merge(
                                            array_map(fn($d) => ['decision' => $d, 'level' => 'direct'], $citationAnalysisResults['direct_influences'] ?? []),
                                            array_map(fn($d) => ['decision' => $d, 'level' => 'indirect'], $citationAnalysisResults['indirect_influences'] ?? [])
                                        );
                                    @endphp

                                    @forelse($allInfluenced as $influenced)
                                        <div class="bg-gray-750 rounded-lg p-3 border border-gray-700 flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-2 h-2 rounded-full {{ $influenced['level'] === 'direct' ? 'bg-blue-400' : 'bg-green-400' }}"></div>
                                                <span class="text-sm text-gray-300">{{ $influenced['decision'] }}</span>
                                            </div>
                                            <span class="text-xs text-gray-500 uppercase">{{ $influenced['level'] }}</span>
                                        </div>
                                    @empty
                                        <p class="text-gray-500 text-center py-4">No influenced decisions found</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center text-gray-400 py-12">
                        <p>Select an analysis type and click "Run Analysis" to begin.</p>
                    </div>
                @endif

                @if($error)
                    <div class="bg-red-900 bg-opacity-20 border border-red-700 rounded p-4 mt-4">
                        <p class="text-red-400">{{ $error }}</p>
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
