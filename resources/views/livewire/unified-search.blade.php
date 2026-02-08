<div>
    {{-- Search Form --}}
    <form wire:submit.prevent="search">
        <div class="controls">
            <div class="ctrl" style="flex: 1; min-width: 300px">
                <label>Search Query</label>
                <div class="relative">
                    <input
                        dusk="search-input"
                        type="text"
                        wire:model.defer="query"
                        wire:loading.attr="disabled"
                        wire:target="search,clearSearch,previousPage,nextPage,goToPage,toggleCorpus,resetFilters"
                        placeholder="Enter your search query (e.g., 'pravo na privatnost', 'NN 123/20')..."
                        class="in"
                        style="width: 100%; font-size: 15px; padding-right: 40px"
                        autofocus
                    />
                    {{-- Search Icon / Loading Spinner --}}
                    <div class="absolute" style="right: 12px; top: 50%; transform: translateY(-50%)">
                        <svg wire:loading.remove wire:target="search" dusk="search-icon" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <svg wire:loading wire:target="search" dusk="search-spinner" class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
                @error('query')
                    <small style="color: #fca5a5; display: block; margin-top: 4px">{{ $message }}</small>
                @enderror
            </div>

            <div class="ctrl">
                <label>Search Mode</label>
                <select
                    dusk="search-mode-select"
                    wire:model="searchMode"
                    wire:loading.attr="disabled"
                    wire:target="search,clearSearch,previousPage,nextPage,goToPage"
                    class="in"
                >
                    <option value="unified">Unified (Vector)</option>
                    <option value="hybrid">Hybrid (Vector + Keyword)</option>
                    <option value="with-citations">With Citations</option>
                    <option value="laws">Laws Only</option>
                    <option value="decisions">Decisions Only</option>
                    <option value="cases">Cases Only</option>
                </select>
            </div>

            <button
                dusk="search-btn"
                type="submit"
                wire:loading.attr="disabled"
                wire:target="search"
                class="btn primary"
                style="white-space: nowrap; transition: all 0.2s; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)"
            >
                <span wire:loading.remove wire:target="search">
                    🔍 Search
                </span>
                <span wire:loading wire:target="search" class="flex items-center">
                    <svg class="animate-spin inline h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </span>
            </button>

            @if($query || $searchResults)
                <button
                    dusk="clear-search-btn"
                    type="button"
                    wire:click="clearSearch"
                    wire:loading.attr="disabled"
                    wire:target="clearSearch"
                    class="btn"
                    style="transition: all 0.2s"
                >
                    <span wire:loading.remove wire:target="clearSearch">Clear</span>
                    <span wire:loading wire:target="clearSearch" class="flex items-center">
                        <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Clearing...
                    </span>
                </button>
            @endif

            @if($searchResults)
                <button
                    dusk="export-btn"
                    type="button"
                    wire:click="exportResults"
                    wire:loading.attr="disabled"
                    wire:target="exportResults"
                    class="btn info"
                    title="Export results as JSON"
                    style="transition: all 0.2s; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)"
                >
                    <span wire:loading.remove wire:target="exportResults">📥 Export</span>
                    <span wire:loading wire:target="exportResults" class="flex items-center">
                        <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Exporting...
                    </span>
                </button>
            @endif
        </div>
    </form>

    {{-- Advanced Options --}}
    <details dusk="advanced-options">
        <summary>▶ Advanced Options</summary>
        <div class="detail-content">
            <div class="controls">
                {{-- Corpora Selection --}}
                <div class="ctrl">
                    <label>Corpora</label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap">
                        <button
                            dusk="corpus-laws-btn"
                            type="button"
                            wire:click="toggleCorpus('laws')"
                            wire:loading.attr="disabled"
                            wire:target="toggleCorpus"
                            class="chip clickable {{ in_array('laws', $corpora) ? 'active' : '' }}"
                            style="transition: all 0.2s; transform: scale(1)"
                            onmouseover="this.style.transform='scale(1.05)'"
                            onmouseout="this.style.transform='scale(1)'"
                        >
                            <span wire:loading.remove wire:target="toggleCorpus">⚖️ Laws</span>
                            <span wire:loading wire:target="toggleCorpus" class="flex items-center">
                                <svg class="animate-spin inline h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                ⚖️ Laws
                            </span>
                        </button>
                        <button
                            dusk="corpus-decisions-btn"
                            type="button"
                            wire:click="toggleCorpus('decisions')"
                            wire:loading.attr="disabled"
                            wire:target="toggleCorpus"
                            class="chip clickable {{ in_array('decisions', $corpora) ? 'active' : '' }}"
                            style="transition: all 0.2s; transform: scale(1)"
                            onmouseover="this.style.transform='scale(1.05)'"
                            onmouseout="this.style.transform='scale(1)'"
                        >
                            <span wire:loading.remove wire:target="toggleCorpus">🏛️ Decisions</span>
                            <span wire:loading wire:target="toggleCorpus" class="flex items-center">
                                <svg class="animate-spin inline h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                🏛️ Decisions
                            </span>
                        </button>
                        <button
                            dusk="corpus-cases-btn"
                            type="button"
                            wire:click="toggleCorpus('cases')"
                            wire:loading.attr="disabled"
                            wire:target="toggleCorpus"
                            class="chip clickable {{ in_array('cases', $corpora) ? 'active' : '' }}"
                            style="transition: all 0.2s; transform: scale(1)"
                            onmouseover="this.style.transform='scale(1.05)'"
                            onmouseout="this.style.transform='scale(1)'"
                        >
                            <span wire:loading.remove wire:target="toggleCorpus">📁 Cases</span>
                            <span wire:loading wire:target="toggleCorpus" class="flex items-center">
                                <svg class="animate-spin inline h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                📁 Cases
                            </span>
                        </button>
                    </div>
                </div>

                {{-- Threshold --}}
                <div class="ctrl">
                    <label>Similarity Threshold: {{ number_format($threshold, 2) }}</label>
                    <input
                        dusk="threshold-slider"
                        type="range"
                        wire:model.defer="threshold"
                        wire:loading.attr="disabled"
                        wire:target="search"
                        min="0"
                        max="1"
                        step="0.05"
                    />
                    <small class="text-muted">Higher = more relevant results</small>
                </div>

                {{-- Results per page --}}
                <div class="ctrl">
                    <label>Results per page</label>
                    <select
                        dusk="limit-select"
                        wire:model.defer="limit"
                        wire:loading.attr="disabled"
                        wire:target="search"
                        class="in small"
                    >
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {{-- Sort Options --}}
                <div class="ctrl">
                    <label>Sort By</label>
                    <select
                        dusk="sort-by-select"
                        wire:model.defer="sortBy"
                        wire:loading.attr="disabled"
                        wire:target="search"
                        class="in small"
                    >
                        <option value="score">Relevance (Score)</option>
                        <option value="date">Date</option>
                    </select>
                </div>

                <div class="ctrl">
                    <label>Sort Order</label>
                    <select
                        dusk="sort-order-select"
                        wire:model.defer="sortOrder"
                        wire:loading.attr="disabled"
                        wire:target="search"
                        class="in small"
                    >
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select>
                </div>

                {{-- Deduplication --}}
                <div class="ctrl">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px">
                        <input
                            dusk="deduplicate-checkbox"
                            type="checkbox"
                            wire:model.defer="deduplicate"
                            wire:loading.attr="disabled"
                            wire:target="search"
                            style="accent-color: var(--accent)"
                        >
                        <span>Deduplicate results</span>
                    </label>
                </div>
            </div>

            <div style="margin-top: 12px">
                <button
                    dusk="reset-filters-btn"
                    type="button"
                    wire:click="resetFilters"
                    wire:loading.attr="disabled"
                    wire:target="resetFilters"
                    class="btn"
                    style="transition: all 0.2s"
                >
                    <span wire:loading.remove wire:target="resetFilters">Reset to Defaults</span>
                    <span wire:loading wire:target="resetFilters" class="flex items-center">
                        <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Resetting...
                    </span>
                </button>
            </div>

            {{-- Corpus Weights --}}
            @if(in_array($searchMode, ['unified', 'hybrid']))
                <details dusk="corpus-weights-section" style="margin-top: 16px">
                    <summary>▶ Corpus Weights</summary>
                    <div class="detail-content">
                        <div class="controls">
                            <div class="ctrl">
                                <label>Laws Weight: {{ number_format($weights['laws'], 1) }}</label>
                                <input
                                    dusk="weight-laws-slider"
                                    type="range"
                                    wire:model.defer="weights.laws"
                                    wire:loading.attr="disabled"
                                    wire:target="search"
                                    min="0"
                                    max="5"
                                    step="0.1"
                                />
                            </div>
                            <div class="ctrl">
                                <label>Decisions Weight: {{ number_format($weights['decisions'], 1) }}</label>
                                <input
                                    dusk="weight-decisions-slider"
                                    type="range"
                                    wire:model.defer="weights.decisions"
                                    wire:loading.attr="disabled"
                                    wire:target="search"
                                    min="0"
                                    max="5"
                                    step="0.1"
                                />
                            </div>
                            <div class="ctrl">
                                <label>Cases Weight: {{ number_format($weights['cases'], 1) }}</label>
                                <input
                                    dusk="weight-cases-slider"
                                    type="range"
                                    wire:model.defer="weights.cases"
                                    wire:loading.attr="disabled"
                                    wire:target="search"
                                    min="0"
                                    max="5"
                                    step="0.1"
                                />
                            </div>
                        </div>
                        <small class="text-muted">Higher weights increase relevance for that corpus type</small>
                    </div>
                </details>
            @endif

            {{-- Advanced Filters --}}
            <details dusk="filters-section" style="margin-top: 16px">
                <summary>▶ Filters</summary>
                <div class="detail-content">
                    <div class="controls">
                        <div class="ctrl">
                            <label>Jurisdiction</label>
                            <input
                                dusk="filter-jurisdiction-input"
                                type="text"
                                wire:model.defer="filterJurisdiction"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                placeholder="e.g., HR, RS"
                                class="in small"
                            />
                        </div>
                        <div class="ctrl">
                            <label>Country</label>
                            <input
                                dusk="filter-country-input"
                                type="text"
                                wire:model.defer="filterCountry"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                placeholder="e.g., Croatia"
                                class="in small"
                            />
                        </div>
                        <div class="ctrl">
                            <label>Court</label>
                            <input
                                dusk="filter-court-input"
                                type="text"
                                wire:model.defer="filterCourt"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                placeholder="e.g., Vrhovni sud"
                                class="in small"
                            />
                        </div>
                        <div class="ctrl">
                            <label>Language</label>
                            <input
                                dusk="filter-language-input"
                                type="text"
                                wire:model.defer="filterLanguage"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                placeholder="e.g., hr, en"
                                class="in small"
                            />
                        </div>
                        <div class="ctrl">
                            <label>Date From</label>
                            <input
                                dusk="filter-date-from-input"
                                type="date"
                                wire:model.defer="filterDateFrom"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                class="in small"
                            />
                            @error('filterDateFrom')
                                <small style="color: #fca5a5; display: block; margin-top: 4px">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="ctrl">
                            <label>Date To</label>
                            <input
                                dusk="filter-date-to-input"
                                type="date"
                                wire:model.defer="filterDateTo"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                class="in small"
                            />
                            @error('filterDateTo')
                                <small style="color: #fca5a5; display: block; margin-top: 4px">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                    <small class="text-muted">Leave empty to search all. Filters are applied to metadata fields.</small>
                </div>
            </details>
        </div>
    </details>

    {{-- Error State --}}
    @if($error)
        <div class="error-banner" dusk="error-banner">
            <div class="error-title">❌ Search Error</div>
            <div class="error-message">{{ $error }}</div>
        </div>
    @endif

    {{-- Results Metadata --}}
    @if($searchMetadata)
        <div class="metadata-info" dusk="results-metadata">
            <div class="metadata-row">
                <div style="flex: 1">
                    <div class="metadata-text">
                        Found <strong style="color: var(--accent)" dusk="results-count">{{ number_format($searchMetadata['total_results']) }}</strong> results
                        @if($searchMetadata['deduplicated_count'] > 0)
                            <span dusk="deduplicated-count">({{ $searchMetadata['deduplicated_count'] }} duplicates removed)</span>
                        @endif
                        for query: <strong style="color: #e2e8f0" dusk="results-query">"{{ $searchMetadata['query'] }}"</strong>
                    </div>
                    <div class="metadata-muted">
                        Search type: <strong dusk="search-type">{{ $searchMetadata['search_type'] }}</strong>
                        · Response time: <strong dusk="response-time">{{ number_format($searchMetadata['response_time_ms'] ?? 0) }}ms</strong>
                        @if($searchMetadata['cached'])
                            · <span style="color: var(--success)" dusk="cached-indicator">✓ Cached</span>
                        @endif
                        @if($searchMetadata['request_id'])
                            · Request ID: <code dusk="request-id" style="font-size: 10px; background: rgba(255,255,255,0.05); padding: 2px 4px; border-radius: 3px">{{ $searchMetadata['request_id'] }}</code>
                        @endif
                    </div>

                    @if($searchMetadata['result_counts'])
                        <div class="metadata-muted" style="margin-top: 4px" dusk="source-counts">
                            Sources:
                            Vector: <span dusk="vector-count">{{ $searchMetadata['result_counts']['vector'] ?? 0 }}</span>,
                            Full-text: <span dusk="fulltext-count">{{ $searchMetadata['result_counts']['fulltext'] ?? 0 }}</span>,
                            Citation: <span dusk="citation-count">{{ $searchMetadata['result_counts']['citation'] ?? 0 }}</span>
                        </div>
                    @endif
                </div>

                {{-- Pagination Controls --}}
                @if($searchMetadata['pagination']['total_pages'] > 1)
                    <div class="pagination" style="margin:0" dusk="pagination-top">
                        <button
                            dusk="prev-page-btn-top"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage"
                            class="btn"
                            @if($page <= 1) disabled @endif
                            style="transition: all 0.2s"
                        >
                            <span wire:loading.remove wire:target="previousPage">← Previous</span>
                            <span wire:loading wire:target="previousPage" class="flex items-center">
                                <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>

                        <span class="text-sm text-muted" style="display: flex; align-items: center; padding: 0 8px" dusk="page-info-top">
                            Page {{ $page }} of {{ $searchMetadata['pagination']['total_pages'] }}
                        </span>

                        <button
                            dusk="next-page-btn-top"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="nextPage"
                            class="btn"
                            @if($page >= $searchMetadata['pagination']['total_pages']) disabled @endif
                            style="transition: all 0.2s"
                        >
                            <span wire:loading.remove wire:target="nextPage">Next →</span>
                            <span wire:loading wire:target="nextPage" class="flex items-center">
                                <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Search Results --}}
    @if($searchResults !== null)
        <div class="search-results-wrapper relative" dusk="search-results-container">
            {{-- Loading Overlay --}}
            <div
                wire:loading
                wire:target="search,previousPage,nextPage,goToPage,toggleCorpus"
                class="absolute inset-0 bg-white/90 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg"
                dusk="results-loading-overlay"
                style="min-height: 200px"
            >
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-700 font-medium" style="font-size: 16px; margin-bottom: 8px">Searching across all legal sources...</p>
                    <p class="text-gray-500 text-sm">This may take a few seconds</p>
                </div>
            </div>

            {{-- Results Content --}}
            @if(count($searchResults) > 0)
                @php
                    $groupedResults = $this->groupResultsByType();
                @endphp

                @foreach($groupedResults as $type => $results)
                    <div class="section-title" dusk="results-section-{{ $type }}">
                        <span class="icon">{{ $this->getTypeIcon($type) }}</span>
                        {{ $this->getTypeLabel($type) }}
                        <span class="chip" dusk="results-{{ $type }}-count">{{ count($results) }}</span>
                    </div>

                    <ul class="seg-list" dusk="results-{{ $type }}-list">
                        @foreach($results as $index => $result)
                            <li class="result-card fade-in"
                                dusk="result-{{ $type }}-{{ $result['id'] }}"
                                style="transition: all 0.2s; transform: scale(1)"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(0,0,0,0.15)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow=''"
                            >
                                {{-- Result Header --}}
                                <div class="result-header">
                                    <div style="flex: 1; min-width: 0">
                                        <h3 class="result-title" dusk="result-{{ $type }}-{{ $result['id'] }}-title">
                                            {{ $result['title'] }}
                                        </h3>

                                        {{-- Metadata --}}
                                        <div class="result-meta" dusk="result-{{ $type }}-{{ $result['id'] }}-meta">
                                            @if($type === 'law')
                                                @if($result['metadata']['law_number'] ?? null)
                                                    <span dusk="result-{{ $type }}-{{ $result['id'] }}-law-number">Law: {{ $result['metadata']['law_number'] }}</span>
                                                @endif
                                                @if($result['metadata']['jurisdiction'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-jurisdiction">{{ $result['metadata']['jurisdiction'] }}</span>
                                                @endif
                                                @if($result['metadata']['promulgation_date'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-date">{{ \Carbon\Carbon::parse($result['metadata']['promulgation_date'])->format('Y-m-d') }}</span>
                                                @endif
                                                @if($result['metadata']['article_number'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-article">Art. {{ $result['metadata']['article_number'] }}</span>
                                                @endif
                                            @elseif($type === 'decision')
                                                @if($result['metadata']['case_number'] ?? null)
                                                    <span dusk="result-{{ $type }}-{{ $result['id'] }}-case-number">Case: {{ $result['metadata']['case_number'] }}</span>
                                                @endif
                                                @if($result['metadata']['court'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-court">{{ $result['metadata']['court'] }}</span>
                                                @endif
                                                @if($result['metadata']['decision_date'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-date">{{ \Carbon\Carbon::parse($result['metadata']['decision_date'])->format('Y-m-d') }}</span>
                                                @endif
                                                @if($result['metadata']['ecli'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-ecli">ECLI: {{ $result['metadata']['ecli'] }}</span>
                                                @endif
                                            @elseif($type === 'case')
                                                @if($result['metadata']['doc_id'] ?? null)
                                                    <span dusk="result-{{ $type }}-{{ $result['id'] }}-doc-id">Doc: {{ $result['metadata']['doc_id'] }}</span>
                                                @endif
                                                @if($result['metadata']['category'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-category">{{ $result['metadata']['category'] }}</span>
                                                @endif
                                                @if($result['metadata']['language'] ?? null)
                                                    · <span dusk="result-{{ $type }}-{{ $result['id'] }}-language">{{ strtoupper($result['metadata']['language']) }}</span>
                                                @endif
                                            @endif

                                            @if($result['metadata']['chunk_index'] ?? null)
                                                · <span dusk="result-{{ $type }}-{{ $result['id'] }}-chunk">Chunk {{ $result['metadata']['chunk_index'] }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Score Badge --}}
                                    <div class="result-score" dusk="result-{{ $type }}-{{ $result['id'] }}-score">
                                        {{ number_format($result['score'] * 100, 1) }}%
                                        @if(isset($result['rrf_sources']))
                                            <div style="font-size: 10px; opacity: 0.8; margin-top: 2px" dusk="result-{{ $type }}-{{ $result['id'] }}-sources">
                                                {{ implode(', ', array_map('ucfirst', $result['rrf_sources'])) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Snippet --}}
                                <div class="result-snippet" dusk="result-{{ $type }}-{{ $result['id'] }}-snippet">
                                    {{ $result['snippet'] }}
                                </div>

                                {{-- Additional Metadata --}}
                                <div class="result-badges" dusk="result-{{ $type }}-{{ $result['id'] }}-badges">
                                    <span class="chip" dusk="result-{{ $type }}-{{ $result['id'] }}-id-badge">ID: {{ $result['id'] }}</span>
                                    <span class="chip" dusk="result-{{ $type }}-{{ $result['id'] }}-type-badge">Type: {{ $type }}</span>
                                    @if($result['metadata']['content_hash'] ?? null)
                                        <span class="chip" title="Content Hash" dusk="result-{{ $type }}-{{ $result['id'] }}-hash-badge">🔑 {{ substr($result['metadata']['content_hash'], 0, 8) }}...</span>
                                    @endif
                                    @if(isset($result['raw_score']))
                                        <span class="chip" title="Original score before weighting" dusk="result-{{ $type }}-{{ $result['id'] }}-raw-score">Raw: {{ number_format($result['raw_score'], 4) }}</span>
                                    @endif
                                    @if(isset($result['corpus_weight']))
                                        <span class="chip" title="Corpus weight applied" dusk="result-{{ $type }}-{{ $result['id'] }}-weight">Weight: {{ $result['corpus_weight'] }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                {{-- Bottom Pagination --}}
                @if($searchMetadata && $searchMetadata['pagination']['total_pages'] > 1)
                    <div class="pagination" dusk="pagination-bottom">
                        <button
                            dusk="prev-page-btn-bottom"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage"
                            class="btn"
                            @if($page <= 1) disabled @endif
                            style="transition: all 0.2s; background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%)"
                        >
                            <span wire:loading.remove wire:target="previousPage">← Previous</span>
                            <span wire:loading wire:target="previousPage" class="flex items-center">
                                <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>

                        {{-- Page Numbers --}}
                        @php
                            $totalPages = $searchMetadata['pagination']['total_pages'];
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                        @endphp

                        @if($start > 1)
                            <button
                                dusk="page-1-btn"
                                wire:click="goToPage(1)"
                                wire:loading.attr="disabled"
                                wire:target="goToPage"
                                class="btn"
                                style="transition: all 0.2s"
                            >
                                <span wire:loading.remove wire:target="goToPage">1</span>
                                <span wire:loading wire:target="goToPage">
                                    <svg class="animate-spin inline h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                            @if($start > 2)
                                <span class="text-muted" style="padding: 8px" dusk="pagination-ellipsis-start">...</span>
                            @endif
                        @endif

                        @for($i = $start; $i <= $end; $i++)
                            <button
                                dusk="page-{{ $i }}-btn"
                                wire:click="goToPage({{ $i }})"
                                wire:loading.attr="disabled"
                                wire:target="goToPage"
                                class="btn {{ $i === $page ? 'primary' : '' }}"
                                style="transition: all 0.2s; {{ $i === $page ? 'background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)' : '' }}"
                            >
                                <span wire:loading.remove wire:target="goToPage">{{ $i }}</span>
                                <span wire:loading wire:target="goToPage">
                                    <svg class="animate-spin inline h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                        @endfor

                        @if($end < $totalPages)
                            @if($end < $totalPages - 1)
                                <span class="text-muted" style="padding: 8px" dusk="pagination-ellipsis-end">...</span>
                            @endif
                            <button
                                dusk="page-{{ $totalPages }}-btn"
                                wire:click="goToPage({{ $totalPages }})"
                                wire:loading.attr="disabled"
                                wire:target="goToPage"
                                class="btn"
                                style="transition: all 0.2s"
                            >
                                <span wire:loading.remove wire:target="goToPage">{{ $totalPages }}</span>
                                <span wire:loading wire:target="goToPage">
                                    <svg class="animate-spin inline h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                        @endif

                        <button
                            dusk="next-page-btn-bottom"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="nextPage"
                            class="btn"
                            @if($page >= $totalPages) disabled @endif
                            style="transition: all 0.2s; background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%)"
                        >
                            <span wire:loading.remove wire:target="nextPage">Next →</span>
                            <span wire:loading wire:target="nextPage" class="flex items-center">
                                <svg class="animate-spin inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>
                    </div>
                @endif
            @else
                {{-- Empty State --}}
                <div class="empty-state" dusk="no-results-found">
                    <div class="empty-icon">🔍</div>
                    <h3 class="empty-title">No results found</h3>
                    <div class="empty-message">
                        Try adjusting your search query or lowering the similarity threshold.
                    </div>
                </div>
            @endif
        </div>
    @elseif(!$isSearching && !$error)
        {{-- Initial State --}}
        <div class="empty-state" dusk="initial-state">
            <div class="empty-icon">🔍</div>
            <h3 class="empty-title">Ready to search</h3>
            <div class="empty-message">
                Enter a search query above to search across Croatian laws, court decisions, and case documents.
                <br><br>
                <strong style="color: #e2e8f0">Examples:</strong>
                <ul style="list-style: none; padding: 0; margin-top: 12px">
                    <li style="margin: 6px 0" dusk="example-query-1">• "pravo na privatnost u digitalnom okruženju"</li>
                    <li style="margin: 6px 0" dusk="example-query-2">• "NN 123/20" (find law by number)</li>
                    <li style="margin: 6px 0" dusk="example-query-3">• "ugovor o radu i radno vrijeme"</li>
                </ul>
            </div>
        </div>
    @endif

    {{-- Loading State --}}
    @if($isSearching && $searchResults === null)
        <div class="empty-state" dusk="searching-state">
            <div style="font-size: 48px; margin-bottom: 12px">⏳</div>
            <div style="font-size: 16px; color: var(--muted)">Searching across legal databases...</div>
        </div>
    @endif

    <script>
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('input[dusk="search-input"]')?.focus();
            }

            // Escape to clear search (when input is focused)
            if (e.key === 'Escape' && document.activeElement.matches('input[dusk="search-input"]')) {
                @this.call('clearSearch');
            }
        });

        // Copy URL to clipboard
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('search-url-copied', () => {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Search URL copied to clipboard!');
                });
            });
        });
    </script>

    <style>
        /* Enhanced CSS for UnifiedSearch Component */
        .result-card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .chip.clickable {
            cursor: pointer;
        }

        .chip.clickable:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .chip.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: white !important;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
        }

        .btn:hover:not([disabled]) {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .relative {
            position: relative;
        }

        .absolute {
            position: absolute;
        }

        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
        }

        .z-10 {
            z-index: 10;
        }

        .inset-0 {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
        }

        .bg-white\/90 {
            background-color: rgba(255, 255, 255, 0.9);
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</div>
