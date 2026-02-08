<div dusk="authority-metrics-panel" class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold" style="color: var(--accent);">Authority Metrics</h3>
        <span class="badge-info">Decision: {{ $citationAnalysisResults['decision_id'] ?? 'N/A' }}</span>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid md:grid-cols-5 gap-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Authority Score</div>
                <div dusk="authority-score-value" class="text-3xl font-bold" style="color: var(--accent);">
                    {{ $citationAnalysisResults['authority_score'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">H-Index</div>
                <div dusk="h-index-value" class="text-3xl font-bold" style="color: #10b981;">
                    {{ $citationAnalysisResults['h_index'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Citations (Out)</div>
                <div dusk="citation-count-value" class="text-3xl font-bold" style="color: #3b82f6;">
                    {{ $citationAnalysisResults['citation_count'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Cited By (In)</div>
                <div dusk="cited-by-count-value" class="text-3xl font-bold" style="color: #f59e0b;">
                    {{ $citationAnalysisResults['cited_by_count'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Influence Rank</div>
                <div dusk="influence-rank-value" class="text-sm font-bold px-2 py-1 rounded"
                     style="background: rgba(139, 92, 246, 0.2); color: var(--accent);">
                    {{ str_replace('_', ' ', ucwords($citationAnalysisResults['influence_rank'] ?? 'not_influential', '_')) }}
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Metrics -->
    <div class="card">
        <div class="card-header">
            <h4 class="text-sm font-semibold" style="color: var(--fg);">Authority Analysis Details</h4>
        </div>
        <div class="card-body">
            <dl class="grid md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium mb-2" style="color: var(--muted);">Authority Score</dt>
                    <dd style="color: var(--fg);">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-slate-700 rounded-full h-3 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500"
                                     style="width: {{ min(100, ($citationAnalysisResults['authority_score'] ?? 0)) }}%"></div>
                            </div>
                            <span class="text-lg font-bold" style="color: var(--accent);">
                                {{ number_format($citationAnalysisResults['authority_score'] ?? 0, 2) }}
                            </span>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--muted);">
                            Calculated as: (Cited By × 0.7) + (Citations × 0.3)
                        </p>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium mb-2" style="color: var(--muted);">Influence Classification</dt>
                    <dd style="color: var(--fg);">
                        <div class="inline-block px-4 py-2 rounded-lg"
                             style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35);">
                            <span class="font-bold" style="color: var(--accent);">
                                {{ str_replace('_', ' ', ucwords($citationAnalysisResults['influence_rank'] ?? 'not_influential', '_')) }}
                            </span>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--muted);">
                            Based on {{ $citationAnalysisResults['cited_by_count'] ?? 0 }} incoming citations
                        </p>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium mb-2" style="color: var(--muted);">H-Index</dt>
                    <dd style="color: var(--fg);">
                        <span class="text-2xl font-bold" style="color: #10b981;">
                            {{ $citationAnalysisResults['h_index'] ?? 0 }}
                        </span>
                        <p class="text-xs mt-1" style="color: var(--muted);">
                            Minimum of outgoing citations and incoming citations
                        </p>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium mb-2" style="color: var(--muted);">Citation Velocity</dt>
                    <dd style="color: var(--fg);">
                        <span class="text-2xl font-bold" style="color: #38bdf8;">
                            {{ number_format($citationAnalysisResults['citation_velocity'] ?? 0, 2) }}
                        </span>
                        <p class="text-xs mt-1" style="color: var(--muted);">
                            Citations per month (recent trend)
                        </p>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
