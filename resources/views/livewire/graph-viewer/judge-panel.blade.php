<div dusk="judge-panel" class="space-y-4">
    <!-- Loading State -->
    <div x-show="$wire.loading" class="flex items-center justify-center py-8">
        <div class="flex items-center gap-3">
            <svg class="animate-spin h-8 w-8" style="color: var(--accent);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium" style="color: var(--muted);">Loading judge data...</span>
        </div>
    </div>

    <!-- Error State -->
    @if($error && !$loading)
        <div class="rounded-lg p-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" style="color: #ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold mb-1" style="color: #ef4444;">Error Loading Judge Data</h4>
                    <p class="text-sm" style="color: #fca5a5;">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Empty State (No Data Available) -->
    @if(!$judgeData && !$loading && !$error)
        <div class="rounded-lg p-8 text-center" style="background: rgba(107, 114, 128, 0.1); border: 1px dashed rgba(107, 114, 128, 0.3);">
            <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h4 class="text-sm font-semibold mb-1" style="color: var(--muted);">No Data Available</h4>
            <p class="text-xs" style="color: var(--muted);">Judge data is not available at this time.</p>
        </div>
    @endif

    <!-- Judge Data Display -->
    @if($judgeData && !$loading)
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold" style="color: var(--accent);">Judge Statistics</h3>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid md:grid-cols-3 gap-4">
            <!-- Case Count -->
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Total Cases</div>
                    <div dusk="case-count-value" class="text-3xl font-bold" style="color: var(--accent);">
                        {{ $judgeData['caseCount'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Average Case Duration -->
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Avg. Case Duration</div>
                    <div dusk="avg-duration-value" class="text-3xl font-bold" style="color: #10b981;">
                        {{ number_format($judgeData['avgCaseDuration'] ?? 0, 1) }}
                        <span class="text-sm font-normal" style="color: var(--muted);">days</span>
                    </div>
                </div>
            </div>

            <!-- Total Rulings -->
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-xs font-semibold mb-2" style="color: var(--muted);">Ruling Types</div>
                    <div dusk="ruling-types-value" class="text-3xl font-bold" style="color: #3b82f6;">
                        {{ is_array($judgeData['rulingDistribution']) ? count($judgeData['rulingDistribution']) : 0 }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Ruling Distribution Details -->
        @if(is_array($judgeData['rulingDistribution']) && count($judgeData['rulingDistribution']) > 0)
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="text-sm font-semibold" style="color: var(--fg);">Ruling Distribution</h4>
                </div>
                <div class="card-body">
                    <dl class="grid md:grid-cols-2 gap-4">
                        @foreach($judgeData['rulingDistribution'] as $outcome => $count)
                            <div>
                                <dt class="text-sm font-medium mb-1" style="color: var(--muted);">
                                    {{ str_replace('_', ' ', ucwords($outcome, '_')) }}
                                </dt>
                                <dd style="color: var(--fg);">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-slate-700 rounded-full h-2 overflow-hidden">
                                            <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500"
                                                 style="width: {{ ($judgeData['caseCount'] > 0) ? min(100, ($count / $judgeData['caseCount']) * 100) : 0 }}%"></div>
                                        </div>
                                        <span class="text-lg font-bold" style="color: var(--accent);">
                                            {{ $count }}
                                        </span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--muted);">
                                        {{ $judgeData['caseCount'] > 0 ? number_format(($count / $judgeData['caseCount']) * 100, 1) : 0 }}% of total cases
                                    </p>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif
    @endif
</div>
