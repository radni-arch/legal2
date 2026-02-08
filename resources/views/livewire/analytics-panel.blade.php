<div class="space-y-6" dusk="analytics-panel-container">
    <style>
    .analytics-card {
        background: rgba(17, 24, 39, 0.6);
        border: 1px solid #1f2937;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .analytics-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #1f2937;
    }

    .analytics-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #e5e7eb;
    }

    .analytics-card-body {
        padding: 1.5rem;
    }

    .view-button-active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .view-button-inactive {
        background: #1f2937;
    }

    .view-button-inactive:hover {
        background: #374151;
    }
    </style>

    <!-- View Selector -->
    <div class="flex gap-3 mb-6 flex-wrap" dusk="view-switcher">
        @foreach($views as $viewKey => $viewLabel)
            <button
                wire:click="switchView('{{ $viewKey }}')"
                wire:loading.attr="disabled"
                wire:target="switchView"
                dusk="view-{{ $viewKey }}-btn"
                class="px-4 py-2 rounded-lg font-medium transition-all duration-200 @if($selectedView === $viewKey) view-button-active text-white shadow-lg shadow-green-500/50 @else view-button-inactive text-gray-400 hover:text-gray-200 @endif disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span class="flex items-center gap-2">
                    @if($viewKey === 'influential')
                        <span class="text-xl">🏆</span>
                    @elseif($viewKey === 'contradictions')
                        <span class="text-xl">⚖️</span>
                    @elseif($viewKey === 'outliers')
                        <span class="text-xl">📊</span>
                    @elseif($viewKey === 'clusters')
                        <span class="text-xl">🔗</span>
                    @endif
                    <span wire:loading.remove wire:target="switchView">{{ $viewLabel }}</span>
                    <span wire:loading wire:target="switchView" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Switching...
                    </span>
                </span>
            </button>
        @endforeach

        <button
            wire:click="refreshData"
            wire:loading.attr="disabled"
            wire:target="refreshData"
            dusk="refresh-data-btn"
            class="ml-auto px-4 py-2 rounded-lg font-medium bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg"
        >
            <span wire:loading.remove wire:target="refreshData" class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </span>
            <span wire:loading wire:target="refreshData" class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Refreshing...
            </span>
        </button>
    </div>

    <!-- Content Area with Loading Overlay -->
    <div class="relative" dusk="analytics-content-container">
        <!-- Global Loading Overlay -->
        <div wire:loading wire:target="switchView,refreshData"
             class="absolute inset-0 bg-gray-900/95 backdrop-blur-sm z-50 flex items-center justify-center rounded-lg"
             dusk="content-loading-overlay">
            <div class="text-center">
                <svg class="animate-spin h-16 w-16 text-green-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-white font-medium text-lg mb-2">Loading Analytics...</p>
                <p class="text-gray-400 text-sm">Please wait while we fetch your data</p>
            </div>
        </div>

        <!-- Influential Decisions View -->
        @if($selectedView === 'influential')
            <div class="analytics-card" dusk="influential-view">
                <div class="analytics-card-header" dusk="influential-header">
                    <h2 class="analytics-card-title" dusk="influential-title">Top Influential Decisions (PageRank)</h2>
                    <p class="text-sm mt-1" style="color: var(--muted);" dusk="influential-subtitle">Decisions ranked by citation impact using PageRank algorithm</p>
                </div>
                <div class="analytics-card-body" dusk="influential-body">
                    @if(count($influentialDecisions) > 0)
                        <div class="overflow-x-auto" dusk="influential-table-container">
                            <table class="w-full text-sm" dusk="influential-table">
                                <thead dusk="influential-table-header">
                                    <tr style="border-bottom: 1px solid #1f2937;">
                                        <th class="text-left py-3 px-2" style="color: #9ca3af;" dusk="header-rank">Rank</th>
                                        <th class="text-left py-3 px-2" style="color: #9ca3af;" dusk="header-case-number">Case Number</th>
                                        <th class="text-left py-3 px-2" style="color: #9ca3af;" dusk="header-court">Court</th>
                                        <th class="text-left py-3 px-2" style="color: #9ca3af;" dusk="header-date">Date</th>
                                        <th class="text-left py-3 px-2" style="color: #9ca3af;" dusk="header-pagerank">PageRank Score</th>
                                    </tr>
                                </thead>
                                <tbody dusk="influential-table-body">
                                    @foreach($influentialDecisions as $index => $decision)
                                    <tr style="border-bottom: 1px solid rgba(31, 41, 55, 0.5);"
                                        dusk="influential-row-{{ $index }}"
                                        class="hover:bg-gray-800/50 transition-colors duration-150">
                                        <td class="py-3 px-2" dusk="rank-{{ $index }}">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full @if($index < 3) bg-gradient-to-br from-yellow-500 to-yellow-600 text-yellow-100 @else bg-gradient-to-br from-blue-500 to-blue-600 text-blue-100 @endif font-semibold shadow-md">
                                                {{ $index + 1 }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-2 font-medium" style="color: #e5e7eb;" dusk="case-number-{{ $index }}">
                                            {{ $decision['case_number'] ?? 'N/A' }}
                                            @if(!empty($decision['ecli']))
                                                <div class="text-xs" style="color: #6b7280;" dusk="ecli-{{ $index }}">{{ $decision['ecli'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-2" style="color: #9ca3af;" dusk="court-{{ $index }}">{{ $decision['court'] ?? 'Unknown' }}</td>
                                        <td class="py-3 px-2" style="color: #9ca3af;" dusk="date-{{ $index }}">
                                            {{ isset($decision['date']) ? date('Y-m-d', strtotime($decision['date'])) : 'N/A' }}
                                        </td>
                                        <td class="py-3 px-2" dusk="pagerank-{{ $index }}">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-700 rounded-full h-2 overflow-hidden max-w-[100px]" dusk="pagerank-bar-{{ $index }}">
                                                    <div class="h-full bg-gradient-to-r from-green-500 to-emerald-500"
                                                         style="width: {{ min(100, ($decision['rank'] ?? 0) * 100) }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium" style="color: #10b981;" dusk="pagerank-value-{{ $index }}">{{ number_format(($decision['rank'] ?? 0), 3) }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12" style="color: #6b7280;" dusk="influential-empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3" style="color: #4b5563;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="influential-empty-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p dusk="influential-empty-message">No influential decisions data available</p>
                            <p class="text-xs mt-2" dusk="influential-empty-hint">Run <code class="bg-gray-800 px-2 py-1 rounded">php artisan graph:calculate-pagerank</code> to generate metrics</p>
                        </div>
                    @endif
                </div>
            </div>

        <!-- Contradictions View -->
        @elseif($selectedView === 'contradictions')
            <div class="analytics-card" dusk="contradictions-view">
                <div class="analytics-card-header" dusk="contradictions-header">
                    <h2 class="analytics-card-title" dusk="contradictions-title">Contradiction Detection</h2>
                    <p class="text-sm mt-1" style="color: var(--muted);" dusk="contradictions-subtitle">Automated detection of contradicting court decisions using AI</p>
                </div>
                <div class="analytics-card-body" dusk="contradictions-body">
                    <div class="text-center py-12" style="color: #94a3b8;" dusk="contradictions-coming-soon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: #f59e0b;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="contradictions-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <h3 class="text-xl font-semibold mb-2" style="color: #e5e7eb;" dusk="contradictions-coming-soon-title">Coming Soon</h3>
                        <p dusk="contradictions-coming-soon-description">This feature will provide advanced AI-powered analysis to detect contradictory legal decisions</p>
                        <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-orange-900/30 border border-orange-700 rounded-lg" dusk="contradictions-status-badge">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                            </span>
                            <span class="text-sm text-orange-300 font-medium">In Development</span>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Outliers View -->
        @elseif($selectedView === 'outliers')
            <div class="analytics-card" dusk="outliers-view">
                <div class="analytics-card-header" dusk="outliers-header">
                    <h2 class="analytics-card-title" dusk="outliers-title">Outlier Detection</h2>
                    <p class="text-sm mt-1" style="color: var(--muted);" dusk="outliers-subtitle">Statistical analysis for identifying anomalous prosecution patterns</p>
                </div>
                <div class="analytics-card-body" dusk="outliers-body">
                    <div class="text-center py-12" style="color: #94a3b8;" dusk="outliers-coming-soon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="outliers-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="text-xl font-semibold mb-2" style="color: #e5e7eb;" dusk="outliers-coming-soon-title">Coming Soon</h3>
                        <p dusk="outliers-coming-soon-description">Advanced statistical analysis to identify unusual patterns in prosecution data</p>
                        <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-red-900/30 border border-red-700 rounded-lg" dusk="outliers-status-badge">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                            <span class="text-sm text-red-300 font-medium">In Development</span>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Citation Clusters View -->
        @elseif($selectedView === 'clusters')
            <div class="analytics-card" dusk="clusters-view">
                <div class="analytics-card-header" dusk="clusters-header">
                    <h2 class="analytics-card-title" dusk="clusters-title">Citation Clusters (Louvain)</h2>
                    <p class="text-sm mt-1" style="color: var(--muted);" dusk="clusters-subtitle">Communities of related decisions identified by Louvain clustering</p>
                </div>
                <div class="analytics-card-body" dusk="clusters-body">
                    @if(count($citationClusters) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" dusk="clusters-grid">
                            @foreach($citationClusters as $index => $cluster)
                            <div class="p-4 rounded-lg border border-gray-700 bg-gray-900 hover:border-blue-500 transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/20"
                                 dusk="cluster-card-{{ $index }}">
                                <div class="flex items-center justify-between mb-3" dusk="cluster-header-{{ $index }}">
                                    <span class="text-lg font-semibold bg-gradient-to-r from-blue-400 to-blue-500 bg-clip-text text-transparent"
                                          dusk="cluster-id-{{ $index }}">
                                        Cluster #{{ $cluster['community_id'] ?? 'N/A' }}
                                    </span>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-blue-600 to-blue-700 text-blue-100 shadow-md"
                                          dusk="cluster-size-{{ $index }}">
                                        {{ $cluster['size'] ?? 0 }} decisions
                                    </span>
                                </div>
                                @if(isset($cluster['modularity']))
                                    <div class="flex items-center gap-2" dusk="cluster-modularity-{{ $index }}">
                                        <span class="text-sm" style="color: #9ca3af;" dusk="cluster-modularity-label-{{ $index }}">Modularity:</span>
                                        <div class="flex-1 bg-gray-700 rounded-full h-2 overflow-hidden" dusk="cluster-modularity-bar-{{ $index }}">
                                            <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500"
                                                 style="width: {{ ($cluster['modularity'] * 100) }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium" style="color: #a78bfa;" dusk="cluster-modularity-value-{{ $index }}">{{ number_format($cluster['modularity'], 2) }}</span>
                                    </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12" style="color: #6b7280;" dusk="clusters-empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3" style="color: #4b5563;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="clusters-empty-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <p dusk="clusters-empty-message">No citation clusters data available</p>
                            <p class="text-xs mt-2" dusk="clusters-empty-hint">Run <code class="bg-gray-800 px-2 py-1 rounded">php artisan graph:detect-clusters</code> to generate clusters</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
