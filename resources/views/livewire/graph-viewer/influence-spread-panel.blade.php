<div dusk="influence-spread-panel" class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold" style="color: var(--accent);">Influence Spread</h3>
        <span class="badge-info">Decision: {{ $citationAnalysisResults['decision_id'] ?? 'N/A' }}</span>
    </div>

    <!-- Influence Metrics -->
    <div class="grid md:grid-cols-3 gap-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Direct Influences</div>
                <div dusk="direct-influence-count" class="text-3xl font-bold" style="color: #3b82f6;">
                    {{ $citationAnalysisResults['influence_spread']['direct'] ?? 0 }}
                </div>
                <p class="text-xs mt-2" style="color: var(--muted);">Decisions this one cites</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Indirect Influences</div>
                <div dusk="indirect-influence-count" class="text-3xl font-bold" style="color: #06b6d4;">
                    {{ $citationAnalysisResults['influence_spread']['indirect'] ?? 0 }}
                </div>
                <p class="text-xs mt-2" style="color: var(--muted);">Second-degree citations</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Total Reach</div>
                <div dusk="total-reach-value" class="text-3xl font-bold" style="color: var(--accent);">
                    {{ $citationAnalysisResults['influence_spread']['total_reach'] ?? 0 }}
                </div>
                <p class="text-xs mt-2" style="color: var(--muted);">Combined influence</p>
            </div>
        </div>
    </div>

    <!-- Influence Spread Visualization -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Influence Visualization</h4>
        </div>
        <div class="card-body">
            <div dusk="influence-spread-chart" class="bg-slate-800 rounded-lg p-8">
                <div class="flex items-center justify-center gap-8">
                    <!-- Direct Influences -->
                    <div class="text-center">
                        <div class="w-24 h-24 rounded-full flex items-center justify-center mb-2"
                             style="background: rgba(59, 130, 246, 0.2); border: 3px solid #3b82f6;">
                            <span class="text-2xl font-bold" style="color: #3b82f6;">
                                {{ $citationAnalysisResults['influence_spread']['direct'] ?? 0 }}
                            </span>
                        </div>
                        <p class="text-xs" style="color: var(--muted);">Direct</p>
                    </div>

                    <!-- Arrow -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>

                    <!-- Indirect Influences -->
                    <div class="text-center">
                        <div class="w-24 h-24 rounded-full flex items-center justify-center mb-2"
                             style="background: rgba(6, 182, 212, 0.2); border: 3px solid #06b6d4;">
                            <span class="text-2xl font-bold" style="color: #06b6d4;">
                                {{ $citationAnalysisResults['influence_spread']['indirect'] ?? 0 }}
                            </span>
                        </div>
                        <p class="text-xs" style="color: var(--muted);">Indirect</p>
                    </div>

                    <!-- Arrow -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>

                    <!-- Total Reach -->
                    <div class="text-center">
                        <div class="w-32 h-32 rounded-full flex items-center justify-center mb-2"
                             style="background: rgba(56, 189, 248, 0.2); border: 4px solid var(--accent);">
                            <span class="text-3xl font-bold" style="color: var(--accent);">
                                {{ $citationAnalysisResults['influence_spread']['total_reach'] ?? 0 }}
                            </span>
                        </div>
                        <p class="text-sm font-semibold" style="color: var(--accent);">Total Reach</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Influenced Decisions List -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Influenced Decisions</h4>
        </div>
        <div class="card-body p-0">
            <div dusk="influenced-decisions-list">
                @if(!empty($citationAnalysisResults['influenced_decisions']))
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Decision ID</th>
                                    <th>Influence Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($citationAnalysisResults['influenced_decisions'], 0, 10) as $decision)
                                <tr>
                                    <td>
                                        <span class="font-mono text-sm" style="color: var(--accent);">
                                            {{ $decision['id'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                              style="background: {{ $decision['level'] === 'direct' ? 'rgba(59, 130, 246, 0.2)' : 'rgba(6, 182, 212, 0.2)' }};
                                                     color: {{ $decision['level'] === 'direct' ? '#3b82f6' : '#06b6d4' }};">
                                            {{ ucfirst($decision['level']) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($citationAnalysisResults['influenced_decisions']) > 10)
                    <div class="p-4 text-center" style="border-top: 1px solid var(--border);">
                        <p class="text-sm" style="color: var(--muted);">
                            Showing 10 of {{ count($citationAnalysisResults['influenced_decisions']) }} influenced decisions
                        </p>
                    </div>
                    @endif
                @else
                    <div class="p-8 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p style="color: var(--muted);">No influenced decisions found</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
