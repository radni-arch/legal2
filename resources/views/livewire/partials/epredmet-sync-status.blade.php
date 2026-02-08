{{-- Sync Status Dashboard --}}
@php
    $hasRunning = collect($syncLogs)->where('status', 'running')->count() > 0;
    $courtsProcessed = $syncSummary['courts_completed'] + $syncSummary['courts_failed'];
    $courtsTotal = $syncSummary['courts_total'] ?: 1;
    $courtsInProgress = $syncSummary['courts_running'] + $syncSummary['courts_pending'];
    $overallProgress = $courtsTotal > 0 ? round(100 * $courtsProcessed / $courtsTotal) : 0;
@endphp

<div dusk="sync-status-panel" @if($hasRunning || $pollingEnabled) wire:poll.5s="loadSyncStatus" @endif>
    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-3">
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Year</label>
            <select wire:model.live="filterYear" class="dt-input epw-input" dusk="filter-year">
                <option value="0">All Years</option>
                @for($y = date('Y'); $y >= \App\Http\Livewire\EpredmetWidget::FULL_SYNC_START_YEAR; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Register</label>
            <select wire:model.live="filterRegister" class="dt-input epw-input" dusk="filter-register">
                <option value="Pp Prz">Pp Prz</option>
                <option value="Pp">Pp</option>
                <option value="K">K</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="loadSyncStatus" class="btn-secondary epw-btn" dusk="refresh-sync-status"
                    wire:loading.attr="disabled" wire:target="loadSyncStatus">
                <span wire:loading wire:target="loadSyncStatus" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                <svg wire:loading.remove wire:target="loadSyncStatus" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- Overall Progress Bar --}}
    @if(count($syncLogs) > 0)
        <div class="mb-3 card epw-card">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-medium" style="color: var(--fg)">Overall Progress</span>
                <span class="text-xs muted">
                    {{ $courtsProcessed }}/{{ $courtsTotal }} courts
                    @if($courtsInProgress > 0)
                        <span class="text-yellow-500">({{ $courtsInProgress }} in progress)</span>
                    @endif
                </span>
            </div>
            <div class="epw-progress">
                <div class="epw-progress-bar" style="width:{{ $overallProgress }}%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
            </div>
            <div class="flex justify-between mt-1">
                <span class="text-[10px] muted">{{ $overallProgress }}% complete</span>
                @if($hasRunning)
                    <span class="text-[10px] text-yellow-500 flex items-center gap-1">
                        <span class="inline-block w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                        Syncing...
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid gap-2 md:grid-cols-4 mb-3">
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Fetched</div>
            <div class="font-semibold text-lg" style="color: var(--fg)" dusk="summary-total-fetched">{{ number_format($syncSummary['total_fetched']) }}</div>
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Saved</div>
            <div class="font-semibold text-lg" style="color: #22c55e" dusk="summary-total-saved">{{ number_format($syncSummary['total_saved']) }}</div>
            @if($syncSummary['total_fetched'] > 0)
                <div class="text-[10px] muted">{{ round(100 * $syncSummary['total_saved'] / $syncSummary['total_fetched'], 1) }}% save rate</div>
            @endif
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Errors</div>
            <div class="font-semibold text-lg" style="color: {{ $syncSummary['total_errors'] > 0 ? '#ef4444' : 'var(--fg)' }}" dusk="summary-total-errors">{{ number_format($syncSummary['total_errors']) }}</div>
            @if($syncSummary['total_fetched'] > 0 && $syncSummary['total_errors'] > 0)
                <div class="text-[10px]" style="color:#ef4444;">{{ round(100 * $syncSummary['total_errors'] / $syncSummary['total_fetched'], 1) }}% error rate</div>
            @endif
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Courts Status</div>
            <div class="flex gap-2 text-xs mt-1 flex-wrap">
                <span class="text-green-500" dusk="summary-courts-completed" title="Completed">{{ $syncSummary['courts_completed'] }} done</span>
                @if($syncSummary['courts_running'] > 0)
                    <span class="text-yellow-500 flex items-center gap-0.5" dusk="summary-courts-running" title="Running">
                        <span class="inline-block w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                        {{ $syncSummary['courts_running'] }} running
                    </span>
                @endif
                @if($syncSummary['courts_pending'] > 0)
                    <span class="text-gray-400" title="Pending">{{ $syncSummary['courts_pending'] }} pending</span>
                @endif
                @if($syncSummary['courts_failed'] > 0)
                    <span class="text-red-500" dusk="summary-courts-failed" title="Failed">{{ $syncSummary['courts_failed'] }} failed</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Sync Logs Table --}}
    @if(count($syncLogs) > 0)
        <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
            <table class="min-w-full epw-table" style="color: var(--fg)" dusk="sync-logs-table">
                <thead style="background: var(--bg)">
                <tr class="text-left" style="color: var(--muted)">
                    <th class="px-2 py-1.5">Court</th>
                    <th class="px-2 py-1.5">Year</th>
                    <th class="px-2 py-1.5">Status</th>
                    <th class="px-2 py-1.5" style="min-width:120px;">Progress</th>
                    <th class="px-2 py-1.5 text-right">Fetched</th>
                    <th class="px-2 py-1.5 text-right">Saved</th>
                    <th class="px-2 py-1.5 text-right">Errors</th>
                    <th class="px-2 py-1.5">Last Case</th>
                    <th class="px-2 py-1.5">Duration</th>
                    <th class="px-2 py-1.5">Updated</th>
                </tr>
                </thead>
                <tbody>
                @foreach($syncLogs as $index => $log)
                    @php
                        $logTotal = max($log['total_fetched'], 1);
                        $savedPct = round(100 * $log['total_saved'] / $logTotal);
                        $errorPct = round(100 * $log['total_errors'] / $logTotal);
                    @endphp
                    <tr style="border-top:1px solid var(--border)" dusk="sync-log-{{ $index }}">
                        <td class="px-2 py-1.5 font-medium">{{ $log['court_name'] }}</td>
                        <td class="px-2 py-1.5">{{ $log['year'] }}</td>
                        <td class="px-2 py-1.5">
                            @switch($log['status'])
                                @case('completed')
                                    <span class="inline-flex items-center gap-1 text-green-500">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Done
                                    </span>
                                    @break
                                @case('running')
                                    <span class="inline-flex items-center gap-1 text-yellow-500">
                                        <span class="epw-spinner" style="width:.75rem; height:.75rem; border-width:1.5px;"></span>
                                        Running
                                    </span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex items-center gap-1 text-red-500" title="{{ $log['error_message'] ?? '' }}">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        Failed
                                    </span>
                                    @break
                                @default
                                    <span class="text-gray-500">Pending</span>
                            @endswitch
                        </td>
                        <td class="px-2 py-1.5">
                            @if($log['status'] === 'running' || $log['status'] === 'completed')
                                <div class="epw-progress" style="height:.25rem;">
                                    <div class="epw-progress-bar" style="width:{{ $log['status'] === 'completed' ? 100 : min(95, $savedPct) }}%; background:{{ $log['status'] === 'completed' ? '#22c55e' : '#eab308' }};"></div>
                                </div>
                                <div class="text-[10px] muted mt-0.5">#{{ $log['last_case_number'] }} reached</div>
                            @elseif($log['status'] === 'failed')
                                <div class="epw-progress" style="height:.25rem;">
                                    <div class="epw-progress-bar" style="width:{{ $savedPct }}%; background:#ef4444;"></div>
                                </div>
                                <div class="text-[10px] text-red-400 mt-0.5">{{ Str::limit($log['error_message'] ?? 'Failed', 30) }}</div>
                            @else
                                <span class="text-[10px] muted">Waiting...</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-right">{{ number_format($log['total_fetched']) }}</td>
                        <td class="px-2 py-1.5 text-right text-green-500">{{ number_format($log['total_saved']) }}</td>
                        <td class="px-2 py-1.5 text-right {{ $log['total_errors'] > 0 ? 'text-red-500' : '' }}">{{ $log['total_errors'] }}</td>
                        <td class="px-2 py-1.5">#{{ $log['last_case_number'] }}</td>
                        <td class="px-2 py-1.5">
                            @if($log['duration_seconds'])
                                {{ gmdate('H:i:s', $log['duration_seconds']) }}
                            @elseif($log['status'] === 'running' && $log['started_at'])
                                <span class="text-yellow-500 text-xs">in progress</span>
                            @else
                                ---
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs muted">{{ $log['completed_at'] ?? $log['started_at'] ?? '---' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card epw-card text-center muted" dusk="no-sync-logs">
            No sync logs found for {{ $filterYear }} / {{ $filterRegister }}
        </div>
    @endif
</div>
