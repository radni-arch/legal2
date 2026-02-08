<div>
    <x-page-header
        :breadcrumbs="breadcrumbs('federated.memory.search')"
        title="Federated Memory Search"
        subtitle="Semantic search across all agent memories using pgvector similarity"
    />

    <div class="max-w-7xl mx-auto px-4 py-8">
        {{-- Error Message --}}
        @if ($errorMessage)
            <div dusk="error-message" class="mb-6 rounded-lg p-4" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" style="color: #fca5a5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span style="color: #fca5a5;" class="font-medium">{{ $errorMessage }}</span>
                </div>
            </div>
        @endif

        {{-- Search Form --}}
        <div class="rounded-2xl p-6 sm:p-8 mb-8" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);">
            <h2 class="text-2xl font-bold mb-6 flex items-center" style="color: var(--fg, #e5e7eb);">
                <svg class="w-7 h-7 mr-3" style="color: var(--accent, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Search Agent Memories
            </h2>

            {{-- Search Query Input --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2" style="color: var(--muted, #94a3b8);">
                    Search Query
                </label>
                <input
                    type="text"
                    dusk="search-input"
                    wire:model="searchQuery"
                    class="w-full px-4 py-3 rounded-lg transition-all duration-300 focus:outline-none"
                    style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);"
                    onfocus="this.style.borderColor='var(--accent, #38bdf8)'; this.style.boxShadow='0 0 0 3px rgba(56,189,248,0.12)'"
                    onblur="this.style.borderColor='var(--border, #1f2937)'; this.style.boxShadow='none'"
                    placeholder="Enter search query (e.g., proportionality home search)"
                />
                @error('searchQuery')
                    <div dusk="search-input-error" class="mt-2 flex items-center text-sm font-medium px-3 py-2 rounded-lg" style="color: #fca5a5; background: rgba(239,68,68,0.1);">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Agent Filter --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2" style="color: var(--muted, #94a3b8);">
                    Agent Filter
                    <span class="font-normal text-xs ml-2" style="color: var(--muted, #94a3b8);">(optional - leave empty for all agents)</span>
                </label>
                <select
                    dusk="agent-filter"
                    wire:model="agentFilter"
                    class="w-full px-4 py-3 rounded-lg transition-all duration-300 cursor-pointer focus:outline-none"
                    style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);">
                    <option value="">All Agents</option>
                    <option value="decision_discovery">Decision Discovery</option>
                    <option value="research_agent">Research Agent</option>
                    <option value="precedent_analyzer">Precedent Analyzer</option>
                    <option value="autonomous_research">Autonomous Research</option>
                    <option value="evidence_analyzer">Evidence Analyzer</option>
                </select>
            </div>

            {{-- Limit Input --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2" style="color: var(--muted, #94a3b8);">
                    Results Limit
                </label>
                <input
                    type="number"
                    dusk="limit-input"
                    wire:model="limit"
                    min="1"
                    max="100"
                    class="w-full sm:w-48 px-4 py-3 rounded-lg transition-all duration-300 focus:outline-none"
                    style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);"
                />
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <button
                    dusk="search-button"
                    wire:click="search"
                    wire:loading.attr="disabled"
                    wire:target="search"
                    class="flex-1 sm:flex-none font-semibold px-8 py-3 rounded-lg shadow-lg transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                    style="background: linear-gradient(180deg, var(--accent, #38bdf8), var(--accent-hover, #0ea5e9)); color: #0f172a;">
                    <span wire:loading.remove wire:target="search" class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search Memories
                    </span>
                    <span wire:loading wire:target="search" class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching...
                    </span>
                </button>

                @if ($searchPerformed)
                    <button
                        dusk="reset-button"
                        wire:click="resetSearch"
                        wire:loading.attr="disabled"
                        wire:target="resetSearch"
                        class="flex-1 sm:flex-none font-semibold px-8 py-3 rounded-lg shadow-md transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                        style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);">
                        <span wire:loading.remove wire:target="resetSearch" class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Reset
                        </span>
                        <span wire:loading wire:target="resetSearch" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Resetting...
                        </span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Search Results --}}
        @if ($searchPerformed)
            <div class="rounded-2xl p-6 sm:p-8 relative overflow-hidden" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);">
                {{-- Loading Overlay --}}
                <div wire:loading wire:target="search,resetSearch" class="absolute inset-0 backdrop-blur-sm z-10 flex items-center justify-center rounded-2xl" style="background: rgba(0,0,0,0.6);">
                    <div class="text-center">
                        <svg class="animate-spin h-16 w-16 mx-auto mb-4" style="color: var(--accent, #38bdf8);" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Searching memories...</p>
                    </div>
                </div>

                <h2 class="text-2xl font-bold mb-6 flex items-center" style="color: var(--fg, #e5e7eb);">
                    <svg class="w-7 h-7 mr-3" style="color: var(--accent, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Search Results
                </h2>

                <div dusk="result-count" class="mb-6 font-medium" style="color: var(--muted, #94a3b8);">
                    Found <span class="font-bold" style="color: var(--accent, #38bdf8);">{{ count($searchResults) }}</span>
                    {{ count($searchResults) === 1 ? 'result' : 'results' }}
                    @if (!empty($agentFilter))
                        from <span class="font-bold" style="color: var(--accent, #38bdf8);">{{ $agentFilter }}</span>
                    @endif
                </div>

                @if (count($searchResults) === 0)
                    <div dusk="empty-state" class="text-center py-16 px-4">
                        <div class="rounded-full w-32 h-32 mx-auto mb-6 flex items-center justify-center" style="background: rgba(56,189,248,0.1);">
                            <svg class="w-20 h-20" style="color: var(--accent, #38bdf8);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-3" style="color: var(--fg, #e5e7eb);">No results found</h3>
                        <p class="text-lg mb-4" style="color: var(--muted, #94a3b8);">
                            We couldn't find any memories matching your search
                        </p>
                        <div class="rounded-lg p-4 max-w-md mx-auto" style="background: rgba(56,189,248,0.08); border: 1px solid var(--border, #1f2937);">
                            <p class="text-sm" style="color: var(--muted, #94a3b8);">
                                <strong>Try:</strong> Different search terms, removing the agent filter, or broadening your query
                            </p>
                        </div>
                    </div>
                @else
                    {{-- Results List --}}
                    <div dusk="results-list" class="space-y-4">
                        @foreach ($searchResults as $index => $result)
                            <div dusk="result-{{ $index }}"
                                 class="rounded-xl p-5 sm:p-6 transition-all duration-300"
                                 style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); border-left: 4px solid var(--accent, #38bdf8);">

                                {{-- Agent Name & Badges --}}
                                <div class="flex flex-wrap items-center gap-2 mb-4">
                                    <span dusk="result-{{ $index }}-agent" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold" style="background: rgba(56,189,248,0.15); color: #7dd3fc;">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path>
                                        </svg>
                                        {{ $result['agent_name'] ?? 'Unknown Agent' }}
                                    </span>

                                    @if (isset($result['distance']))
                                        <span dusk="result-{{ $index }}-similarity" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold" style="background: rgba(34,197,94,0.15); color: #86efac;">
                                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ number_format((1 - $result['distance']) * 100, 1) }}% match
                                        </span>
                                    @endif

                                    @if (isset($result['access_count']))
                                        <span dusk="result-{{ $index }}-access-count" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold" style="background: rgba(168,85,247,0.15); color: #c084fc;">
                                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $result['access_count'] }} {{ $result['access_count'] === 1 ? 'access' : 'accesses' }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div dusk="result-{{ $index }}-content" class="mb-4 leading-relaxed text-base rounded-lg p-4" style="color: var(--fg, #e5e7eb); background: rgba(0,0,0,0.2);">
                                    {{ $result['content'] ?? 'No content' }}
                                </div>

                                {{-- Metadata --}}
                                @if (isset($result['metadata']) && is_array($result['metadata']) && count($result['metadata']) > 0)
                                    <div dusk="result-{{ $index }}-metadata" class="mb-3 flex flex-wrap gap-2">
                                        @foreach ($result['metadata'] as $key => $value)
                                            @if (!in_array($key, ['namespace']))
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium" style="background: var(--chip, #334155); color: var(--muted, #94a3b8); border: 1px solid var(--border, #1f2937);">
                                                    <span class="font-semibold mr-1">{{ $key }}:</span>
                                                    {{ is_string($value) || is_numeric($value) ? $value : json_encode($value) }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Timestamp --}}
                                @if (isset($result['created_at']))
                                    <div dusk="result-{{ $index }}-timestamp" class="flex items-center text-sm" style="color: var(--muted, #94a3b8);">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Created {{ \Carbon\Carbon::parse($result['created_at'])->diffForHumans() }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Search Method Indicator --}}
            <div dusk="search-method" class="mt-6 text-center">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold" style="background: var(--chip, #334155); color: var(--muted, #94a3b8); border: 1px solid var(--border, #1f2937);">
                    @if (count($searchResults) > 0 && isset($searchResults[0]['distance']))
                        <svg class="w-5 h-5 mr-2" style="color: var(--success, #22c55e);" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Vector Similarity Search
                    @else
                        <svg class="w-5 h-5 mr-2" style="color: var(--accent, #38bdf8);" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Text Search
                    @endif
                </span>
            </div>
        @endif
    </div>
</div>
