{{-- Batch Fetch Panel --}}
<div dusk="batch-fetch-panel">
    {{-- Batch Fetch Form --}}
    <div class="card epw-card mb-3">
        <div class="font-semibold mb-2" style="color: var(--fg)">Batch Fetch Configuration</div>
        <div class="grid gap-3 md:grid-cols-4 items-end">
            <div>
                <label class="block text-[11px] muted font-medium mb-1">Court</label>
                <select wire:model="batchCourtId" class="dt-input epw-input w-full" dusk="batch-court-select"
                        wire:loading.attr="disabled" wire:target="startBatchFetch">
                    <option value="">-- Select Court --</option>
                    @foreach($courts as $court)
                        <option value="{{ $court['id'] }}">{{ $court['name'] }}</option>
                    @endforeach
                </select>
                @error('batchCourtId')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;" dusk="batch-court-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-[11px] muted font-medium mb-1">Year</label>
                <select wire:model="batchYear" class="dt-input epw-input w-full" dusk="batch-year-select"
                        wire:loading.attr="disabled" wire:target="startBatchFetch">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                @error('batchYear')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-[11px] muted font-medium mb-1">Register</label>
                <select wire:model="batchRegister" class="dt-input epw-input w-full" dusk="batch-register-select"
                        wire:loading.attr="disabled" wire:target="startBatchFetch">
                    <option value="Pp Prz">Pp Prz</option>
                    <option value="Pp">Pp</option>
                    <option value="K">K</option>
                </select>
                @error('batchRegister')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;">{{ $message }}</div>@enderror
            </div>
            <div>
                <button type="button"
                        wire:click="startBatchFetch"
                        class="btn-primary epw-btn w-full justify-center"
                        dusk="start-batch-fetch"
                        wire:loading.attr="disabled" wire:target="startBatchFetch, fetchAllPending, retryFailed, fullSyncAllCourts"
                        @if($activeAction) disabled @endif>
                    <span wire:loading wire:target="startBatchFetch" class="epw-spinner"></span>
                    <span wire:loading.remove wire:target="startBatchFetch">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </span>
                    <span wire:loading wire:target="startBatchFetch">Dispatching...</span>
                    <span wire:loading.remove wire:target="startBatchFetch">Start Fetch</span>
                </button>
            </div>
        </div>

        @if($batchFetchError)
            <div class="mt-2 text-sm" style="color:#fca5a5;" dusk="batch-fetch-error">{{ $batchFetchError }}</div>
        @endif

        @if($batchFetchStatus === 'dispatched')
            <div class="mt-2 px-3 py-2 rounded-lg text-sm" style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2);" dusk="batch-fetch-dispatched">
                <div class="flex items-center gap-2 text-green-500">
                    <span class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                    <span class="font-medium">Job dispatched successfully!</span>
                </div>
                <div class="mt-1 text-xs muted">Switch to Sync Status tab to monitor progress.</div>
            </div>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="card epw-card mb-3">
        <div class="font-semibold mb-2" style="color: var(--fg)">Quick Actions</div>
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    wire:click="fetchAllPending"
                    class="btn-secondary epw-btn"
                    dusk="fetch-all-pending"
                    wire:loading.attr="disabled" wire:target="fetchAllPending, startBatchFetch, retryFailed, fullSyncAllCourts"
                    @if($activeAction) disabled @endif>
                <span wire:loading wire:target="fetchAllPending" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                <svg wire:loading.remove wire:target="fetchAllPending" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span wire:loading wire:target="fetchAllPending">Dispatching...</span>
                <span wire:loading.remove wire:target="fetchAllPending">Fetch All Pending</span>
            </button>
            <button type="button"
                    wire:click="retryFailed"
                    class="btn-secondary epw-btn"
                    dusk="retry-failed"
                    wire:loading.attr="disabled" wire:target="retryFailed, startBatchFetch, fetchAllPending, fullSyncAllCourts"
                    @if($activeAction) disabled @endif>
                <span wire:loading wire:target="retryFailed" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                <svg wire:loading.remove wire:target="retryFailed" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span wire:loading wire:target="retryFailed">Retrying...</span>
                <span wire:loading.remove wire:target="retryFailed">Retry Failed</span>
            </button>
        </div>
        <div class="mt-2 text-xs muted">
            <strong>Fetch All Pending:</strong> Dispatches jobs for all courts that haven't been fetched for {{ $batchYear }} / {{ $batchRegister }}.<br>
            <strong>Retry Failed:</strong> Re-dispatches jobs for courts that previously failed.
        </div>
    </div>

    {{-- Full Sync All Courts --}}
    <div class="card epw-card mb-3" style="border:1px solid rgba(234,179,8,0.3);">
        <div class="flex items-center gap-2 mb-2">
            <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span class="font-semibold" style="color: var(--fg)">Full Sync All Courts</span>
            <span class="epw-badge" style="background:rgba(234,179,8,0.15); color:#eab308; font-size:10px;">Pp Prz</span>
        </div>
        <div class="text-xs muted mb-3">
            Dispatches fetch jobs for <strong>all courts</strong> across <strong>all years ({{ \App\Http\Livewire\EpredmetWidget::FULL_SYNC_START_YEAR }}&ndash;{{ date('Y') }})</strong>
            under the <strong>Pp Prz</strong> register. Already-completed court/year combinations are skipped automatically.
        </div>
        <button type="button"
                wire:click="fullSyncAllCourts"
                wire:confirm="This will dispatch fetch jobs for ALL courts across ALL years ({{ \App\Http\Livewire\EpredmetWidget::FULL_SYNC_START_YEAR }}-{{ date('Y') }}) under Pp Prz. Already completed combinations will be skipped. Continue?"
                class="btn-primary epw-btn"
                dusk="full-sync-all-courts"
                wire:loading.attr="disabled" wire:target="fullSyncAllCourts, startBatchFetch, fetchAllPending, retryFailed"
                @if($activeAction) disabled @endif>
            <span wire:loading wire:target="fullSyncAllCourts" class="epw-spinner"></span>
            <svg wire:loading.remove wire:target="fullSyncAllCourts" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span wire:loading wire:target="fullSyncAllCourts">Dispatching all jobs...</span>
            <span wire:loading.remove wire:target="fullSyncAllCourts">Start Full Sync</span>
        </button>
        @if($fullSyncJobsDispatched > 0 && $batchFetchStatus === 'dispatched')
            <div class="mt-2 px-3 py-2 rounded-lg text-sm" style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2);" dusk="full-sync-dispatched">
                <div class="flex items-center gap-2 text-green-500">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <span class="font-medium">{{ $fullSyncJobsDispatched }} jobs dispatched!</span>
                </div>
                <div class="mt-1 text-xs muted">Switch to Sync Status tab to monitor progress across all courts and years.</div>
            </div>
        @endif
    </div>

    {{-- Courts List --}}
    @if(count($courts) > 0)
        <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
            <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Available Courts ({{ count($courts) }})</div>
            <table class="min-w-full epw-table" style="color: var(--fg)" dusk="courts-table">
                <thead style="background: var(--bg)">
                <tr class="text-left" style="color: var(--muted)">
                    <th class="px-2 py-1.5">ID</th>
                    <th class="px-2 py-1.5">External ID</th>
                    <th class="px-2 py-1.5">Name</th>
                    <th class="px-2 py-1.5">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($courts as $index => $court)
                    <tr style="border-top:1px solid var(--border)" dusk="court-{{ $index }}"
                        class="{{ $batchCourtId == $court['id'] ? 'epw-row-highlight' : '' }}">
                        <td class="px-2 py-1.5">{{ $court['id'] }}</td>
                        <td class="px-2 py-1.5">{{ $court['external_id'] }}</td>
                        <td class="px-2 py-1.5">
                            <div class="font-medium">{{ $court['name'] }}</div>
                            <div class="text-xs muted">{{ $court['full_name'] }}</div>
                        </td>
                        <td class="px-2 py-1.5">
                            <button type="button"
                                    wire:click="$set('batchCourtId', {{ $court['id'] }})"
                                    class="text-xs px-2 py-1 rounded transition-colors"
                                    style="background: {{ $batchCourtId == $court['id'] ? 'rgba(34,197,94,0.15)' : 'var(--bg)' }}; border: 1px solid {{ $batchCourtId == $court['id'] ? 'rgba(34,197,94,0.3)' : 'var(--border)' }}; color: {{ $batchCourtId == $court['id'] ? '#22c55e' : 'var(--fg)' }};"
                                    dusk="select-court-{{ $court['id'] }}">
                                {{ $batchCourtId == $court['id'] ? 'Selected' : 'Select' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card epw-card text-center muted" dusk="no-courts">
            No courts available. Courts are loaded when switching to this tab.
        </div>
    @endif
</div>
