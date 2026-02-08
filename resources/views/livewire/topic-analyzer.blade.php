<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        {{-- Unified Header --}}
        <x-page-header
            title="Topic Framework - Interactive Demo"
            subtitle="Test the modular abuse detection system with real-time analysis"
            route-name="topics.demo"
        />

        {{-- Topic Selector --}}
        <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 mb-6 transition-all duration-300 hover:shadow-xl">
            <label class="block text-sm font-medium text-slate-300 mb-2">
                Select Topic
            </label>
            <select
                wire:model="selectedTopic"
                dusk="topic-selector"
                class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                @foreach ($topics as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Error Message --}}
        @if ($errorMessage)
            <div class="bg-gradient-to-r from-red-900/20 to-red-900/10 border border-red-600 text-red-400 px-4 py-3 rounded-lg relative mb-6 animate-fade-in" role="alert" dusk="error-message">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="border-b border-slate-700 mb-6">
            <nav class="-mb-px flex space-x-8" dusk="tabs-navigation">
                <button
                    wire:click="setTab('analyze')"
                    wire:loading.attr="disabled"
                    wire:target="setTab"
                    dusk="tab-analyze"
                    class="@if($activeTab === 'analyze') border-b-4 border-cyan-500 text-cyan-400 bg-slate-800/50 @else border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500 @endif whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="setTab">📊 Analyze Case</span>
                    <span wire:loading wire:target="setTab" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-cyan-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                </button>
                <button
                    wire:click="setTab('statistics')"
                    wire:loading.attr="disabled"
                    wire:target="setTab"
                    dusk="tab-statistics"
                    class="@if($activeTab === 'statistics') border-b-4 border-cyan-500 text-cyan-400 bg-slate-800/50 @else border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500 @endif whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="setTab">📈 Statistics</span>
                    <span wire:loading wire:target="setTab" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-cyan-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                </button>
                <button
                    wire:click="setTab('compare')"
                    wire:loading.attr="disabled"
                    wire:target="setTab"
                    dusk="tab-compare"
                    class="@if($activeTab === 'compare') border-b-4 border-cyan-500 text-cyan-400 bg-slate-800/50 @else border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500 @endif whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="setTab">🔄 Compare Regions</span>
                    <span wire:loading wire:target="setTab" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-cyan-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                </button>
            </nav>
        </div>

        {{-- Analyze Tab --}}
        @if ($activeTab === 'analyze')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" dusk="analyze-tab-content">
                {{-- Input Form --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl">
                    <h2 class="text-2xl font-bold mb-4 text-cyan-400">Case Analysis</h2>

                    {{-- Case Selector --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Select Case
                        </label>
                        <select
                            wire:model="selectedCaseId"
                            dusk="case-selector"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            @foreach ($cases as $case)
                                <option value="{{ $case->id }}">
                                    {{ $case->case_number }} - {{ Str::limit($case->title, 50) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($selectedTopic === 'drug_charge_severity')
                        {{-- Drug Type --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Drug Type
                            </label>
                            <select
                                wire:model="drugType"
                                dusk="drug-type-selector"
                                class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                @foreach ($drugTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Amount --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Amount (grams or pills)
                            </label>
                            <input
                                type="number"
                                wire:model="amount"
                                step="0.1"
                                dusk="amount-input"
                                class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                            />
                            @error('amount') <span class="text-red-400 text-sm" dusk="amount-error">{{ $message }}</span> @enderror
                        </div>

                        {{-- Charged As --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Charged As
                            </label>
                            <select
                                wire:model="chargedAs"
                                dusk="charged-as-selector"
                                class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                                @foreach ($chargeTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Evidence of Dealing --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Evidence of Dealing
                            </label>
                            <div class="space-y-2" dusk="evidence-checkboxes">
                                @foreach ($evidenceOptions as $key => $name)
                                    <label class="inline-flex items-center mr-4">
                                        <input
                                            type="checkbox"
                                            wire:model="evidenceOfDealing"
                                            value="{{ $key }}"
                                            dusk="evidence-{{ $key }}"
                                            class="rounded border-slate-600 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all duration-200"
                                        />
                                        <span class="ml-2 text-slate-300">{{ $name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Analyze Button --}}
                    <button
                        wire:click="analyzeCase"
                        wire:loading.attr="disabled"
                        wire:target="analyzeCase"
                        dusk="analyze-button"
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold shadow-md hover:shadow-xl hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105">
                        <span wire:loading.remove wire:target="analyzeCase" class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            Analyze Case
                        </span>
                        <span wire:loading wire:target="analyzeCase" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Analyzing...
                        </span>
                    </button>
                </div>

                {{-- Results --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl relative" dusk="analysis-results-panel">
                    {{-- Loading Overlay --}}
                    <div wire:loading wire:target="analyzeCase" class="absolute inset-0 bg-slate-900/80 rounded-lg flex items-center justify-center z-10">
                        <div class="text-center">
                            <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-slate-300 font-medium">Analyzing case...</p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-cyan-400">Analysis Results</h2>
                        @if ($analysisResult)
                            <button
                                wire:click="resetAnalysis"
                                wire:loading.attr="disabled"
                                wire:target="resetAnalysis"
                                dusk="clear-analysis-button"
                                class="px-4 py-2 text-sm text-white bg-gradient-to-r from-gray-500 to-gray-600 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg">
                                <span wire:loading.remove wire:target="resetAnalysis">Clear</span>
                                <span wire:loading wire:target="resetAnalysis">Clearing...</span>
                            </button>
                        @endif
                    </div>

                    @if ($analysisResult)
                        {{-- Overcharge Detection --}}
                        <div class="mb-4 p-4 rounded-lg @if($analysisResult['overcharge_detected']) bg-gradient-to-r from-red-900/20 to-red-900/10 @else bg-gradient-to-r from-green-900/20 to-green-900/10 @endif transition-all duration-300" dusk="overcharge-detection">
                            <div class="flex items-center mb-2">
                                @if ($analysisResult['overcharge_detected'])
                                    <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <span class="text-xl font-bold text-red-400" dusk="overcharge-status">Overcharge Detected!</span>
                                @else
                                    <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-xl font-bold text-green-400" dusk="overcharge-status">No Overcharge</span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-300">
                                Severity: <strong dusk="overcharge-severity">{{ $analysisResult['overcharge_severity'] }}/100</strong>
                            </p>
                        </div>

                        {{-- Threshold Analysis --}}
                        <div class="mb-4 p-4 bg-gradient-to-r from-blue-900/20 to-indigo-900/10 rounded-lg transition-all duration-300" dusk="threshold-analysis">
                            <h3 class="font-bold mb-2 text-white">Threshold Analysis</h3>
                            <div class="text-sm space-y-1 text-slate-300">
                                <p><strong>Amount:</strong> <span dusk="actual-amount">{{ $analysisResult['threshold_analysis']['actual_amount'] ?? 'N/A' }}</span></p>
                                <p><strong>Threshold:</strong> <span dusk="threshold-amount">{{ $analysisResult['threshold_analysis']['threshold_amount'] ?? 'N/A' }}</span></p>
                                <p><strong>Percentage:</strong> <span dusk="threshold-percentage">{{ $analysisResult['threshold_analysis']['percentage_of_threshold'] ?? 'N/A' }}%</span></p>
                                <p class="mt-2 text-slate-300" dusk="threshold-analysis-text">{{ $analysisResult['threshold_analysis']['analysis'] ?? '' }}</p>
                            </div>
                        </div>

                        {{-- Patterns --}}
                        @if (!empty($analysisResult['overcharging_patterns']))
                            <div class="mb-4" dusk="overcharging-patterns">
                                <h3 class="font-bold mb-2 text-white">Detected Patterns</h3>
                                <div class="space-y-2">
                                    @foreach ($analysisResult['overcharging_patterns'] as $index => $pattern)
                                        <div class="p-3 bg-gradient-to-r from-yellow-900/20 to-orange-900/10 border-l-4 border-yellow-600 rounded transition-all duration-300 hover:shadow-md" dusk="pattern-{{ $index }}">
                                            <p class="font-semibold text-sm text-slate-200">{{ $pattern['description'] }}</p>
                                            <p class="text-xs text-slate-400 mt-1">Severity: {{ $pattern['severity'] }}/100</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Defense Strategies --}}
                        @if (!empty($analysisResult['defense_strategy']))
                            <div class="mb-4" dusk="defense-strategies">
                                <h3 class="font-bold mb-2 text-white">Defense Strategies</h3>
                                <div class="space-y-2">
                                    @foreach ($analysisResult['defense_strategy'] as $index => $strategy)
                                        <div class="p-3 bg-gradient-to-r from-green-900/20 to-emerald-900/10 border-l-4 border-green-600 rounded transition-all duration-300 hover:shadow-md" dusk="strategy-{{ $index }}">
                                            <p class="font-semibold text-sm text-slate-200">{{ $strategy['title'] }}</p>
                                            <p class="text-xs text-slate-400 mt-1">Priority: {{ $strategy['priority'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Recommended Charge --}}
                        <div class="p-4 bg-gradient-to-r from-indigo-900/20 to-purple-900/10 rounded-lg transition-all duration-300" dusk="recommended-charge">
                            <h3 class="font-bold mb-2 text-white">Recommended Charge</h3>
                            <p class="text-lg font-semibold text-cyan-400" dusk="recommended-charge-value">{{ $analysisResult['recommended_charge'] }}</p>
                        </div>
                    @else
                        <p class="text-slate-500 text-center py-8" dusk="no-results-message">
                            No results yet. Analyze a case to see results.
                        </p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Statistics Tab --}}
        @if ($activeTab === 'statistics')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" dusk="statistics-tab-content">
                {{-- Input Form --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl">
                    <h2 class="text-2xl font-bold mb-4 text-cyan-400">Get Statistics</h2>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Year
                        </label>
                        <input
                            type="number"
                            wire:model="statsYear"
                            dusk="stats-year-input"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                        />
                        @error('statsYear') <span class="text-red-400 text-sm" dusk="stats-year-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Region
                        </label>
                        <select
                            wire:model="statsRegion"
                            dusk="stats-region-selector"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            @foreach ($regions as $region)
                                <option value="{{ $region }}">{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        wire:click="getStatistics"
                        wire:loading.attr="disabled"
                        wire:target="getStatistics"
                        dusk="get-statistics-button"
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold shadow-md hover:shadow-xl hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105">
                        <span wire:loading.remove wire:target="getStatistics" class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Get Statistics
                        </span>
                        <span wire:loading wire:target="getStatistics" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                </div>

                {{-- Results --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl relative" dusk="statistics-results-panel">
                    {{-- Loading Overlay --}}
                    <div wire:loading wire:target="getStatistics" class="absolute inset-0 bg-slate-900/80 rounded-lg flex items-center justify-center z-10">
                        <div class="text-center">
                            <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-slate-300 font-medium">Loading statistics...</p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-cyan-400">Statistics Results</h2>
                        @if ($statisticsResult)
                            <button
                                wire:click="resetStatistics"
                                wire:loading.attr="disabled"
                                wire:target="resetStatistics"
                                dusk="clear-statistics-button"
                                class="px-4 py-2 text-sm text-white bg-gradient-to-r from-gray-500 to-gray-600 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg">
                                <span wire:loading.remove wire:target="resetStatistics">Clear</span>
                                <span wire:loading wire:target="resetStatistics">Clearing...</span>
                            </button>
                        @endif
                    </div>

                    @if ($statisticsResult)
                        <div class="space-y-4">
                            <div class="p-4 bg-gradient-to-r from-slate-700/30 to-slate-700/20 rounded-lg transition-all duration-300 hover:shadow-md" dusk="total-cases">
                                <p class="text-sm text-slate-400">Total Cases</p>
                                <p class="text-3xl font-bold text-cyan-400" dusk="total-cases-count">{{ $statisticsResult['total_cases'] ?? 0 }}</p>
                            </div>

                            @if (isset($statisticsResult['overcharged_count']))
                                <div class="p-4 bg-gradient-to-r from-red-900/20 to-red-900/10 rounded-lg transition-all duration-300 hover:shadow-md" dusk="overcharged-cases">
                                    <p class="text-sm text-slate-400">Overcharged Cases</p>
                                    <p class="text-3xl font-bold text-red-600" dusk="overcharged-count">{{ $statisticsResult['overcharged_count'] }}</p>
                                    <p class="text-sm text-slate-400 mt-1" dusk="overcharge-percentage">({{ $statisticsResult['overcharge_percentage'] }}%)</p>
                                </div>
                            @endif

                            @if (!empty($statisticsResult['by_drug_type']))
                                <div class="p-4 bg-gradient-to-r from-blue-900/20 to-indigo-900/10 rounded-lg transition-all duration-300 hover:shadow-md" dusk="by-drug-type">
                                    <h3 class="font-bold mb-2 text-white">By Drug Type</h3>
                                    <div class="space-y-1 text-slate-300">
                                        @foreach ($statisticsResult['by_drug_type'] as $drug => $count)
                                            <div class="flex justify-between" dusk="drug-type-{{ $drug }}">
                                                <span>{{ ucfirst($drug) }}</span>
                                                <span class="font-semibold">{{ $count }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (!empty($statisticsResult['alarming_findings']))
                                <div class="p-4 bg-gradient-to-r from-yellow-900/20 to-orange-900/10 border-l-4 border-yellow-600 rounded transition-all duration-300 hover:shadow-md" dusk="alarming-findings">
                                    <h3 class="font-bold mb-2 text-white">⚠️ Alarming Findings</h3>
                                    @foreach ($statisticsResult['alarming_findings'] as $index => $finding)
                                        <p class="text-sm text-slate-300" dusk="finding-{{ $index }}">{{ $finding }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <div class="p-4 bg-gradient-to-r from-slate-700/40 to-slate-700/30 rounded transition-all duration-300" dusk="stats-status">
                                <p class="text-xs text-slate-400">Status: {{ $statisticsResult['status'] ?? 'unknown' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-slate-500 text-center py-8" dusk="no-statistics-message">
                            No statistics yet. Get statistics to see results.
                        </p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Compare Tab --}}
        @if ($activeTab === 'compare')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" dusk="compare-tab-content">
                {{-- Input Form --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl">
                    <h2 class="text-2xl font-bold mb-4 text-cyan-400">Compare Regions</h2>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Region 1
                        </label>
                        <select
                            wire:model="region1"
                            dusk="region1-selector"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            @foreach ($regions as $region)
                                <option value="{{ $region }}">{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Region 2
                        </label>
                        <select
                            wire:model="region2"
                            dusk="region2-selector"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            @foreach ($regions as $region)
                                <option value="{{ $region }}">{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Year
                        </label>
                        <input
                            type="number"
                            wire:model="comparisonYear"
                            dusk="comparison-year-input"
                            class="block w-full px-3 py-2 border border-slate-600 rounded-md bg-slate-800 text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                        />
                        @error('comparisonYear') <span class="text-red-400 text-sm" dusk="comparison-year-error">{{ $message }}</span> @enderror
                    </div>

                    <button
                        wire:click="compareRegions"
                        wire:loading.attr="disabled"
                        wire:target="compareRegions"
                        dusk="compare-regions-button"
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold shadow-md hover:shadow-xl hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105">
                        <span wire:loading.remove wire:target="compareRegions" class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Compare Regions
                        </span>
                        <span wire:loading wire:target="compareRegions" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Comparing...
                        </span>
                    </button>
                </div>

                {{-- Results --}}
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 shadow-xl rounded-lg p-6 transition-all duration-300 hover:shadow-xl relative" dusk="comparison-results-panel">
                    {{-- Loading Overlay --}}
                    <div wire:loading wire:target="compareRegions" class="absolute inset-0 bg-slate-900/80 rounded-lg flex items-center justify-center z-10">
                        <div class="text-center">
                            <svg class="animate-spin h-12 w-12 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-slate-300 font-medium">Comparing regions...</p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-cyan-400">Comparison Results</h2>
                        @if ($comparisonResult)
                            <button
                                wire:click="resetComparison"
                                wire:loading.attr="disabled"
                                wire:target="resetComparison"
                                dusk="clear-comparison-button"
                                class="px-4 py-2 text-sm text-white bg-gradient-to-r from-gray-500 to-gray-600 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg">
                                <span wire:loading.remove wire:target="resetComparison">Clear</span>
                                <span wire:loading wire:target="resetComparison">Clearing...</span>
                            </button>
                        @endif
                    </div>

                    @if ($comparisonResult)
                        <div class="space-y-4">
                            {{-- Worse Region --}}
                            <div class="p-4 bg-gradient-to-r from-red-900/20 to-red-900/10 border-l-4 border-red-600 rounded-lg transition-all duration-300 hover:shadow-md" dusk="worse-region">
                                <h3 class="font-bold text-red-400 mb-2">Worse Region</h3>
                                <p class="text-2xl font-bold text-red-300" dusk="worse-region-name">{{ $comparisonResult['worse_region']['worse_region'] ?? 'Unknown' }}</p>
                                <p class="text-sm text-slate-300 mt-2" dusk="worse-region-analysis">{{ $comparisonResult['worse_region']['analysis'] ?? '' }}</p>
                            </div>

                            {{-- Region Statistics --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-gradient-to-r from-blue-900/20 to-indigo-900/10 rounded-lg transition-all duration-300 hover:shadow-md" dusk="region1-stats">
                                    <h4 class="font-bold mb-2 text-white" dusk="region1-name">{{ $comparisonResult['region1']['name'] }}</h4>
                                    @if (isset($comparisonResult['region1']['statistics']['overcharge_percentage']))
                                        <p class="text-xl font-semibold text-cyan-400" dusk="region1-percentage">{{ $comparisonResult['region1']['statistics']['overcharge_percentage'] }}%</p>
                                        <p class="text-xs text-slate-400">Overcharge Rate</p>
                                    @endif
                                </div>

                                <div class="p-4 bg-gradient-to-r from-blue-900/20 to-indigo-900/10 rounded-lg transition-all duration-300 hover:shadow-md" dusk="region2-stats">
                                    <h4 class="font-bold mb-2 text-white" dusk="region2-name">{{ $comparisonResult['region2']['name'] }}</h4>
                                    @if (isset($comparisonResult['region2']['statistics']['overcharge_percentage']))
                                        <p class="text-xl font-semibold text-cyan-400" dusk="region2-percentage">{{ $comparisonResult['region2']['statistics']['overcharge_percentage'] }}%</p>
                                        <p class="text-xs text-slate-400">Overcharge Rate</p>
                                    @endif
                                </div>
                            </div>

                            {{-- AI Analysis --}}
                            @if (isset($comparisonResult['analysis']))
                                <div class="p-4 bg-gradient-to-r from-slate-700/30 to-slate-700/20 rounded-lg transition-all duration-300 hover:shadow-md" dusk="comparison-analysis">
                                    <h3 class="font-bold mb-2 text-white">Analysis</h3>
                                    <p class="text-sm text-slate-300 whitespace-pre-wrap" dusk="comparison-analysis-text">{{ $comparisonResult['analysis'] }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-slate-500 text-center py-8" dusk="no-comparison-message">
                            No comparison yet. Compare regions to see results.
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
