{{-- Analytics Dashboard with D3.js Charts --}}
<div dusk="analytics-panel" x-data="epredmetCharts">
    {{-- Filter --}}
    <div class="flex flex-wrap gap-3 mb-3">
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Year</label>
            <select wire:model.live="analyticsYear" class="dt-input epw-input" dusk="analytics-year">
                <option value="0">All Years ({{ \App\Http\Livewire\EpredmetWidget::FULL_SYNC_START_YEAR }}-{{ date('Y') }})</option>
                @for($y = date('Y'); $y >= \App\Http\Livewire\EpredmetWidget::FULL_SYNC_START_YEAR; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="loadAnalytics" class="btn-secondary epw-btn" dusk="refresh-analytics"
                    wire:loading.attr="disabled" wire:target="loadAnalytics">
                <span wire:loading wire:target="loadAnalytics" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                <svg wire:loading.remove wire:target="loadAnalytics" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
            <button type="button"
                    @click="renderAllCharts()"
                    class="btn-secondary epw-btn"
                    x-show="Object.values(chartErrors || {}).some(e => e)"
                    x-cloak
                    dusk="retry-all-charts">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Retry Charts
            </button>
        </div>
    </div>

    {{-- Loading State --}}
    <div wire:loading wire:target="loadAnalytics" class="card epw-card mb-3">
        <div style="display:flex; align-items:center; gap:.5rem; color: var(--muted);">
            <span class="epw-spinner"></span>
            <span>Loading analytics data...</span>
        </div>
    </div>

    @if(!empty($analytics['error'] ?? null))
        <div class="alert alert-error" style="padding:.5rem;" dusk="analytics-error">
            <div class="font-semibold mb-1">Error loading analytics</div>
            <div>{{ $analytics['error'] }}</div>
        </div>
    @endif

    @if(!empty($analytics['volume'] ?? null))
        <div wire:loading.class="opacity-50" wire:target="loadAnalytics">
            {{-- Scope badge --}}
            <div class="mb-2 text-xs muted">
                Scope: <span class="font-semibold" style="color: var(--fg)">{{ $analytics['year_label'] ?? $analyticsYear }}</span>
                @if($analytics['all_years'] ?? false)
                    <span class="epw-badge ml-1" style="background:rgba(59,130,246,0.15); color:#3b82f6;">AGGREGATE</span>
                @endif
            </div>

            {{-- Argument Strength Meter --}}
            @if(!empty($analytics['argument_scores']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Defense Argument Strength</div>
                    <div class="grid gap-2 md:grid-cols-4 mb-2">
                        <div class="card epw-card text-center md:col-span-1" style="border:2px solid {{ ($analytics['composite_score'] ?? 0) >= 75 ? 'rgba(34,197,94,0.4)' : (($analytics['composite_score'] ?? 0) >= 50 ? 'rgba(234,179,8,0.4)' : 'rgba(107,114,128,0.4)') }};">
                            <div class="text-[10px] uppercase muted">Overall Strength</div>
                            <div class="font-bold text-2xl" style="color:{{ ($analytics['composite_score'] ?? 0) >= 75 ? '#22c55e' : (($analytics['composite_score'] ?? 0) >= 50 ? '#eab308' : '#6b7280') }}">{{ $analytics['composite_score'] ?? 0 }}</div>
                            <div class="text-[10px] font-semibold" style="color:{{ ($analytics['composite_score'] ?? 0) >= 75 ? '#22c55e' : (($analytics['composite_score'] ?? 0) >= 50 ? '#eab308' : '#6b7280') }}">
                                {{ ($analytics['composite_score'] ?? 0) >= 75 ? 'STRONG' : (($analytics['composite_score'] ?? 0) >= 50 ? 'MODERATE' : 'DEVELOPING') }}
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <div id="chart-argument-scores" style="width:100%; height:110px;" dusk="chart-argument-scores"></div>
                        </div>
                    </div>
                    <div class="grid gap-1" style="max-height:200px; overflow-y:auto;">
                        @foreach($analytics['argument_scores'] as $key => $as)
                            <div class="flex items-center gap-2 px-2 py-1 rounded" style="background:rgba(255,255,255,0.02);">
                                <div class="epw-progress" style="width:60px; height:6px;">
                                    <div class="epw-progress-bar" style="width:{{ $as['score'] }}%; background:{{ $as['score'] >= 75 ? '#22c55e' : ($as['score'] >= 50 ? '#eab308' : '#6b7280') }};"></div>
                                </div>
                                <span class="text-xs font-medium" style="min-width:24px; color:{{ $as['score'] >= 75 ? '#22c55e' : ($as['score'] >= 50 ? '#eab308' : '#6b7280') }}">{{ $as['score'] }}</span>
                                <span class="text-xs font-medium" style="color: var(--fg)">{{ $as['label'] }}</span>
                                <span class="text-[10px] muted">{{ $as['article'] }}</span>
                                <span class="text-[10px] muted ml-auto">{{ $as['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 1. Volume Overview --}}
            <div class="mb-3">
                <div class="text-xs font-semibold muted mb-2 uppercase">Volume Overview</div>
                <div class="grid gap-2 md:grid-cols-6">
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Total Cases</div>
                        <div class="font-semibold text-lg" style="color: var(--fg)">{{ number_format($analytics['volume']['total_cases']) }}</div>
                    </div>
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Search Warrants</div>
                        <div class="font-semibold text-lg" style="color: #eab308">{{ number_format($analytics['volume']['total_warrants']) }}</div>
                        <div class="text-[10px] muted">{{ $analytics['volume']['daily_rate'] }}/day, {{ $analytics['volume']['weekly_rate'] }}/week</div>
                    </div>
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Same-Day</div>
                        <div class="font-semibold text-lg" style="color: {{ $analytics['volume']['same_day_pct'] > 80 ? '#ef4444' : '#eab308' }}">{{ $analytics['volume']['same_day_pct'] }}%</div>
                        <div class="text-[10px] muted">{{ number_format($analytics['volume']['same_day']) }} cases</div>
                    </div>
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Weekend</div>
                        <div class="font-semibold text-lg" style="color: {{ $analytics['volume']['weekend_pct'] > 15 ? '#ef4444' : 'var(--fg)' }}">{{ $analytics['volume']['weekend_pct'] }}%</div>
                        <div class="text-[10px] muted">{{ number_format($analytics['volume']['weekend']) }} cases</div>
                    </div>
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Avg Processing</div>
                        <div class="font-semibold text-lg" style="color: {{ $analytics['volume']['avg_processing_days'] < 1 ? '#ef4444' : 'var(--fg)' }}">{{ $analytics['volume']['avg_processing_days'] }}d</div>
                    </div>
                    <div class="card epw-card text-center">
                        <div class="text-[10px] uppercase muted">Per 100k pop.</div>
                        <div class="font-semibold text-lg" style="color: {{ ($analytics['volume']['per_100k'] ?? 0) > 50 ? '#ef4444' : 'var(--fg)' }}">{{ $analytics['volume']['per_100k'] ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>

            {{-- Alerts --}}
            @if($analytics['volume']['same_day_pct'] > 80)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-danger" style="font-size:.85rem;">
                    <span class="font-semibold">{{ $analytics['volume']['same_day_pct'] }}% same-day approval rate</span>
                    <span class="muted ml-1">- indicates minimal judicial review (rubber-stamp pattern)</span>
                </div>
            @endif
            @if(collect($analytics['police_units'] ?? [])->where('is_soko', true)->sum('cases') > 0)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-danger" style="font-size:.85rem;">
                    <span class="font-semibold">SOKO using misdemeanor path</span>
                    <span class="muted ml-1">- organized crime unit submitting via misdemeanor procedure</span>
                </div>
            @endif
            @if(collect($analytics['rejection_rate'] ?? [])->where('is_zero_rejection', true)->count() > 0)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-warning" style="font-size:.85rem;">
                    <span class="font-semibold">{{ collect($analytics['rejection_rate'] ?? [])->where('is_zero_rejection', true)->count() }} courts with 0% rejection rate</span>
                    <span class="muted ml-1">- no judicial pushback on any request (expected: 5-10%)</span>
                </div>
            @endif

            {{-- CHARTS ROW 1: Trend + Donut --}}
            <div class="grid gap-3 md:grid-cols-3 mb-3">
                <div class="md:col-span-2 card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">2019-Present Yearly Suspicious Warrant Trend: Home Search Count Per Year</div>
                    <div id="chart-yearly-trend" style="width:100%; height:220px;" dusk="chart-yearly-trend"></div>
                </div>
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Same-Day Ratio</div>
                    <div id="chart-same-day-donut" style="width:100%; height:220px;" dusk="chart-same-day-donut"></div>
                </div>
            </div>

            {{-- CHARTS ROW 2: Processing Distribution + Regional --}}
            <div class="grid gap-3 md:grid-cols-2 mb-3">
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Processing Time Distribution</div>
                    <div id="chart-processing-dist" style="width:100%; height:200px;" dusk="chart-processing-dist"></div>
                </div>
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Regional Per Capita (per 100k)</div>
                    <div id="chart-regional-bar" style="width:100%; height:200px;" dusk="chart-regional-bar"></div>
                </div>
            </div>

            {{-- CHARTS ROW 3: Court bar + HHI --}}
            <div class="grid gap-3 md:grid-cols-2 mb-3">
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Top Courts by Warrants</div>
                    <div id="chart-court-bar" style="width:100%; height:200px;" dusk="chart-court-bar"></div>
                </div>
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Judge Concentration HHI per Court</div>
                    <div id="chart-hhi-bar" style="width:100%; height:200px;" dusk="chart-hhi-bar"></div>
                </div>
            </div>

            {{-- 8. Rejection Rate --}}
            @if(!empty($analytics['rejection_rate']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Rejection Rate (lowest first)</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-rejection-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5 text-right">Total</th>
                                <th class="px-2 py-1.5 text-right">Approved</th>
                                <th class="px-2 py-1.5 text-right">Rejected</th>
                                <th class="px-2 py-1.5 text-right">Rejection %</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['rejection_rate'] as $rr)
                                <tr style="border-top:1px solid var(--border)" class="{{ $rr['is_zero_rejection'] ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $rr['court'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($rr['total']) }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($rr['approved']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $rr['rejected'] === 0 ? '#ef4444' : 'var(--fg)' }}">{{ $rr['rejected'] }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $rr['rejection_pct'] < 1 ? '#ef4444' : 'var(--fg)' }}">{{ $rr['rejection_pct'] }}%</td>
                                    <td class="px-2 py-1.5">
                                        @if($rr['is_zero_rejection'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">0% REJECTED</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">Expected: 5-10% rejection for meaningful judicial oversight.</div>
                    </div>
                </div>
            @endif

            {{-- 3b. HHI Concentration --}}
            @if(!empty($analytics['hhi_per_court']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Judge Concentration HHI per Court</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-hhi-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5 text-right">Judges</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Max Share</th>
                                <th class="px-2 py-1.5 text-right">HHI</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['hhi_per_court'] as $hhi)
                                <tr style="border-top:1px solid var(--border)" class="{{ $hhi['flag'] !== '' ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $hhi['court'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ $hhi['judges'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($hhi['warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ $hhi['max_share_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:{{ $hhi['hhi'] > 5000 ? '#ef4444' : ($hhi['hhi'] > 2500 ? '#eab308' : 'var(--fg)') }}">{{ number_format($hhi['hhi']) }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($hhi['flag'] === 'EXTREME')
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">EXTREME</span>
                                        @elseif($hhi['flag'] === 'HIGH')
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">HIGH</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">HHI: &lt;2500=normal, 2500-5000=concentrated, &gt;5000=extreme (6 judges normal = ~1667)</div>
                    </div>
                </div>
            @endif

            {{-- 9. Regional disproportion --}}
            @if(!empty($analytics['regional_per_capita']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Regional Disproportion (Per 100k Population)</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-regional-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5">County</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Population</th>
                                <th class="px-2 py-1.5 text-right">Per 100k</th>
                                <th class="px-2 py-1.5">vs National</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $nationalPer100k = $analytics['volume']['per_100k'] ?? 1; @endphp
                            @foreach($analytics['regional_per_capita'] as $r)
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-2 py-1.5 font-medium">{{ $r['court'] }}</td>
                                    <td class="px-2 py-1.5">{{ $r['county'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($r['warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($r['population']) }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:{{ $r['per_100k'] > 50 ? '#ef4444' : 'var(--fg)' }}">{{ $r['per_100k'] }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($nationalPer100k > 0)
                                            <span style="color:{{ round($r['per_100k'] / $nationalPer100k, 1) > 2 ? '#ef4444' : 'var(--fg)' }}">
                                                {{ round($r['per_100k'] / max($nationalPer100k, 0.1), 1) }}x
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 2. Top Courts --}}
            @if(!empty($analytics['top_courts']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Top Courts by Warrants</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-courts-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Share</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">Avg Days</th>
                                <th class="px-2 py-1.5 text-right">Per 100k</th>
                                <th class="px-2 py-1.5" style="min-width:80px;">Bar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $maxWarrants = collect($analytics['top_courts'])->max('warrants') ?: 1; @endphp
                            @foreach($analytics['top_courts'] as $court)
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-2 py-1.5 font-medium">{{ $court['court'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($court['warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ $court['share_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $court['same_day_pct'] > 90 ? '#ef4444' : ($court['same_day_pct'] > 70 ? '#eab308' : 'var(--fg)') }}">{{ $court['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right">{{ $court['avg_days'] }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ ($court['per_100k'] ?? 0) > 50 ? '#ef4444' : 'var(--fg)' }}">{{ $court['per_100k'] ?? '-' }}</td>
                                    <td class="px-2 py-1.5">
                                        <div class="epw-progress">
                                            <div class="epw-progress-bar" style="width:{{ round(100 * $court['warrants'] / $maxWarrants) }}%; background:#eab308;"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 3. Top Judges --}}
            @if(!empty($analytics['top_judges']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Top Judges by Warrant Volume</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-judges-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Judge</th>
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Concentration</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">0-Day %</th>
                                <th class="px-2 py-1.5 text-right">Avg Days</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['top_judges'] as $judge)
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-2 py-1.5 font-medium">{{ Str::limit($judge['judge'], 25) }}</td>
                                    <td class="px-2 py-1.5">{{ $judge['court'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($judge['total']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $judge['concentration_pct'] > 50 ? '#ef4444' : 'var(--fg)' }}">{{ $judge['concentration_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $judge['same_day_pct'] > 90 ? '#ef4444' : 'var(--fg)' }}">{{ $judge['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ ($judge['zero_day_pct'] ?? 0) > 90 ? '#ef4444' : 'var(--fg)' }}">{{ $judge['zero_day_pct'] ?? '-' }}%</td>
                                    <td class="px-2 py-1.5 text-right">{{ $judge['avg_days'] ?? '-' }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($judge['concentration_pct'] > 75)
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">EXTREME</span>
                                        @elseif($judge['concentration_pct'] > 50)
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">HIGH</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="grid gap-3 md:grid-cols-2 mb-3">
                {{-- 4. Police Units --}}
                @if(!empty($analytics['police_units']))
                    <div>
                        <div class="text-xs font-semibold muted mb-2 uppercase">Police Units (Request Submitters)</div>
                        <div class="card" style="padding:0; border-radius:1rem;">
                            <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-police-table">
                                <thead style="background: var(--bg)">
                                <tr class="text-left" style="color: var(--muted)">
                                    <th class="px-2 py-1.5">Unit</th>
                                    <th class="px-2 py-1.5 text-right">Cases</th>
                                    <th class="px-2 py-1.5">Flag</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($analytics['police_units'] as $unit)
                                    <tr style="border-top:1px solid var(--border)" class="{{ $unit['is_soko'] ? 'epw-row-highlight' : '' }}">
                                        <td class="px-2 py-1.5 font-medium" style="{{ $unit['is_soko'] ? 'color:#ef4444;' : '' }}">{{ $unit['unit'] }}</td>
                                        <td class="px-2 py-1.5 text-right">{{ number_format($unit['cases']) }}</td>
                                        <td class="px-2 py-1.5">
                                            @if($unit['is_soko'])
                                                <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">SHOULD USE CRIMINAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- 5. Pismena Types --}}
                @if(!empty($analytics['pismena_types']))
                    <div>
                        <div class="text-xs font-semibold muted mb-2 uppercase">Document Types in Search Warrant Cases</div>
                        <div class="card" style="padding:0; border-radius:1rem; max-height:300px; overflow-y:auto;">
                            <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-pismena-table">
                                <thead style="background: var(--bg); position:sticky; top:0;">
                                <tr class="text-left" style="color: var(--muted)">
                                    <th class="px-2 py-1.5">Vrsta (Kind)</th>
                                    <th class="px-2 py-1.5 text-right">Count</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($analytics['pismena_types'] as $pt)
                                    @php $isUvid = str_contains(strtolower($pt['kind']), 'uvid'); @endphp
                                    <tr style="border-top:1px solid var(--border)" class="{{ $isUvid ? 'epw-row-highlight' : '' }}">
                                        <td class="px-2 py-1.5">
                                            {{ $pt['kind'] }}
                                            @if($isUvid)
                                                <span class="epw-badge ml-1" style="background:rgba(34,197,94,0.15); color:#22c55e;">UVID</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5 text-right font-medium">{{ number_format($pt['total']) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- 6. Zahtjev za uvid u spis --}}
            @if(($analytics['uvid_u_spis_total'] ?? 0) > 0)
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">
                        Zahtjev za uvid u spis
                        <span class="font-normal ml-1">({{ $analytics['uvid_u_spis_total'] }} total across {{ $analytics['uvid_u_spis_cases'] }} cases)</span>
                    </div>
                    <div class="card" style="padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-uvid-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Submitter</th>
                                <th class="px-2 py-1.5 text-right">Count</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['uvid_u_spis'] as $uvid)
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-2 py-1.5 font-medium">{{ $uvid['submitter'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ $uvid['count'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 7. Yearly Trend Table --}}
            @if(!empty($analytics['yearly_trend']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Yearly Trend</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-trend-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Year</th>
                                <th class="px-2 py-1.5 text-right">Total Cases</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">Weekend %</th>
                                <th class="px-2 py-1.5 text-right">Avg Days</th>
                                <th class="px-2 py-1.5">Change</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['yearly_trend'] as $index => $trend)
                                @php
                                    $prev = $index > 0 ? $analytics['yearly_trend'][$index - 1]['warrants'] : null;
                                    $change = $prev ? round(100 * ($trend['warrants'] - $prev) / max($prev, 1), 0) : null;
                                @endphp
                                <tr style="border-top:1px solid var(--border)" class="{{ ($analyticsYear > 0 && $trend['year'] == $analyticsYear) ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $trend['year'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($trend['total_cases']) }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:#eab308;">{{ number_format($trend['warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $trend['same_day_pct'] > 80 ? '#ef4444' : 'var(--fg)' }}">{{ $trend['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ ($trend['weekend_pct'] ?? 0) > 30 ? '#ef4444' : 'var(--fg)' }}">{{ $trend['weekend_pct'] ?? '-' }}%</td>
                                    <td class="px-2 py-1.5 text-right">{{ $trend['avg_days'] }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($change !== null)
                                            <span style="color:{{ $change > 0 ? '#ef4444' : ($change < 0 ? '#22c55e' : 'var(--muted)') }}">
                                                {{ $change > 0 ? '+' : '' }}{{ $change }}%
                                                @if($change > 0) &#8593; @elseif($change < 0) &#8595; @else &#8594; @endif
                                            </span>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 10. Processing distribution table --}}
            @if(!empty($analytics['processing_distribution']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Processing Time Distribution</div>
                    <div class="card" style="padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Time Bucket</th>
                                <th class="px-2 py-1.5 text-right">Cases</th>
                                <th class="px-2 py-1.5" style="min-width:120px;">Distribution</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $maxBucket = collect($analytics['processing_distribution'])->max('count') ?: 1; @endphp
                            @foreach($analytics['processing_distribution'] as $pd)
                                <tr style="border-top:1px solid var(--border)" class="{{ str_contains($pd['bucket'], 'same day') ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $pd['bucket'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($pd['count']) }}</td>
                                    <td class="px-2 py-1.5">
                                        <div class="epw-progress">
                                            <div class="epw-progress-bar" style="width:{{ round(100 * $pd['count'] / $maxBucket) }}%; background:{{ str_contains($pd['bucket'], 'same day') ? '#ef4444' : '#3b82f6' }};"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- DEFENSE ARGUMENT ANALYTICS --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mb-3 mt-4 px-3 py-2 rounded-lg" style="background:rgba(139,92,246,0.06); border:1px solid rgba(139,92,246,0.2);">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5" style="color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="font-semibold" style="color:#8b5cf6;">Defense Argument Analytics</span>
                    <span class="text-xs muted ml-2">ECHR Art. 6 &amp; 8 — Evidence of systemic failures</span>
                </div>
            </div>

            {{-- Defense Alerts --}}
            @if(collect($analytics['judge_profiles'] ?? [])->where('is_rubber_stamp', true)->count() > 0)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-danger" style="font-size:.85rem;">
                    <span class="font-semibold">{{ collect($analytics['judge_profiles'] ?? [])->where('is_rubber_stamp', true)->count() }} rubber-stamp judge(s) detected</span>
                    <span class="muted ml-1">- 50+ warrants, 90%+ same-day, 0 rejections (no individualized assessment)</span>
                </div>
            @endif
            @if(collect($analytics['workload_impossibility'] ?? [])->where('is_impossible', true)->count() > 0)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-danger" style="font-size:.85rem;">
                    <span class="font-semibold">{{ collect($analytics['workload_impossibility'] ?? [])->where('is_impossible', true)->count() }} day(s) with physically impossible workload</span>
                    <span class="muted ml-1">- 10+ warrants issued by same judge in single day</span>
                </div>
            @endif
            @if(($analytics['document_completeness']['decision_no_request'] ?? 0) > 0)
                <div class="mb-2 px-3 py-2 rounded-lg epw-highlight-warning" style="font-size:.85rem;">
                    <span class="font-semibold">{{ $analytics['document_completeness']['decision_no_request'] }} warrant(s) issued without formal request</span>
                    <span class="muted ml-1">- court order exists but no police request on file</span>
                </div>
            @endif

            {{-- 15. Proportionality Index --}}
            @if(!empty($analytics['proportionality']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Proportionality Index (Art. 8 ECHR)</div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Misdemeanor Cases</div>
                            <div class="font-semibold text-lg" style="color: var(--fg)">{{ number_format($analytics['proportionality']['total_misdemeanor']) }}</div>
                            <div class="text-[10px] muted">Pp Prz register</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Home Searches</div>
                            <div class="font-semibold text-lg" style="color:#ef4444;">{{ number_format($analytics['proportionality']['warrants_in_misdemeanor']) }}</div>
                            <div class="text-[10px] muted">{{ $analytics['proportionality']['warrants_pct'] }}% of all misdemeanor</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Urgent Processing</div>
                            <div class="font-semibold text-lg" style="color:#eab308;">{{ number_format($analytics['proportionality']['urgent_count']) }}</div>
                            <div class="text-[10px] muted">{{ $analytics['proportionality']['urgent_pct'] }}% same-day or weekend</div>
                        </div>
                        <div class="card epw-card text-center" style="border:1px solid rgba(139,92,246,0.3);">
                            <div class="text-[10px] uppercase muted">Argument</div>
                            <div class="text-xs mt-1" style="color:#8b5cf6;">Maximum invasiveness (home search) for minimum sanctions (fines)</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- CHARTS ROW 4: Judge Profile + Police Approval --}}
            <div class="grid gap-3 md:grid-cols-2 mb-3">
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Judge Warrant Volume (Rubber-Stamp Detection)</div>
                    <div id="chart-judge-profile" style="width:100%; height:220px;" dusk="chart-judge-profile"></div>
                </div>
                <div class="card" style="border-radius:1rem; padding:.75rem;">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Police Unit Approval Rate</div>
                    <div id="chart-police-approval" style="width:100%; height:220px;" dusk="chart-police-approval"></div>
                </div>
            </div>

            {{-- 11. Judge Rubber-Stamp Profiles --}}
            @if(!empty($analytics['judge_profiles']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Judge Rubber-Stamp Profiles</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-judge-profiles-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Judge</th>
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">0-Day %</th>
                                <th class="px-2 py-1.5 text-right">Rejections</th>
                                <th class="px-2 py-1.5 text-right">Avg Days</th>
                                <th class="px-2 py-1.5">Period</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['judge_profiles'] as $jp)
                                <tr style="border-top:1px solid var(--border)" class="{{ $jp['is_rubber_stamp'] ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ Str::limit($jp['judge'], 25) }}</td>
                                    <td class="px-2 py-1.5">{{ $jp['court'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium">{{ number_format($jp['total_warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $jp['same_day_pct'] > 90 ? '#ef4444' : ($jp['same_day_pct'] > 70 ? '#eab308' : 'var(--fg)') }}">{{ $jp['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $jp['zero_day_pct'] > 90 ? '#ef4444' : 'var(--fg)' }}">{{ $jp['zero_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $jp['rejections'] === 0 ? '#ef4444' : 'var(--fg)' }}">{{ $jp['rejections'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ $jp['avg_days'] }}</td>
                                    <td class="px-2 py-1.5 text-xs muted">
                                        @if($jp['first_warrant'] && $jp['last_warrant'])
                                            {{ \Carbon\Carbon::parse($jp['first_warrant'])->format('m/Y') }} - {{ \Carbon\Carbon::parse($jp['last_warrant'])->format('m/Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5">
                                        @if($jp['is_rubber_stamp'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">RUBBER STAMP</span>
                                        @elseif($jp['same_day_pct'] > 90)
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">HIGH SAME-DAY</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">Rubber Stamp: 50+ warrants, 90%+ same-day, 0 rejections — indicates no individualized assessment (ECHR Art. 8).</div>
                    </div>
                </div>
            @endif

            {{-- 12. Workload Impossibility --}}
            @if(!empty($analytics['workload_impossibility']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Judge Workload Impossibility (5+ Warrants/Day)</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-workload-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Judge</th>
                                <th class="px-2 py-1.5">Court</th>
                                <th class="px-2 py-1.5">Date</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['workload_impossibility'] as $wl)
                                <tr style="border-top:1px solid var(--border)" class="{{ $wl['is_impossible'] ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ Str::limit($wl['judge'], 25) }}</td>
                                    <td class="px-2 py-1.5">{{ $wl['court'] }}</td>
                                    <td class="px-2 py-1.5">{{ $wl['date'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:{{ $wl['is_impossible'] ? '#ef4444' : '#eab308' }}">{{ $wl['warrants_on_day'] }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($wl['is_impossible'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">IMPOSSIBLE</span>
                                        @else
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">EXCESSIVE</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">10+ warrants/day = physically impossible to conduct individualized review of each request. ECHR Art. 8 requires specific assessment.</div>
                    </div>
                </div>
            @endif

            {{-- 13. Repeat Targeting --}}
            @if(!empty($analytics['repeat_targets']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Repeat Targeting (Same Defendant in Multiple Warrant Cases)</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-repeat-targets-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Defendant</th>
                                <th class="px-2 py-1.5 text-right">Warrant Cases</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['repeat_targets'] as $rt)
                                <tr style="border-top:1px solid var(--border)" class="{{ $rt['is_excessive'] ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $rt['name'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:{{ $rt['is_excessive'] ? '#ef4444' : '#eab308' }}">{{ $rt['case_count'] }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($rt['is_excessive'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">EXCESSIVE</span>
                                        @else
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">REPEAT</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">Multiple home search warrants against same defendant may indicate disproportionate targeting (ECHR Art. 8).</div>
                    </div>
                </div>
            @endif

            {{-- 14. Police Unit Approval Rate --}}
            @if(!empty($analytics['police_approval_rate']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Police Unit Request Approval Rate</div>
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-police-approval-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Unit</th>
                                <th class="px-2 py-1.5 text-right">Requests</th>
                                <th class="px-2 py-1.5 text-right">Approved</th>
                                <th class="px-2 py-1.5 text-right">Rejected</th>
                                <th class="px-2 py-1.5 text-right">Approval %</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">Avg Days</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['police_approval_rate'] as $pa)
                                <tr style="border-top:1px solid var(--border)" class="{{ ($pa['is_100pct'] || $pa['is_soko']) ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium" style="{{ $pa['is_soko'] ? 'color:#ef4444;' : '' }}">{{ $pa['unit'] }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($pa['total_requests']) }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($pa['approved']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $pa['rejected'] === 0 ? '#ef4444' : 'var(--fg)' }}">{{ $pa['rejected'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium" style="color:{{ $pa['approval_pct'] >= 100 ? '#ef4444' : 'var(--fg)' }}">{{ $pa['approval_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $pa['same_day_pct'] > 90 ? '#ef4444' : 'var(--fg)' }}">{{ $pa['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right">{{ $pa['avg_processing'] }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($pa['is_soko'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">SOKO</span>
                                        @endif
                                        @if($pa['is_100pct'])
                                            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308;">100% APPROVED</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">100% approval = no judicial pushback. Compare rates across units for unequal treatment (ECHR Art. 6).</div>
                    </div>
                </div>
            @endif

            {{-- 16. Document Completeness --}}
            @if(!empty($analytics['document_completeness']))
                <div class="mb-3">
                    <div class="text-xs font-semibold muted mb-2 uppercase">Document Completeness (Procedural Violations)</div>
                    <div class="grid gap-2 md:grid-cols-5">
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Warrant Cases</div>
                            <div class="font-semibold text-lg" style="color: var(--fg)">{{ number_format($analytics['document_completeness']['total_warrant_cases']) }}</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">With Request</div>
                            <div class="font-semibold text-lg" style="color:#22c55e;">{{ $analytics['document_completeness']['request_pct'] }}%</div>
                            <div class="text-[10px] muted">{{ number_format($analytics['document_completeness']['with_request']) }}</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">With Decision</div>
                            <div class="font-semibold text-lg" style="color:#3b82f6;">{{ $analytics['document_completeness']['decision_pct'] }}%</div>
                            <div class="text-[10px] muted">{{ number_format($analytics['document_completeness']['with_decision']) }}</div>
                        </div>
                        <div class="card epw-card text-center" style="{{ $analytics['document_completeness']['decision_no_request'] > 0 ? 'border:1px solid rgba(239,68,68,0.3);' : '' }}">
                            <div class="text-[10px] uppercase muted">Decision w/o Request</div>
                            <div class="font-semibold text-lg" style="color:#ef4444;">{{ number_format($analytics['document_completeness']['decision_no_request']) }}</div>
                            <div class="text-[10px] muted">procedural violation</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">No Documents</div>
                            <div class="font-semibold text-lg" style="color:{{ $analytics['document_completeness']['no_documents'] > 0 ? '#eab308' : 'var(--fg)' }}">{{ number_format($analytics['document_completeness']['no_documents']) }}</div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="epw-progress" style="height:8px;">
                            <div class="epw-progress-bar" style="width:{{ $analytics['document_completeness']['completeness_pct'] }}%; background:{{ $analytics['document_completeness']['completeness_pct'] > 80 ? '#22c55e' : ($analytics['document_completeness']['completeness_pct'] > 50 ? '#eab308' : '#ef4444') }};"></div>
                        </div>
                        <div class="text-[10px] muted mt-1">Overall completeness: {{ $analytics['document_completeness']['completeness_pct'] }}% — cases with both request and decision documents on file.</div>
                    </div>
                </div>
            @endif

            {{-- 17. Monthly Trend with Anomaly Detection --}}
            @if(!empty($analytics['monthly_trend']))
                <div class="mb-3 mt-4">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="h-4 w-4" style="color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        <span class="text-xs font-semibold muted uppercase">Monthly Trend &amp; Temporal Anomaly Detection</span>
                        @php $spikeCount = collect($analytics['monthly_trend'])->where('is_spike', true)->count(); @endphp
                        @if($spikeCount > 0)
                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">{{ $spikeCount }} SPIKE{{ $spikeCount > 1 ? 'S' : '' }}</span>
                        @endif
                    </div>
                    {{-- Monthly chart --}}
                    <div class="card mb-2" style="border-radius:1rem; padding:.75rem;">
                        <div id="chart-monthly-trend" style="width:100%; height:280px;" dusk="chart-monthly-trend"></div>
                    </div>
                    {{-- Monthly table --}}
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem; max-height:350px; overflow-y:auto;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="analytics-monthly-table">
                            <thead style="background: var(--bg); position:sticky; top:0;">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Month</th>
                                <th class="px-2 py-1.5 text-right">Warrants</th>
                                <th class="px-2 py-1.5 text-right">Same-Day %</th>
                                <th class="px-2 py-1.5 text-right">MoM Change</th>
                                <th class="px-2 py-1.5 text-right">vs 3m Avg</th>
                                <th class="px-2 py-1.5">Flag</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($analytics['monthly_trend'] as $mt)
                                <tr style="border-top:1px solid var(--border)" class="{{ $mt['is_spike'] ? 'epw-row-highlight' : '' }}">
                                    <td class="px-2 py-1.5 font-medium">{{ $mt['month'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium">{{ number_format($mt['warrants']) }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $mt['same_day_pct'] > 90 ? '#ef4444' : 'var(--fg)' }}">{{ $mt['same_day_pct'] }}%</td>
                                    <td class="px-2 py-1.5 text-right">
                                        @if($mt['mom_change'] !== null)
                                            <span style="color:{{ $mt['mom_change'] > 50 ? '#ef4444' : ($mt['mom_change'] < -40 ? '#22c55e' : 'var(--fg)') }}">
                                                {{ $mt['mom_change'] > 0 ? '+' : '' }}{{ $mt['mom_change'] }}%
                                            </span>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $mt['vs_rolling_avg'] > 2 ? '#ef4444' : 'var(--fg)' }}">{{ $mt['vs_rolling_avg'] }}x</td>
                                    <td class="px-2 py-1.5">
                                        @if($mt['is_spike'])
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">SPIKE</span>
                                        @elseif($mt['is_drop'])
                                            <span class="epw-badge" style="background:rgba(34,197,94,0.15); color:#22c55e;">DROP</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">Spike: >50% MoM increase or >2x rolling 3-month average. Reveals politically-timed enforcement surges.</div>
                    </div>
                </div>

                {{-- Per-court spike alerts --}}
                @if(!empty($analytics['court_monthly_spikes']))
                    <div class="mb-3">
                        <div class="text-xs font-semibold muted mb-2 uppercase">Per-Court Monthly Spikes (>100% MoM increase)</div>
                        @foreach($analytics['court_monthly_spikes'] as $cs)
                            <div class="mb-1 px-3 py-2 rounded-lg epw-highlight-danger" style="font-size:.85rem;">
                                <span class="font-semibold">{{ $cs['court'] }}:</span>
                                @foreach($cs['spikes'] as $spike)
                                    <span class="epw-badge ml-1" style="background:rgba(239,68,68,0.15); color:#ef4444;">
                                        {{ $spike['month'] }} — {{ $spike['warrants'] }} warrants (+{{ $spike['change'] }}%)
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Court Comparison Mode --}}
            <div class="mb-3 mt-4">
                <div class="text-xs font-semibold muted mb-2 uppercase">Court Comparison</div>
                <div class="grid gap-2 md:grid-cols-3 mb-2">
                    <div>
                        <label class="block text-[10px] muted font-medium mb-1">Court A</label>
                        <select wire:model="compareCourtA" class="dt-input epw-input w-full">
                            <option value="">-- Select --</option>
                            @foreach(\App\Models\Court::where('level', 1)->orderBy('name')->get() as $c)
                                <option value="{{ $c->id }}">{{ $c->short_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] muted font-medium mb-1">Court B</label>
                        <select wire:model="compareCourtB" class="dt-input epw-input w-full">
                            <option value="">-- Select --</option>
                            @foreach(\App\Models\Court::where('level', 1)->orderBy('name')->get() as $c)
                                <option value="{{ $c->id }}">{{ $c->short_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" wire:click="loadComparison" class="btn-secondary epw-btn"
                                wire:loading.attr="disabled" wire:target="loadComparison"
                                @if(!$compareCourtA || !$compareCourtB) disabled @endif>
                            <span wire:loading wire:target="loadComparison" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                            Compare
                        </button>
                    </div>
                </div>

                @if(!empty($comparisonData['courts'] ?? []))
                    @php $ca = $comparisonData['courts'][0]; $cb = $comparisonData['courts'][1]; $nat = $comparisonData['national']; @endphp
                    <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
                        <table class="min-w-full epw-table" style="color: var(--fg)" dusk="comparison-table">
                            <thead style="background: var(--bg)">
                            <tr class="text-left" style="color: var(--muted)">
                                <th class="px-2 py-1.5">Metric</th>
                                <th class="px-2 py-1.5 text-right">{{ $ca['court'] }}</th>
                                <th class="px-2 py-1.5 text-right">{{ $cb['court'] }}</th>
                                <th class="px-2 py-1.5 text-right">National</th>
                                <th class="px-2 py-1.5">Worse</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $metrics = [
                                    ['label' => 'Warrants', 'a' => $ca['warrants'], 'b' => $cb['warrants'], 'nat' => '-', 'higher_worse' => true, 'suffix' => ''],
                                    ['label' => 'Same-Day %', 'a' => $ca['same_day_pct'], 'b' => $cb['same_day_pct'], 'nat' => $nat['same_day_pct'], 'higher_worse' => true, 'suffix' => '%'],
                                    ['label' => 'Weekend %', 'a' => $ca['weekend_pct'], 'b' => $cb['weekend_pct'], 'nat' => $nat['weekend_pct'], 'higher_worse' => true, 'suffix' => '%'],
                                    ['label' => 'Rejection %', 'a' => $ca['rejection_pct'], 'b' => $cb['rejection_pct'], 'nat' => '-', 'higher_worse' => false, 'suffix' => '%'],
                                    ['label' => 'Avg Days', 'a' => $ca['avg_days'], 'b' => $cb['avg_days'], 'nat' => $nat['avg_days'], 'higher_worse' => false, 'suffix' => ''],
                                    ['label' => 'HHI', 'a' => $ca['hhi'], 'b' => $cb['hhi'], 'nat' => '-', 'higher_worse' => true, 'suffix' => ''],
                                    ['label' => 'Per 100k', 'a' => $ca['per_100k'] ?? '-', 'b' => $cb['per_100k'] ?? '-', 'nat' => '-', 'higher_worse' => true, 'suffix' => ''],
                                ];
                            @endphp
                            @foreach($metrics as $m)
                                @php
                                    $aVal = is_numeric($m['a']) ? $m['a'] : 0;
                                    $bVal = is_numeric($m['b']) ? $m['b'] : 0;
                                    $worse = $m['higher_worse'] ? ($aVal >= $bVal ? 'A' : 'B') : ($aVal <= $bVal ? 'A' : 'B');
                                    if ($aVal == $bVal) $worse = '-';
                                @endphp
                                <tr style="border-top:1px solid var(--border)">
                                    <td class="px-2 py-1.5 font-medium">{{ $m['label'] }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $worse === 'A' ? '#ef4444' : 'var(--fg)' }}; font-weight:{{ $worse === 'A' ? '600' : '400' }}">{{ $m['a'] }}{{ $m['suffix'] }}</td>
                                    <td class="px-2 py-1.5 text-right" style="color:{{ $worse === 'B' ? '#ef4444' : 'var(--fg)' }}; font-weight:{{ $worse === 'B' ? '600' : '400' }}">{{ $m['b'] }}{{ $m['suffix'] }}</td>
                                    <td class="px-2 py-1.5 text-right muted">{{ $m['nat'] }}{{ is_numeric($m['nat']) ? $m['suffix'] : '' }}</td>
                                    <td class="px-2 py-1.5">
                                        @if($worse === 'A')
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">{{ $ca['court'] }}</span>
                                        @elseif($worse === 'B')
                                            <span class="epw-badge" style="background:rgba(239,68,68,0.15); color:#ef4444;">{{ $cb['court'] }}</span>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-2 py-1.5 text-[10px] muted">Red = worse performing court on that metric. Use side-by-side to demonstrate deviation from peer courts.</div>
                    </div>
                @endif
            </div>
        </div>
    @elseif(empty($analytics['error'] ?? null) && empty($analytics))
        <div class="card epw-card text-center muted" dusk="no-analytics">
            Click Refresh to load analytics data for {{ $analyticsYear === 0 ? 'all years' : $analyticsYear }}
        </div>
    @endif
</div>
