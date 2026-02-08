<div dusk="citation-patterns-panel" class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold" style="color: var(--accent);">Citation Patterns</h3>
        <span class="badge-info">Decision: {{ $citationAnalysisResults['decision_id'] ?? 'N/A' }}</span>
    </div>

    <!-- Detected Patterns -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Detected Patterns</h4>
        </div>
        <div class="card-body">
            <div dusk="patterns-list">
                @if(!empty($citationAnalysisResults['patterns']))
                    <div class="space-y-2">
                        @foreach($citationAnalysisResults['patterns'] as $pattern)
                        <div class="flex items-center gap-3 p-3 rounded-lg" style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span style="color: var(--fg);">{{ str_replace('_', ' ', ucwords($pattern, '_')) }}</span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center py-4" style="color: var(--muted);">No patterns detected</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Temporal Distribution -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Temporal Distribution</h4>
        </div>
        <div class="card-body">
            <div dusk="temporal-distribution-chart">
                @if(!empty($citationAnalysisResults['temporal_distribution']))
                    <div class="space-y-3">
                        @foreach($citationAnalysisResults['temporal_distribution'] as $month => $count)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm" style="color: var(--fg);">{{ $month }}</span>
                                <span class="text-sm font-bold" style="color: var(--accent);">{{ $count }}</span>
                            </div>
                            <div class="w-full bg-slate-700 rounded-full h-2 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500"
                                     style="width: {{ min(100, ($count / max(array_values($citationAnalysisResults['temporal_distribution'])) * 100)) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                        <p style="color: var(--muted);">No temporal data available</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Citation Types Breakdown -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Citation Types</h4>
        </div>
        <div class="card-body">
            <div dusk="citation-types-breakdown">
                @if(!empty($citationAnalysisResults['citation_types']))
                    <div class="grid md:grid-cols-2 gap-4">
                        @foreach($citationAnalysisResults['citation_types'] as $type => $count)
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">
                                    {{ ucfirst($type) }} Citations
                                </div>
                                <div class="text-3xl font-bold" style="color: var(--accent);">{{ $count }}</div>
                                <div class="w-full bg-slate-700 rounded-full h-2 mt-3 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500"
                                         style="width: {{ $count > 0 ? min(100, ($count / array_sum($citationAnalysisResults['citation_types']) * 100)) : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center py-4" style="color: var(--muted);">No citation type data available</p>
                @endif
            </div>
        </div>
    </div>
</div>
