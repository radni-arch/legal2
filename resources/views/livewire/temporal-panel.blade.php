<div class="space-y-6" dusk="temporal-panel-container">
    <style>
    .temporal-card {
        background: rgba(17, 24, 39, 0.6);
        border: 1px solid #1f2937;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .temporal-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #1f2937;
    }

    .temporal-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #e5e7eb;
    }

    .temporal-card-body {
        padding: 1.5rem;
    }
    </style>

    <!-- Query Type Selector -->
    <div class="flex gap-3 mb-6 flex-wrap" dusk="query-type-selector">
        @foreach($queryTypes as $typeKey => $typeLabel)
            <button
                wire:click="$set('queryType', '{{ $typeKey }}')"
                wire:loading.attr="disabled"
                wire:target="$set('queryType', '{{ $typeKey }}')"
                class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed @if($queryType === $typeKey) bg-amber-600 text-white @else bg-gray-800 text-gray-400 hover:bg-gray-700 @endif"
                dusk="query-type-{{ $typeKey }}-btn"
            >
                <span wire:loading.remove wire:target="$set('queryType', '{{ $typeKey }}')">
                    @if($typeKey === 'law_at_date')
                        📅
                    @elseif($typeKey === 'evolution')
                        📈
                    @elseif($typeKey === 'amendments')
                        📝
                    @endif
                    {{ $typeLabel }}
                </span>
                <span wire:loading wire:target="$set('queryType', '{{ $typeKey }}')" class="inline-flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading...
                </span>
            </button>
        @endforeach
    </div>

    <!-- Query Interface -->
    <div class="temporal-card relative" dusk="query-interface-container">
        <!-- Loading Overlay for Query Interface -->
        <div wire:loading wire:target="executeQuery"
             class="absolute inset-0 bg-gradient-to-br from-slate-900/90 to-slate-800/90 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg"
             dusk="query-loading-overlay">
            <div class="text-center">
                <svg class="animate-spin h-16 w-16 text-amber-400 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-white font-medium text-lg" dusk="loading-message">Executing temporal query...</p>
                <p class="text-gray-300 text-sm" dusk="loading-submessage">Analyzing legal graph state</p>
            </div>
        </div>

        <div class="temporal-card-header">
            <h2 class="temporal-card-title" dusk="query-interface-title">Temporal Query</h2>
            <p class="text-sm mt-1" style="color: var(--muted);" dusk="query-interface-description">Query legal graph state at specific points in time</p>
        </div>
        <div class="temporal-card-body space-y-4">
            <!-- Date Selector -->
            <div dusk="date-selector-container">
                <label class="block text-sm font-medium mb-2" style="color: #e5e7eb;" dusk="date-label">Select Date</label>
                <input
                    type="date"
                    wire:model.defer="selectedDate"
                    class="w-full max-w-md rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    @if($loading) disabled @endif
                    dusk="selected-date-input"
                />
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3" dusk="action-buttons-container">
                <button
                    wire:click="executeQuery"
                    wire:loading.attr="disabled"
                    wire:target="executeQuery"
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-amber-600 to-orange-600 text-white rounded-lg font-medium hover:shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    dusk="execute-query-btn"
                >
                    <span wire:loading.remove wire:target="executeQuery">
                        🔍 Execute Query
                    </span>
                    <span wire:loading wire:target="executeQuery" class="inline-flex items-center" dusk="execute-query-loading">
                        <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>

                @if($results || $error)
                    <button
                        wire:click="clearResults"
                        wire:loading.attr="disabled"
                        wire:target="clearResults"
                        class="px-6 py-3 bg-gray-800 text-gray-400 border border-gray-700 rounded-lg font-medium hover:bg-gray-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        dusk="clear-results-btn"
                    >
                        <span wire:loading.remove wire:target="clearResults">
                            Clear Results
                        </span>
                        <span wire:loading wire:target="clearResults" class="inline-flex items-center">
                            <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Clearing...
                        </span>
                    </button>
                @endif
            </div>

            <!-- Error Display -->
            @if($error)
                <div class="rounded-lg p-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);" dusk="error-display">
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="error-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h4 class="font-semibold" style="color: #ef4444;" dusk="error-title">Error</h4>
                            <p class="text-sm mt-1" style="color: #fca5a5;" dusk="error-message">{{ $error }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Results Display -->
    @if($results)
        <div class="temporal-card" dusk="results-container">
            <div class="temporal-card-header">
                <h3 class="temporal-card-title" dusk="results-title">Results</h3>
            </div>
            <div class="temporal-card-body">
                <div class="text-center py-12" style="color: #94a3b8;" dusk="results-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: #f59e0b;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="results-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-xl font-semibold mb-2" style="color: #e5e7eb;" dusk="results-heading">Temporal Queries - Coming Soon</h3>
                    <p dusk="results-description">Time-travel queries and law evolution tracking will be implemented here</p>
                    <div class="mt-6 p-4 bg-gray-900 rounded-lg inline-block" dusk="results-metadata">
                        <p class="text-sm" dusk="results-query-type">
                            <span class="font-semibold">Query Type:</span>
                            <span dusk="results-query-type-value">{{ $queryTypes[$results['type']] ?? 'Unknown' }}</span>
                        </p>
                        <p class="text-sm mt-1" dusk="results-selected-date">
                            <span class="font-semibold">Selected Date:</span>
                            <span dusk="results-selected-date-value">{{ $results['date'] ?? 'N/A' }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Feature Preview -->
    @if(!$results && !$error)
    <div class="temporal-card" dusk="feature-preview-container">
        <div class="temporal-card-header">
            <h3 class="temporal-card-title" dusk="feature-preview-title">Temporal Analysis Features</h3>
        </div>
        <div class="temporal-card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" dusk="feature-cards-grid">
                <div class="p-4 rounded-lg border border-gray-700 bg-gray-900" dusk="feature-law-at-date">
                    <div class="text-2xl mb-2" dusk="feature-law-at-date-icon">📅</div>
                    <h4 class="font-semibold mb-2" style="color: #e5e7eb;" dusk="feature-law-at-date-title">Law State at Date</h4>
                    <p class="text-sm" style="color: #9ca3af;" dusk="feature-law-at-date-description">Query which version of a law was valid on a specific date</p>
                </div>
                <div class="p-4 rounded-lg border border-gray-700 bg-gray-900" dusk="feature-evolution">
                    <div class="text-2xl mb-2" dusk="feature-evolution-icon">📈</div>
                    <h4 class="font-semibold mb-2" style="color: #e5e7eb;" dusk="feature-evolution-title">Law Evolution</h4>
                    <p class="text-sm" style="color: #9ca3af;" dusk="feature-evolution-description">Track how laws changed over time with timeline visualization</p>
                </div>
                <div class="p-4 rounded-lg border border-gray-700 bg-gray-900" dusk="feature-amendments">
                    <div class="text-2xl mb-2" dusk="feature-amendments-icon">📝</div>
                    <h4 class="font-semibold mb-2" style="color: #e5e7eb;" dusk="feature-amendments-title">Amendment Impact</h4>
                    <p class="text-sm" style="color: #9ca3af;" dusk="feature-amendments-description">Analyze how amendments affected related decisions and cases</p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
