<div class="p-6" wire:poll.{{ $refreshInterval }}s dusk="circuit-breaker-monitor" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    {{-- Unified Header --}}
    <x-page-header
        title="Circuit Breaker Monitor"
        subtitle="Real-time monitoring of circuit breaker states across all services"
        route-name="circuit-breaker.monitor"
    />

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 p-4 rounded-lg shadow-sm transition-all duration-300"
             style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #86efac;"
             dusk="flash-success"
             role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 rounded-lg shadow-sm transition-all duration-300"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;"
             dusk="flash-error"
             role="alert">
            {{ session('error') }}
        </div>
    @endif

    {{-- Current Status Grid --}}
    <div class="mb-8 relative">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--fg, #e5e7eb);">Current Status</h2>

        {{-- Loading overlay for status cards during refresh --}}
        <div wire:loading.delay wire:target="$refresh"
             class="absolute inset-0 backdrop-blur-sm rounded-lg z-10 flex items-center justify-center"
             style="background: rgba(11,18,32,0.75);"
             dusk="status-loading-overlay">
            <div class="text-center">
                <svg class="animate-spin h-12 w-12 mx-auto" style="color: var(--accent, #38bdf8);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-3 text-sm font-medium" style="color: var(--muted, #94a3b8);">Refreshing status...</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" dusk="circuit-status-grid">
            @foreach ($circuitStatuses as $service => $status)
                <div class="rounded-lg p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
                    style="@if($status['state'] === 'closed') background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3);
                    @elseif($status['state'] === 'half_open') background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.3);
                    @elseif($status['state'] === 'open') background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
                    @else background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937);
                    @endif"
                    dusk="circuit-card-{{ $service }}">

                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-lg capitalize" style="color: var(--fg, #e5e7eb);" dusk="circuit-name-{{ $service }}">{{ $service }}</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium shadow-sm"
                            style="@if($status['state'] === 'closed') background: rgba(34,197,94,0.15); color: #86efac;
                            @elseif($status['state'] === 'half_open') background: rgba(234,179,8,0.15); color: #fde047;
                            @elseif($status['state'] === 'open') background: rgba(239,68,68,0.15); color: #fca5a5;
                            @else background: var(--surface, #0f172a); color: var(--muted, #94a3b8);
                            @endif"
                            dusk="circuit-status-{{ $service }}">
                            {{ strtoupper(str_replace('_', ' ', $status['state'])) }}
                        </span>
                    </div>

                    @if(isset($status['error']))
                        <p class="text-sm" style="color: #fca5a5;" dusk="circuit-error-{{ $service }}">{{ $status['error'] }}</p>
                    @else
                        <div class="space-y-1 text-sm" style="color: var(--muted, #94a3b8);">
                            <div class="flex justify-between" dusk="failure-count-{{ $service }}">
                                <span>Failures:</span>
                                <span class="font-medium">{{ $status['failure_count'] ?? 0 }}/{{ $status['failure_threshold'] ?? 5 }}</span>
                            </div>
                            @if($status['state'] === 'half_open')
                                <div class="flex justify-between" dusk="success-count-{{ $service }}">
                                    <span>Successes:</span>
                                    <span class="font-medium">{{ $status['success_count'] ?? 0 }}/{{ $status['success_threshold'] ?? 2 }}</span>
                                </div>
                            @endif
                            @if($status['last_failure'])
                                <div class="flex justify-between" dusk="last-failure-{{ $service }}">
                                    <span>Last Failure:</span>
                                    <span class="font-medium text-xs">{{ \Carbon\Carbon::parse($status['last_failure'])->diffForHumans() }}</span>
                                </div>
                            @endif
                        </div>

                        @if($status['state'] !== 'closed')
                            {{-- CRITICAL: Reset Circuit Button with Full Loading States --}}
                            <button
                                wire:click="resetCircuit('{{ $service }}')"
                                wire:loading.attr="disabled"
                                wire:target="resetCircuit"
                                dusk="reset-circuit-{{ $service }}"
                                class="mt-3 w-full px-3 py-2 text-white text-sm font-medium rounded-lg
                                       focus:outline-none focus:ring-2 focus:ring-offset-2
                                       disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-md hover:shadow-lg"
                                style="background: var(--accent, #38bdf8); focus-ring-color: var(--accent, #38bdf8);"
                                onmouseover="this.style.opacity='0.85';"
                                onmouseout="this.style.opacity='1';">
                                <span wire:loading.remove wire:target="resetCircuit" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Reset Circuit
                                </span>
                                <span wire:loading wire:target="resetCircuit" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Resetting...
                                </span>
                            </button>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Historical Events --}}
    <div class="relative">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--fg, #e5e7eb);">Recent Events (Last 24 Hours)</h2>

        @if(count($recentEvents) > 0)
            <div class="overflow-x-auto rounded-lg shadow-sm relative"
                 style="border: 1px solid var(--border, #1f2937);"
                 dusk="events-table-container">
                {{-- Loading overlay during reset operations --}}
                <div wire:loading.delay wire:target="resetCircuit"
                     class="absolute inset-0 backdrop-blur-sm rounded-lg z-20 flex items-center justify-center transition-all duration-300"
                     style="background: rgba(11,18,32,0.75);"
                     dusk="events-loading-overlay">
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 mx-auto" style="color: var(--accent, #38bdf8);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-sm font-medium" style="color: var(--fg, #e5e7eb);">Processing circuit reset...</p>
                        <p class="mt-1 text-xs" style="color: var(--muted, #94a3b8);">Events will be updated momentarily</p>
                    </div>
                </div>

                <table class="min-w-full" dusk="events-table">
                    <thead style="background: var(--surface, #0f172a);">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                                Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                                Service
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                                State
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                                Failures
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                                Details
                            </th>
                        </tr>
                    </thead>
                    <tbody style="background: var(--card, #111827);">
                        @foreach ($recentEvents as $event)
                            <tr style="border-bottom: 1px solid var(--border, #1f2937);"
                                onmouseover="this.style.background='var(--surface, #0f172a)';"
                                onmouseout="this.style.background='transparent';"
                                dusk="event-row-{{ $event->id ?? $loop->index }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm"
                                    style="color: var(--fg, #e5e7eb);"
                                    dusk="event-time-{{ $event->id ?? $loop->index }}">
                                    {{ \Carbon\Carbon::parse($event->created_at)->format('M d, H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium capitalize"
                                    style="color: var(--fg, #e5e7eb);"
                                    dusk="event-service-{{ $event->id ?? $loop->index }}">
                                    {{ $event->service }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"
                                    dusk="event-state-{{ $event->id ?? $loop->index }}">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full shadow-sm"
                                        style="@if($event->state === 'closed') background: rgba(34,197,94,0.15); color: #86efac;
                                        @elseif($event->state === 'half_open') background: rgba(234,179,8,0.15); color: #fde047;
                                        @elseif($event->state === 'open') background: rgba(239,68,68,0.15); color: #fca5a5;
                                        @else background: var(--surface, #0f172a); color: var(--muted, #94a3b8);
                                        @endif">
                                        {{ strtoupper(str_replace('_', ' ', $event->state)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"
                                    style="color: var(--fg, #e5e7eb);"
                                    dusk="event-failures-{{ $event->id ?? $loop->index }}">
                                    {{ $event->failure_count }}
                                </td>
                                <td class="px-6 py-4 text-sm"
                                    style="color: var(--muted, #94a3b8);"
                                    dusk="event-details-{{ $event->id ?? $loop->index }}">
                                    @if($event->metadata)
                                        @php
                                            $metadata = json_decode($event->metadata, true);
                                            $displayText = '';
                                            if (isset($metadata['last_error']['message'])) {
                                                $displayText = substr($metadata['last_error']['message'], 0, 50);
                                            } elseif (isset($metadata['closed_at'])) {
                                                $displayText = 'Circuit recovered';
                                            } elseif (isset($metadata['half_opened_at'])) {
                                                $displayText = 'Testing recovery';
                                            }
                                        @endphp
                                        {{ $displayText }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 rounded-lg shadow-sm"
                 style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937);"
                 dusk="events-empty-state">
                <svg class="mx-auto h-12 w-12" style="color: var(--muted, #94a3b8);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-3 font-medium" style="color: var(--muted, #94a3b8);">No events recorded in the last 24 hours.</p>
                <p class="text-sm mt-2" style="color: var(--border, #1f2937);">Events will appear here when circuit breaker state changes occur.</p>
            </div>
        @endif
    </div>

    <div class="mt-6 flex items-center justify-between text-sm"
         style="color: var(--muted, #94a3b8);"
         dusk="auto-refresh-info">
        <div class="flex items-center gap-2">
            <svg wire:loading.remove wire:target="$refresh" class="h-4 w-4" style="color: var(--success, #22c55e);" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <svg wire:loading wire:target="$refresh" class="animate-spin h-4 w-4" style="color: var(--accent, #38bdf8);" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Auto-refreshing every {{ $refreshInterval }} seconds</span>
        </div>
        <div>
            Last updated: <span class="font-medium">{{ now()->format('H:i:s') }}</span>
        </div>
    </div>
</div>
