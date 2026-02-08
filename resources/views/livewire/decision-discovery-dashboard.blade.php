<div class="decision-discovery-dashboard" dusk="decision-discovery">
    {{-- Header --}}
    <x-page-header
        :breadcrumbs="breadcrumbs('decisions.discover')"
        title="Decision Discovery"
        subtitle="{{ $sourceType === 'esljp' ? 'Search ESLJP (sljeme.usud.hr)' : ($sourceType === 'usud' ? 'Search Ustavni sud (sljeme.usud.hr)' : 'Search Odluke.sudovi.hr') }}"
    />

    {{-- Statistics Cards with Loading Overlay --}}
    <div class="stats-grid relative" dusk="stats-grid">
        {{-- Loading Overlay for Stats Section --}}
        <div wire:loading wire:target="refreshStats,search,resetSearch"
             class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg"
             dusk="stats-loading-overlay">
            <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-white font-medium" dusk="stats-loading-text">Refreshing statistics...</p>
            </div>
        </div>

        <div class="stat-card" dusk="stat-card-total">
            <div class="stat-label">Total Decisions</div>
            <div class="stat-value stat-accent" dusk="stat-value-total">{{ $stats['total_decisions'] }}</div>
        </div>
        <div class="stat-card" dusk="stat-card-vectors">
            <div class="stat-label">Decisions with Vectors</div>
            <div class="stat-value stat-success" dusk="stat-value-vectors">{{ $stats['decisions_with_vectors'] }}</div>
        </div>
        <div class="stat-card" dusk="stat-card-chunks">
            <div class="stat-label">Total Chunks</div>
            <div class="stat-value stat-info" dusk="stat-value-chunks">{{ $stats['total_chunks'] }}</div>
        </div>
        <div class="stat-card" dusk="stat-card-avg">
            <div class="stat-label">Avg Chunks per Decision</div>
            <div class="stat-value stat-warn" dusk="stat-value-avg">{{ $stats['avg_chunks_per_decision'] }}</div>
        </div>
    </div>

    {{-- Messages --}}
    @if ($successMessage)
        <div class="alert alert-success" dusk="success-message">
            ✓ {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="alert alert-error" dusk="error-message">
            ✗ {{ $errorMessage }}
        </div>
    @endif

    @if ($searchError)
        <div class="alert alert-error" dusk="search-error-message">
            ⚠️ {{ $searchError }}
        </div>
    @endif

    {{-- Search Form --}}
    <div class="search-card" dusk="search-card">
        <div class="section-header" dusk="search-section-header">🔍 Search Court Decisions</div>

        <div class="search-form" dusk="search-form">
            {{-- Source Selection --}}
            <div class="control-group" dusk="control-group-source">
                <label dusk="label-source">Source</label>
                <select id="sourceType" name="sourceType" class="control-input" wire:model="sourceType" dusk="select-source-type">
                    @foreach($sourceOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Keywords Input --}}
            <div class="control-group" dusk="control-group-keywords">
                <label dusk="label-keywords">Keywords *</label>
                <input
                    type="text"
                    id="searchKeywords"
                    name="searchKeywords"
                    class="control-input"
                    wire:model="searchKeywords"
                    placeholder="Enter search terms (e.g., 'proportionality' or 'kazneno djelo')"
                    dusk="input-search-keywords"
                >
                @error('searchKeywords')
                    <span class="error-msg" dusk="error-keywords">{{ $message }}</span>
                @enderror
            </div>

            @if($sourceType === 'odluke')
                {{-- Court Type and Decision Type Row --}}
                <div class="control-row" dusk="control-row-filters">
                    <div class="control-group" dusk="control-group-court">
                        <label dusk="label-court">Court Type</label>
                        <select id="courtFilter" name="courtFilter" class="control-input" wire:model="courtFilter" dusk="select-court-filter">
                            @foreach($courtOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="control-group" dusk="control-group-decision-type">
                        <label dusk="label-decision-type">Decision Type</label>
                        <select id="decisionType" name="decisionType" class="control-input" wire:model="decisionType" dusk="select-decision-type">
                            @foreach($decisionTypeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Date Range Row --}}
                <div class="control-row" dusk="control-row-dates">
                    <div class="control-group" dusk="control-group-date-from">
                        <label dusk="label-date-from">Date From</label>
                        <input
                            type="date"
                            class="control-input"
                            wire:model="dateFrom"
                            dusk="input-date-from"
                        >
                        @error('dateFrom')
                            <span class="error-msg" dusk="error-date-from">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="control-group" dusk="control-group-date-to">
                        <label dusk="label-date-to">Date To</label>
                        <input
                            type="date"
                            class="control-input"
                            wire:model="dateTo"
                            dusk="input-date-to"
                        >
                        @error('dateTo')
                            <span class="error-msg" dusk="error-date-to">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            @else
                <div class="control-row" dusk="control-row-esljp-note">
                    <div class="control-group">
                        <label>Note</label>
                        <div class="text-xs muted">Search uses a text query on sljeme.usud.hr. Additional filters are not available.</div>
                    </div>
                </div>
            @endif

            {{-- Action Buttons with Loading States --}}
            <div class="control-actions" dusk="control-actions">
                {{-- Search Button with Loading State --}}
                <button
                    type="button"
                    class="btn btn-info"
                    wire:click="search"
                    wire:loading.attr="disabled"
                    wire:target="search"
                    dusk="search-btn"
                >
                    <span wire:loading.remove wire:target="search" dusk="search-btn-text">🔍 Search</span>
                    <span wire:loading wire:target="search" dusk="search-btn-loading">
                        <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Searching...
                    </span>
                </button>

                {{-- Reset Search Button with Loading State --}}
                @if($searchPerformed)
                    <button
                        type="button"
                        class="btn"
                        wire:click="resetSearch"
                        wire:loading.attr="disabled"
                        wire:target="resetSearch"
                        dusk="reset-search-btn"
                    >
                        <span wire:loading.remove wire:target="resetSearch" dusk="reset-search-btn-text">🔄 Reset</span>
                        <span wire:loading wire:target="resetSearch" dusk="reset-search-btn-loading">
                            <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Resetting...
                        </span>
                    </button>
                @endif

                {{-- Refresh Stats Button with Loading State --}}
                <button
                    type="button"
                    class="btn"
                    wire:click="refreshStats"
                    wire:loading.attr="disabled"
                    wire:target="refreshStats"
                    dusk="refresh-stats-btn"
                >
                    <span wire:loading.remove wire:target="refreshStats" dusk="refresh-stats-btn-text">📊 Refresh Stats</span>
                    <span wire:loading wire:target="refreshStats" dusk="refresh-stats-btn-loading">
                        <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Refreshing...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Search Results --}}
    @if($searchPerformed && !empty($searchResults))
        <div class="results-card" dusk="results-card">
            <div class="section-header" dusk="results-section-header">
                📋 Search Results ({{ count($searchResults) }} found)
            </div>

            {{-- Batch Actions --}}
            <div class="batch-actions" dusk="batch-actions">
                <label class="checkbox-label" dusk="select-all-label">
                    <input
                        type="checkbox"
                        wire:model="selectAll"
                        wire:click="toggleSelectAll"
                        dusk="select-all-checkbox"
                    >
                    <span>Select All</span>
                </label>

                @if(count($selectedDecisions) > 0)
                    <button
                        type="button"
                        class="btn btn-success"
                        wire:click="ingestSelected"
                        wire:loading.attr="disabled"
                        wire:target="ingestSelected"
                        dusk="ingest-selected-btn"
                    >
                        <span wire:loading.remove wire:target="ingestSelected" dusk="ingest-selected-btn-text">
                            📥 Ingest Selected ({{ count($selectedDecisions) }})
                        </span>
                        <span wire:loading wire:target="ingestSelected" dusk="ingest-selected-btn-loading">
                            <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Ingesting...
                        </span>
                    </button>
                @endif
            </div>

            {{-- Progress Bar --}}
            @if($ingestInProgress)
                <div class="progress-container" dusk="progress-container">
                    <div class="progress-bar" dusk="progress-bar">
                        <div
                            class="progress-fill"
                            style="width: {{ $ingestTotal > 0 ? ($ingestProgress / $ingestTotal * 100) : 0 }}%"
                            dusk="progress-fill"
                        ></div>
                    </div>
                    <div class="progress-text" dusk="progress-text">
                        {{ $ingestProgress }} / {{ $ingestTotal }} decisions queued
                        ({{ $ingestSucceeded }} succeeded, {{ $ingestFailed }} failed)
                    </div>
                </div>
            @endif

            {{-- Results Table with Loading Overlay --}}
            <div class="results-table relative" dusk="results-table-wrapper">
                {{-- Loading Overlay for Results Table --}}
                <div wire:loading wire:target="search,resetSearch,toggleSelectAll,selectForIngest"
                     class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg"
                     dusk="results-loading-overlay">
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-white font-medium" dusk="results-loading-text">Loading decisions...</p>
                    </div>
                </div>

                <table dusk="decisions-table">
                    <thead>
                        <tr dusk="table-header-row">
                            <th style="width: 50px;" dusk="th-select">Select</th>
                            <th style="width: 200px;" dusk="th-case-number">Case Number</th>
                            <th dusk="th-court">Court</th>
                            <th style="width: 120px;" dusk="th-date">Date</th>
                            <th style="width: 150px;" dusk="th-type">Type</th>
                            <th style="width: 150px;" dusk="th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody dusk="table-body">
                        @foreach($searchResults as $decision)
                            <tr dusk="decision-row-{{ $decision['id'] }}">
                                <td dusk="decision-checkbox-cell-{{ $decision['id'] }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $decision['id'] }}"
                                        wire:click="selectForIngest('{{ $decision['id'] }}')"
                                        @if(in_array($decision['id'], $selectedDecisions)) checked @endif
                                        dusk="decision-checkbox-{{ $decision['id'] }}"
                                    >
                                </td>
                                <td dusk="decision-case-number-{{ $decision['id'] }}">
                                    <span class="case-number" dusk="case-number-{{ $decision['id'] }}">{{ $decision['case_number'] }}</span>
                                    @if($decision['ecli'])
                                        <br><span class="ecli" dusk="ecli-{{ $decision['id'] }}">{{ $decision['ecli'] }}</span>
                                    @endif
                                </td>
                                <td dusk="decision-court-{{ $decision['id'] }}">{{ $decision['court'] }}</td>
                                <td dusk="decision-date-{{ $decision['id'] }}">{{ $decision['decision_date'] }}</td>
                                <td dusk="decision-type-{{ $decision['id'] }}">{{ $decision['decision_type'] }}</td>
                                <td dusk="decision-actions-{{ $decision['id'] }}">
                                    {{-- Preview Button with Loading State --}}
                                    <button
                                        type="button"
                                        class="btn-small btn-info"
                                        wire:click="preview('{{ $decision['id'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="preview"
                                        dusk="preview-btn-{{ $decision['id'] }}"
                                    >
                                        <span wire:loading.remove wire:target="preview">👁️ Preview</span>
                                        <span wire:loading wire:target="preview">
                                            <svg class="animate-spin inline h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <a
                                        href="/citation-time-series?decision_id={{ $decision['id'] }}"
                                        class="btn-small btn-info"
                                        style="text-decoration: none; display: inline-block; margin-left: 4px;"
                                        dusk="citations-link-{{ $decision['id'] }}"
                                    >
                                        📊 Citations
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Empty State --}}
    @if($searchPerformed && empty($searchResults) && !$searchError)
        <div class="empty-state-card" dusk="empty-state">
            <div class="empty-icon" dusk="empty-icon">📭</div>
            <div class="empty-text" dusk="empty-text">No decisions found. Try adjusting your search criteria.</div>
        </div>
    @endif

    {{-- Preview Modal with Alpine.js Animations --}}
    @if($showPreviewModal && $previewData)
        <div x-data="{ open: @entangle('showPreviewModal') }"
             x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="modal-overlay"
             wire:click="closePreview"
             dusk="modal-overlay">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="modal-content"
                 wire:click.stop
                 dusk="modal-content">
                {{-- Modal Header --}}
                <div class="modal-header" dusk="modal-header">
                    <h2 dusk="modal-title">📄 Decision Preview</h2>
                    <button type="button"
                            class="modal-close"
                            wire:click="closePreview"
                            wire:loading.attr="disabled"
                            wire:target="closePreview"
                            dusk="modal-close-btn">
                        <span wire:loading.remove wire:target="closePreview">✕</span>
                        <span wire:loading wire:target="closePreview">
                            <svg class="animate-spin inline h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="modal-body" dusk="modal-body">
                    <div class="preview-grid" dusk="preview-grid">
                        <div class="preview-field" dusk="preview-field-case-number">
                            <div class="preview-label" dusk="preview-label-case-number">Case Number</div>
                            <div class="preview-value" dusk="preview-value-case-number">{{ $previewData['case_number'] }}</div>
                        </div>

                        <div class="preview-field" dusk="preview-field-court">
                            <div class="preview-label" dusk="preview-label-court">Court</div>
                            <div class="preview-value" dusk="preview-value-court">{{ $previewData['court'] }}</div>
                        </div>

                        <div class="preview-field" dusk="preview-field-date">
                            <div class="preview-label" dusk="preview-label-date">Decision Date</div>
                            <div class="preview-value" dusk="preview-value-date">{{ $previewData['decision_date'] }}</div>
                        </div>

                        <div class="preview-field" dusk="preview-field-type">
                            <div class="preview-label" dusk="preview-label-type">Decision Type</div>
                            <div class="preview-value" dusk="preview-value-type">{{ $previewData['decision_type'] }}</div>
                        </div>

                        @if(isset($previewData['meta']['title']) && $previewData['meta']['title'])
                            <div class="preview-field" dusk="preview-field-title">
                                <div class="preview-label" dusk="preview-label-title">Title</div>
                                <div class="preview-value" dusk="preview-value-title">{{ $previewData['meta']['title'] }}</div>
                            </div>
                        @endif

                        @if($previewData['ecli'])
                            <div class="preview-field" dusk="preview-field-ecli">
                                <div class="preview-label" dusk="preview-label-ecli">ECLI</div>
                                <div class="preview-value" dusk="preview-value-ecli">{{ $previewData['ecli'] }}</div>
                            </div>
                        @endif

                        @if(isset($previewData['meta']['upisnik']))
                            <div class="preview-field" dusk="preview-field-register">
                                <div class="preview-label" dusk="preview-label-register">Register</div>
                                <div class="preview-value" dusk="preview-value-register">{{ $previewData['meta']['upisnik'] }}</div>
                            </div>
                        @endif

                        @if(isset($previewData['meta']['pravomocnost']))
                            <div class="preview-field" dusk="preview-field-finality">
                                <div class="preview-label" dusk="preview-label-finality">Finality</div>
                                <div class="preview-value" dusk="preview-value-finality">{{ $previewData['meta']['pravomocnost'] }}</div>
                            </div>
                        @endif

                        @if(isset($previewData['meta']['datum_objave']))
                            <div class="preview-field" dusk="preview-field-publication">
                                <div class="preview-label" dusk="preview-label-publication">Publication Date</div>
                                <div class="preview-value" dusk="preview-value-publication">{{ $previewData['meta']['datum_objave'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="modal-footer" dusk="modal-footer">
                    {{-- Select for Ingest Button with Loading State --}}
                    <button
                        type="button"
                        class="btn btn-success"
                        wire:click="selectForIngest('{{ $previewData['id'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="selectForIngest"
                        dusk="modal-select-ingest-btn"
                    >
                        <span wire:loading.remove wire:target="selectForIngest" dusk="modal-select-ingest-text">
                            @if(in_array($previewData['id'], $selectedDecisions))
                                ✓ Selected
                            @else
                                📥 Select for Ingest
                            @endif
                        </span>
                        <span wire:loading wire:target="selectForIngest" dusk="modal-select-ingest-loading">
                            <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Selecting...
                        </span>
                    </button>
                    <a
                        href="/citation-time-series?decision_id={{ $previewData['id'] }}"
                        class="btn btn-info"
                        style="text-decoration: none; display: inline-block;"
                        dusk="modal-citations-link"
                    >
                        📊 View Citations
                    </a>
                    {{-- Close Button with Loading State --}}
                    <button type="button"
                            class="btn"
                            wire:click="closePreview"
                            wire:loading.attr="disabled"
                            wire:target="closePreview"
                            dusk="modal-close-footer-btn">
                        <span wire:loading.remove wire:target="closePreview" dusk="modal-close-footer-text">Close</span>
                        <span wire:loading wire:target="closePreview" dusk="modal-close-footer-loading">
                            <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Closing...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
    .decision-discovery-dashboard {
    background: var(--bg, #0b1220);
    color: var(--fg, #e5e7eb);
    min-height: 100vh;
}

.decision-discovery-dashboard > .stats-grid,
.decision-discovery-dashboard > .search-card,
.decision-discovery-dashboard > .results-card,
.decision-discovery-dashboard > .alert,
.decision-discovery-dashboard > .empty-state-card {
    margin-left: 1.5rem;
    margin-right: 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.relative {
    position: relative;
}

.stat-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #8b949e;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
}

.stat-accent { color: #58a6ff; }
.stat-success { color: #3fb950; }
.stat-info { color: #a371f7; }
.stat-warn { color: #d29922; }
.stat-error { color: #f85149; }

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-success {
    background: rgba(63, 185, 80, 0.1);
    border: 1px solid rgba(63, 185, 80, 0.3);
    color: #3fb950;
}

.alert-error {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.3);
    color: #f85149;
}

.search-card, .results-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.section-header {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #f0f6fc;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.control-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.control-group label {
    font-size: 0.875rem;
    color: #8b949e;
    font-weight: 500;
}

.control-input {
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: #e0e6ed;
    font-size: 0.875rem;
}

.control-input:focus {
    outline: none;
    border-color: #58a6ff;
}

.control-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #30363d;
    background: #21262d;
    color: #e0e6ed;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn:hover {
    background: #30363d;
}

.btn-info {
    background: #1f6feb;
    border-color: #1f6feb;
}

.btn-info:hover {
    background: #388bfd;
}

.btn-success {
    background: #238636;
    border-color: #238636;
}

.btn-success:hover {
    background: #2ea043;
}

.btn-small {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.error-msg {
    color: #f85149;
    font-size: 0.75rem;
}

.batch-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #e0e6ed;
    cursor: pointer;
}

.progress-container {
    margin-bottom: 1rem;
}

.progress-bar {
    width: 100%;
    height: 24px;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #1f6feb, #388bfd);
    transition: width 0.3s ease;
}

.progress-text {
    margin-top: 0.5rem;
    color: #8b949e;
    font-size: 0.875rem;
}

.results-table {
    overflow-x: auto;
}

.results-table table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th {
    text-align: left;
    padding: 0.75rem;
    background: #0d1117;
    border-bottom: 2px solid #30363d;
    color: #8b949e;
    font-size: 0.875rem;
    font-weight: 600;
}

.results-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #21262d;
}

.results-table tr:hover {
    background: #161b22;
}

.case-number {
    color: #58a6ff;
    font-weight: 500;
}

.ecli {
    color: #8b949e;
    font-size: 0.75rem;
}

.empty-state-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 3rem;
    text-align: center;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-text {
    color: #8b949e;
    font-size: 1.125rem;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #30363d;
}

.modal-header h2 {
    color: #f0f6fc;
    font-size: 1.5rem;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
}

.modal-close:hover {
    color: #f0f6fc;
}

.modal-body {
    padding: 1.5rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.preview-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.preview-label {
    font-size: 0.875rem;
    color: #8b949e;
    font-weight: 600;
}

.preview-value {
    color: #e0e6ed;
    font-size: 1rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #30363d;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}
    </style>
</div>
