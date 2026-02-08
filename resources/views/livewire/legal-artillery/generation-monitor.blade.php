<div
    @if(in_array($status, ['running', 'pending']))
        wire:poll.3s="checkStatus"
    @endif
    style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 1.5rem;">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-semibold text-sm" style="color: var(--fg, #e5e7eb);">{{ $documentType }}</h3>
            <p class="text-xs" style="color: var(--muted, #94a3b8);">Run: {{ substr($runId, 0, 8) }}...</p>
        </div>
        <span style="display: inline-flex; align-items: center; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
            @if($status === 'completed') background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.35);
            @elseif($status === 'running') background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.35); animation: pulse 2s infinite;
            @elseif($status === 'pending') background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35); animation: pulse 2s infinite;
            @else background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35);
            @endif">
            {{ $status }}
        </span>
    </div>

    @if($status === 'pending')
        <div class="text-center py-4">
            <svg class="animate-spin h-8 w-8 mx-auto mb-2" style="color: var(--accent, #38bdf8);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="text-sm" style="color: var(--muted, #94a3b8);">Cekam u redu...</p>
        </div>
    @endif

    {{-- Running indicator --}}
    @if($status === 'running')
        <div class="flex items-center gap-3 mb-4" style="background: var(--bg, #0b1220); padding: 0.75rem; border-radius: 0.5rem;">
            <svg class="animate-spin h-5 w-5" style="color: var(--warn, #eab308);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <div class="flex-1">
                <div class="text-sm font-medium" style="color: var(--fg, #e5e7eb);">
                    @if($currentIteration)
                        Iteracija {{ $currentIteration }}
                    @else
                        Pokrecem...
                    @endif
                </div>
                @if($currentScore)
                    <div class="text-xs" style="color: var(--muted, #94a3b8);">
                        Trenutna ocjena: <span style="color: var(--accent, #38bdf8); font-weight: 600;">{{ number_format($currentScore, 1) }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Iteration Steps --}}
    @if(count($iterations) > 0)
        <div class="space-y-2">
            @php $seen = []; @endphp
            @foreach($iterations as $iter)
                @if($iter['phase'] === 'critic' && !in_array($iter['number'], $seen))
                    @php $seen[] = $iter['number']; @endphp
                    <div class="flex items-center gap-3" style="font-size: 0.8rem;">
                        <span style="width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
                            background: rgba(56,189,248,0.15); color: var(--accent, #38bdf8); font-size: 0.7rem; font-weight: 600;">
                            {{ $iter['number'] }}
                        </span>
                        <span style="color: var(--fg, #e5e7eb); flex: 1;">
                            Ocjena: <strong style="color: var(--accent, #38bdf8);">{{ $iter['score'] ? number_format($iter['score'], 1) : '-' }}</strong>
                        </span>
                        @if($iter['delta'] !== null)
                            <span style="font-size: 0.75rem; font-weight: 500;
                                {{ $iter['delta'] > 0 ? 'color: #22c55e;' : ($iter['delta'] < 0 ? 'color: #ef4444;' : 'color: #94a3b8;') }}">
                                {{ $iter['delta'] > 0 ? '+' : '' }}{{ number_format($iter['delta'], 1) }}%
                            </span>
                        @endif
                        <span class="text-xs" style="color: var(--muted, #94a3b8);">{{ $iter['created_at'] ?? '' }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Completed --}}
    @if($status === 'completed')
        <div class="mt-3 pt-3" style="border-top: 1px solid var(--border, #1f2937);">
            <div class="flex items-center justify-between">
                <span class="text-sm" style="color: var(--success, #22c55e); font-weight: 600;">Zavrseno</span>
                @if($currentScore)
                    <span class="text-sm" style="color: var(--accent, #38bdf8); font-weight: 600;">{{ number_format($currentScore, 1) }}/100</span>
                @endif
            </div>
            @if($stoppedReason)
                <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);">{{ $stoppedReason }}</p>
            @endif
        </div>
    @endif

    {{-- Failed --}}
    @if($status === 'failed')
        <div class="mt-3 pt-3" style="border-top: 1px solid rgba(239,68,68,0.35);">
            <span class="text-sm" style="color: #ef4444; font-weight: 600;">Neuspjelo</span>
            @if($stoppedReason)
                <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);">{{ $stoppedReason }}</p>
            @endif
        </div>
    @endif

    {{-- Error details for failed state --}}
    @if($status === 'failed' && $errorMessage)
        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <h4 class="text-sm font-semibold text-red-800 mb-2">Generacija neuspjela</h4>
            <pre class="text-xs text-red-700 whitespace-pre-wrap break-words font-mono bg-red-100 p-3 rounded">{{ $errorMessage }}</pre>
        </div>
    @endif
</div>
