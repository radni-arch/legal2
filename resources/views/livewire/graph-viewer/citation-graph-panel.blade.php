<div dusk="citation-graph-panel" class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold" style="color: var(--accent);">Citation Graph</h3>
        <div class="flex items-center gap-2">
            <span class="badge-info" dusk="citation-graph-depth">Depth: {{ $citationAnalysisResults['depth'] ?? 2 }}</span>
            <span class="badge-success" dusk="citation-graph-nodes">{{ count($citationAnalysisResults['nodes'] ?? []) }} nodes</span>
            <span class="badge-info" dusk="citation-graph-edges">{{ count($citationAnalysisResults['edges'] ?? []) }} edges</span>
        </div>
    </div>

    <!-- Graph Canvas -->
    <div dusk="citation-graph-canvas" class="card">
        <div class="card-body">
            <div class="bg-slate-800 rounded-lg p-6 text-center" style="min-height: 400px;">
                <div class="flex items-center justify-center h-full">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p style="color: var(--muted);">Citation network visualization</p>
                        <p class="text-sm mt-2" style="color: var(--muted);">
                            Root: <span style="color: var(--fg);">{{ $citationAnalysisResults['root_decision'] ?? 'N/A' }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graph Legend -->
    <div dusk="graph-legend" class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Legend</h4>
        </div>
        <div class="card-body">
            <div class="grid md:grid-cols-3 gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded-full" style="background: #8b5cf6;"></div>
                    <span style="color: var(--fg);">Root Decision</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded-full" style="background: #3b82f6;"></div>
                    <span style="color: var(--fg);">Citing Decisions</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded-full" style="background: #06b6d4;"></div>
                    <span style="color: var(--fg);">Cited Decisions</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Graph Statistics -->
    @if(isset($citationAnalysisResults['nodes']) && count($citationAnalysisResults['nodes']) > 0)
    <div dusk="graph-stats" class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Statistics</h4>
        </div>
        <div class="card-body">
            <div class="grid md:grid-cols-3 gap-4">
                <div class="stat-card">
                    <div class="stat-label">Total Citations</div>
                    <div class="stat-value">{{ count($citationAnalysisResults['edges'] ?? []) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Nodes</div>
                    <div class="stat-value">{{ count($citationAnalysisResults['nodes'] ?? []) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Graph Depth</div>
                    <div class="stat-value">{{ $citationAnalysisResults['depth'] ?? 2 }}</div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
