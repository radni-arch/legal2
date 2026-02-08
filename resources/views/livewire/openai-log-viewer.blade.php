<div class="container mx-auto max-w-6xl p-4" style="--bg: #0b1220; --surface: #0f172a; --card: #111827; --border: #1f2937; --fg: #e5e7eb; --muted: #94a3b8; --accent: #38bdf8;">
    {{-- Unified Header --}}
    <x-page-header
        title="OpenAI API Logs"
        subtitle="Monitor and analyze OpenAI API requests and responses"
        route-name="openai.logs"
    />

    <div class="rounded-md p-3 mb-4" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);">
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm" style="color: var(--muted, #94a3b8);">Limit
                <select dusk="limit-select" class="rounded px-2 py-1 text-sm" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);" wire:model.live="limit">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                </select>
            </label>
            <label class="text-sm" style="color: var(--muted, #94a3b8);">Search
                <input dusk="search-input" type="text" class="rounded px-2 py-1 text-sm" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);" placeholder="text, id, status…" wire:model.debounce.500ms="search" />
            </label>
            <label class="text-sm" style="color: var(--muted, #94a3b8);">Request ID
                <input type="text" class="rounded px-2 py-1 text-sm" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);" placeholder="req-uuid" wire:model.debounce.500ms="requestId" />
            </label>

            <div class="flex items-center gap-2 text-sm">
                <button dusk="filter-request" type="button" class="px-2 py-1 rounded" style="{{ $eventTypes['openai.request'] ? 'background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.4); color: #86efac;' : 'background: transparent; border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);' }}" wire:click="toggleEvent('openai.request')">Request</button>
                <button dusk="filter-response" type="button" class="px-2 py-1 rounded" style="{{ $eventTypes['openai.response'] ? 'background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.4); color: #93c5fd;' : 'background: transparent; border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);' }}" wire:click="toggleEvent('openai.response')">Response</button>
                <button dusk="filter-error" type="button" class="px-2 py-1 rounded" style="{{ $eventTypes['openai.error'] ? 'background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5;' : 'background: transparent; border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);' }}" wire:click="toggleEvent('openai.error')">Error</button>
            </div>

            <div class="flex-1"></div>

            <label class="flex items-center gap-2 text-sm" style="color: var(--muted, #94a3b8);">
                <input dusk="auto-refresh-toggle" type="checkbox" wire:model="autoRefresh" /> Auto refresh
            </label>
            <button dusk="refresh-btn" type="button" class="px-3 py-1.5 rounded text-sm" style="background: transparent; border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);" wire:click="refreshNow">Refresh</button>
            <button dusk="clear-filters-btn" type="button" class="px-3 py-1.5 rounded text-sm" style="background: transparent; border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);" wire:click="clearFilters">Clear</button>
        </div>
    </div>

    <div @if($autoRefresh) wire:poll.5s="refreshNow" @endif>
        @if(empty($entries))
            <div class="text-sm" style="color: var(--muted, #94a3b8);">No entries to display. Ensure requests are being made and logging channel 'openai' is configured.</div>
        @else
            <ul dusk="log-entries" class="space-y-3">
                @foreach($entries as $i => $e)
                    <li dusk="log-entry" class="rounded-md p-3" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);">
                        <div class="flex flex-wrap items-center gap-2 text-xs mb-2">
                            <span class="px-2 py-0.5 rounded" style="{{ $e['message'] === 'openai.request' ? 'background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.4); color: #86efac;' : ($e['message'] === 'openai.response' ? 'background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.4); color: #93c5fd;' : 'background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5;') }}">{{ $e['message'] }}</span>
                            @if($e['request_id'])
                                <span class="cursor-pointer underline" style="color: var(--accent, #38bdf8);" wire:click="filterByRequest('{{ $e['request_id'] }}')">{{ $e['request_id'] }}</span>
                            @endif
                            @if($e['datetime'])
                                <span style="color: var(--muted, #94a3b8);">{{ is_array($e['datetime']) ? ($e['datetime']['date'] ?? '') : $e['datetime'] }}</span>
                            @endif
                            <span style="color: var(--muted, #94a3b8);">{{ $e['channel'] ?? '' }} {{ $e['level'] ? '· '.$e['level'] : '' }}</span>

                            @if(isset($e['context']['status']))
                                <span class="px-1.5 py-0.5 rounded" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);">status: {{ $e['context']['status'] }}</span>
                            @endif
                            @if(isset($e['context']['duration_ms']))
                                <span class="px-1.5 py-0.5 rounded" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);">{{ $e['context']['duration_ms'] }} ms</span>
                            @endif
                            @if(isset($e['context']['url']))
                                <span class="px-1.5 py-0.5 rounded" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);">{{ $e['context']['method'] ?? '' }} {{ $e['context']['url'] }}</span>
                            @endif
                        </div>

                        @php
                            $ctx = $e['context'] ?? [];
                            $payload = $ctx['payload'] ?? null;
                            $response = $ctx['response'] ?? null;
                            $error = $ctx['error'] ?? null;
                        @endphp

                        @if($payload)
                            <details dusk="entry-details" class="mb-2" open>
                                <summary class="cursor-pointer text-sm font-medium" style="color: var(--fg, #e5e7eb);">Payload</summary>
                                <pre class="mt-2 text-xs rounded p-2 overflow-x-auto" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);">{{ json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif

                        @if($response)
                            <details dusk="entry-details" class="mb-2" @if($e['message']==='openai.response') open @endif>
                                <summary class="cursor-pointer text-sm font-medium" style="color: var(--fg, #e5e7eb);">Response</summary>
                                <pre class="mt-2 text-xs rounded p-2 overflow-x-auto" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--muted, #94a3b8);">{{ json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif

                        @if($error)
                            <details dusk="entry-details" class="mb-2" open>
                                <summary class="cursor-pointer text-sm font-medium" style="color: #fca5a5;">Error</summary>
                                <pre class="mt-2 text-xs rounded p-2 overflow-x-auto" style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5;">{{ json_encode($error, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
