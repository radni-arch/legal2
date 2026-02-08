<div class="agent-performance-dashboard">
    {{-- Unified Header --}}
    <x-page-header
        title="Agent Performance Dashboard"
        subtitle="Monitor agent execution metrics and success rates"
        route-name="agent.performance"
    >
        <x-slot:actions>
            <button wire:click="refreshDashboard"
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: 0.875rem; border: 1px solid var(--border, #1f2937); border-radius: 0.5rem;">
                Refresh
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <div class="card flex flex-wrap items-end gap-4" style="padding: 1rem 1.5rem; border-radius: 0 0 .75rem .75rem; margin-bottom: 1.5rem;">
        <div class="filter-group">
            <label style="color: var(--muted, #94a3b8); font-size: .85rem; display: block; margin-bottom: .25rem;">Agent Type:</label>
            <input type="text" wire:model.live="filterAgentType" placeholder="Filter by agent type" class="dt-input">
        </div>
        <div class="filter-group">
            <label style="color: var(--muted, #94a3b8); font-size: .85rem; display: block; margin-bottom: .25rem;">Date From:</label>
            <input type="date" wire:model.live="filterDateFrom" class="dt-input">
        </div>
        <div class="filter-group">
            <label style="color: var(--muted, #94a3b8); font-size: .85rem; display: block; margin-bottom: .25rem;">Date To:</label>
            <input type="date" wire:model.live="filterDateTo" class="dt-input">
        </div>
    </div>

    @if(!$hasData)
        {{-- Empty State --}}
        <div style="text-align: center; padding: 3rem 1rem; color: var(--muted, #94a3b8);">
            <p style="font-size: 2rem; font-weight: 700; margin-bottom: .5rem;">0</p>
            <p>No data available</p>
        </div>
    @else
        {{-- Key Metrics --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4" style="margin-bottom: 1.5rem;">
            <div class="card" style="padding: 1.25rem;">
                <div style="color: var(--fg, #e5e7eb); font-size: 2rem; font-weight: 700;">{{ $totalRuns }}</div>
                <div style="color: var(--muted, #94a3b8); font-size: .85rem;">Total Runs</div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <div style="color: var(--fg, #e5e7eb); font-size: 2rem; font-weight: 700;">{{ $successRate }}%</div>
                <div style="color: var(--muted, #94a3b8); font-size: .85rem;">Success Rate</div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <div style="color: var(--fg, #e5e7eb); font-size: 2rem; font-weight: 700;">{{ $averageConfidence }}</div>
                <div style="color: var(--muted, #94a3b8); font-size: .85rem;">Average Confidence</div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <div style="color: var(--fg, #e5e7eb); font-size: 2rem; font-weight: 700;">{{ $learningOpportunities }}</div>
                <div style="color: var(--muted, #94a3b8); font-size: .85rem;">Learning Opportunities</div>
            </div>

            <div class="card" style="padding: 1.25rem;">
                <div style="color: var(--fg, #e5e7eb); font-size: 2rem; font-weight: 700;">{{ $feedbackIncorporationRate }}%</div>
                <div style="color: var(--muted, #94a3b8); font-size: .85rem;">Feedback Incorporation</div>
            </div>
        </div>

        {{-- Agent Type Breakdown --}}
        <div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="color: var(--fg, #e5e7eb); font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Agent Type Breakdown</h3>
            <div class="flex flex-col gap-2">
                @foreach($agentTypeBreakdown as $type => $count)
                    <div class="flex items-center justify-between" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); padding: .65rem 1rem; border-radius: .5rem;">
                        <span style="color: var(--fg, #e5e7eb);">{{ $type }}</span>
                        <span style="color: var(--accent, #38bdf8); font-weight: 600;">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Time Series Chart --}}
        <div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="color: var(--fg, #e5e7eb); font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Performance Over Time</h3>
            <div class="chart-container">
                {{-- Chart will be rendered here with JavaScript library --}}
                <div id="timeSeriesChart" data-chart='@json($timeSeriesData)'></div>
            </div>
        </div>

        {{-- Comparison Chart (Before vs After) --}}
        <div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="color: var(--fg, #e5e7eb); font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Before vs After Active Learning</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); padding: 1rem; border-radius: .5rem;">
                    <h4 style="color: var(--muted, #94a3b8); font-size: .9rem; font-weight: 600; margin-bottom: .5rem;">Before</h4>
                    <p style="color: var(--fg, #e5e7eb);">Avg Confidence: {{ $comparisonData['before']['avg_confidence'] }}</p>
                    <p style="color: var(--fg, #e5e7eb);">Count: {{ $comparisonData['before']['count'] }}</p>
                </div>
                <div style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); padding: 1rem; border-radius: .5rem;">
                    <h4 style="color: var(--muted, #94a3b8); font-size: .9rem; font-weight: 600; margin-bottom: .5rem;">After</h4>
                    <p style="color: var(--fg, #e5e7eb);">Avg Confidence: {{ $comparisonData['after']['avg_confidence'] }}</p>
                    <p style="color: var(--fg, #e5e7eb);">Count: {{ $comparisonData['after']['count'] }}</p>
                </div>
            </div>
        </div>

        {{-- Improvement Metrics --}}
        <div class="card" style="padding: 1.25rem 1.5rem;">
            <h3 style="color: var(--fg, #e5e7eb); font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Improvement Metrics</h3>
            <div class="flex items-center justify-between" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); padding: .65rem 1rem; border-radius: .5rem;">
                <span style="color: var(--muted, #94a3b8);">Confidence Improvement:</span>
                <span style="color: var(--success, #22c55e); font-weight: 700;">{{ $improvementMetrics['confidence_improvement'] }}%</span>
            </div>
        </div>
    @endif
</div>
