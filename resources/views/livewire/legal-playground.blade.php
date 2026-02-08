<div class="relative">
    {{-- Custom Animations --}}
    <style>
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-down {
            animation: fadeInDown 0.3s ease-out;
        }
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 200ms;
        }
    </style>

    {{-- Global Loading Overlay --}}
    <div wire:loading.delay class="fixed inset-0 bg-black/30 backdrop-blur-sm z-50 flex items-center justify-center transition-opacity duration-200">
        <div class="bg-gray-900 border border-blue-500/30 rounded-lg p-6 shadow-2xl">
            <div class="flex items-center space-x-3">
                <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-white font-medium">Processing...</span>
            </div>
        </div>
    </div>

    {{-- Messages --}}
    @if ($successMessage)
        <div class="mb-6 bg-green-950/50 border border-green-500/30 rounded-lg p-4 shadow-lg animate-fade-in-down">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <p class="text-green-400 font-medium">{{ $successMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($errorMessage)
        <div class="mb-6 bg-red-950/50 border border-red-500/30 rounded-lg p-4 shadow-lg animate-fade-in-down">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-red-500 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <p class="text-red-400 font-medium">{{ $errorMessage }}</p>
                    @if (str_contains($errorMessage, 'Network error'))
                        <p class="text-red-300/70 text-sm mt-1">Please check your connection</p>
                    @endif
                    @if (str_contains($errorMessage, 'Rate limit'))
                        <p class="text-red-300/70 text-sm mt-1">Please wait a moment before retrying</p>
                    @endif
                    @if (str_contains($errorMessage, 'Session expired') || str_contains($errorMessage, 'Unauthenticated'))
                        <p class="text-red-300/70 text-sm mt-1">Please log in again to continue</p>
                    @endif
                    @if (str_contains($errorMessage, 'Request in progress'))
                        <p class="text-red-300/70 text-sm mt-1">Please wait for current analysis to complete</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Case Selector --}}
    @if (empty($cases))
        <div class="mb-8 bg-red-950/30 border border-red-500/40 rounded-xl p-6 shadow-lg">
            <div class="flex items-start">
                <svg class="h-8 w-8 text-red-500 mr-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h2 class="text-red-400 font-bold text-lg mb-2">No Cases Available</h2>
                    <p class="text-gray-400 text-sm">Please create a legal case first to use the playground. You can create a case from the main application.</p>
                </div>
            </div>
        </div>
    @else
        <div class="mb-8 bg-gray-900/50 border border-gray-700/50 rounded-xl p-6 shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">
                        Select Case
                        <span class="text-gray-500 font-normal">({{ count($cases) }} available)</span>
                    </label>
                    <select
                        dusk="case-selector"
                        wire:model.live="selectedCaseId"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        @foreach ($cases as $case)
                            <option value="{{ $case['id'] }}">{{ $case['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button
                        wire:click="resetResults"
                        wire:confirm="Are you sure you want to reset all results? This cannot be undone."
                        wire:loading.attr="disabled"
                        wire:target="resetResults"
                        class="w-full md:w-auto bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-semibold px-6 py-2.5 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                        <span wire:loading.remove wire:target="resetResults">🔄 Reset All Results</span>
                        <span wire:loading wire:target="resetResults" class="flex items-center">
                            <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Resetting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Module Navigation --}}
    @if (!empty($cases))
        <div class="mb-8 bg-gray-900/50 border border-gray-700/50 rounded-xl p-4 shadow-lg">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <button
                    dusk="evidence-tab"
                    wire:click="setModule('evidence')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'evidence' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">📋</span>
                        <span>Evidence</span>
                    </span>
                    @if($activeModule === 'evidence')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-blue-400 rounded-b-lg"></div>
                    @endif
                </button>
                <button
                    dusk="recontextualize-tab"
                    wire:click="setModule('recontextualize')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'recontextualize' ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">🔄</span>
                        <span>Recontextualize</span>
                    </span>
                    @if($activeModule === 'recontextualize')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-purple-400 rounded-b-lg"></div>
                    @endif
                </button>
                <button
                    dusk="misconduct-tab"
                    wire:click="setModule('misconduct')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'misconduct' ? 'bg-red-600 text-white shadow-lg shadow-red-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">⚠️</span>
                        <span>Misconduct</span>
                    </span>
                    @if($activeModule === 'misconduct')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-red-400 rounded-b-lg"></div>
                    @endif
                </button>
                <button
                    dusk="topics-tab"
                    wire:click="setModule('topics')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'topics' ? 'bg-green-600 text-white shadow-lg shadow-green-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">📊</span>
                        <span>Topics</span>
                    </span>
                    @if($activeModule === 'topics')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-green-400 rounded-b-lg"></div>
                    @endif
                </button>
                <button
                    dusk="concepts-tab"
                    wire:click="setModule('concepts')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'concepts' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">🧠</span>
                        <span>Concepts</span>
                    </span>
                    @if($activeModule === 'concepts')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-400 rounded-b-lg"></div>
                    @endif
                </button>
                <button
                    dusk="case-analysis-tab"
                    wire:click="setModule('case_analysis')"
                    wire:loading.attr="disabled"
                    wire:target="setModule"
                    class="group relative px-4 py-3 rounded-lg font-medium text-sm transition-all duration-200 {{ $activeModule === 'case_analysis' ? 'bg-orange-600 text-white shadow-lg shadow-orange-500/50' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white' }} disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button">
                    <span class="flex items-center justify-center">
                        <span class="mr-2">📈</span>
                        <span>Case Analysis</span>
                    </span>
                    @if($activeModule === 'case_analysis')
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-orange-400 rounded-b-lg"></div>
                    @endif
                </button>
            </div>
        </div>
    @endif

    {{-- Evidence Analysis Module --}}
    @if ($activeModule === 'evidence')
        <div dusk="evidence-panel" class="bg-gradient-to-br from-blue-950/30 to-indigo-950/30 border border-blue-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">📋</span>
                    Evidence Analysis Module
                </h2>
                <p class="text-gray-400 text-sm">
                    Analyze evidence for admissibility challenges under Croatian law (ZKP, Ustav RH)
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Evidence Type</label>
                    <select
                        wire:model.live="evidenceType"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        @foreach ($evidenceTypes as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Evidence Description</label>
                    <textarea
                        dusk="evidence-description"
                        wire:model.defer="evidenceDescription"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 resize-none"
                        rows="5"
                        placeholder="Describe the evidence... (e.g., 'SMS message saying I'll get the stuff tonight')"></textarea>
                    @error('evidenceDescription')
                        <p class="mt-2 text-red-400 text-sm flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        dusk="analyze-button"
                        wire:click="analyzeEvidence"
                        wire:loading.attr="disabled"
                        wire:target="analyzeEvidence"
                        class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                        <span wire:loading.remove wire:target="analyzeEvidence" class="flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Analyze Evidence
                        </span>
                        <span wire:loading wire:target="analyzeEvidence" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Analyzing...
                        </span>
                    </button>

                    @if ($showRetryButton)
                        <button
                            dusk="retry-button"
                            wire:click="retry"
                            wire:loading.attr="disabled"
                            wire:target="retry"
                            class="bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center">
                            <span wire:loading.remove wire:target="retry" class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Retry
                            </span>
                            <span wire:loading wire:target="retry" class="flex items-center">
                                <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Retrying...
                            </span>
                        </button>
                    @endif
                </div>
            </div>

            @if ($evidenceAnalysisResult)
                <details open style="margin-top: 24px;">
                    <summary>📊 Analysis Results</summary>
                    <div class="detail-content">
                        <ul class="seg-list">
                            <li class="seg">
                                <div class="head">
                                    <span class="chip info">Evidence Type</span>
                                    <span>{{ $evidenceAnalysisResult['type'] ?? 'Unknown' }}</span>
                                </div>
                                @if (isset($evidenceAnalysisResult['analysis']))
                                    <div class="txt">{{ $evidenceAnalysisResult['analysis'] }}</div>
                                @endif
                            </li>

                            @if (isset($evidenceAnalysisResult['constitutional_violations']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip warn">Constitutional Violations</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($evidenceAnalysisResult['constitutional_violations'] as $violation)
                                            <div style="margin-top: 8px;">
                                                <strong>{{ $violation['article'] ?? 'Unknown' }}</strong>
                                                <br>{{ $violation['description'] ?? '' }}
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            @if (isset($evidenceAnalysisResult['suppression_grounds']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip success">Suppression Grounds</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($evidenceAnalysisResult['suppression_grounds'] as $ground)
                                            <div style="margin-top: 8px;">• {{ $ground }}</div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- Evidence Recontextualization Module --}}
    @if ($activeModule === 'recontextualize')
        <div dusk="recontextualize-panel" class="bg-gradient-to-br from-purple-950/30 to-indigo-950/30 border border-purple-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">🔄</span>
                    Evidence Recontextualization Module
                </h2>
                <p class="text-gray-400 text-sm">
                    Counter selective presentation by revealing full context (ZKP Čl. 9 - Objektivnost)
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Prosecution's Selective Presentation</label>
                    <textarea
                        dusk="prosecution-evidence"
                        wire:model.defer="prosecutionEvidence"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 resize-none"
                        rows="3"
                        placeholder="What the prosecutor showed... (e.g., 'I'll get the stuff tonight')"></textarea>
                    @error('prosecutionEvidence')
                        <p class="mt-2 text-red-400 text-sm flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Full Context / Complete Evidence</label>
                    <textarea
                        dusk="full-content"
                        wire:model.defer="fullContent"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 resize-none"
                        rows="4"
                        placeholder="The full message/context... (e.g., 'Full conversation: Can you pick up groceries? Sure, I'll get the stuff tonight from the store')"></textarea>
                    @error('fullContent')
                        <p class="mt-2 text-red-400 text-sm flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <button
                wire:click="recontextualizeEvidence"
                wire:loading.attr="disabled"
                wire:target="recontextualizeEvidence"
                class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center mt-6">
                <span wire:loading.remove wire:target="recontextualizeEvidence" class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Recontextualize Evidence
                </span>
                <span wire:loading wire:target="recontextualizeEvidence" class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </span>
            </button>

            @if ($recontextResult)
                <details open style="margin-top: 24px;">
                    <summary>📊 Recontextualization Results</summary>
                    <div class="detail-content">
                        <ul class="seg-list">
                            @if (isset($recontextResult['selective_presentation_detected']))
                                <li class="seg">
                                    <div class="head">
                                        @if ($recontextResult['selective_presentation_detected'])
                                            <span class="chip warn">Selective Presentation Detected!</span>
                                            <span>Severity: {{ $recontextResult['severity'] ?? 'Unknown' }}/100</span>
                                        @else
                                            <span class="chip success">No Selective Presentation</span>
                                        @endif
                                    </div>
                                </li>
                            @endif

                            @if (isset($recontextResult['defense_narrative']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip info">Defense Narrative</span>
                                        @if (isset($recontextResult['credibility_score']))
                                            <span class="chip @if($recontextResult['credibility_score'] >= 70) success @else warn @endif">
                                                Credibility: {{ $recontextResult['credibility_score'] }}/100
                                            </span>
                                        @endif
                                    </div>
                                    <div class="txt">{{ $recontextResult['defense_narrative'] }}</div>
                                </li>
                            @endif

                            @if (isset($recontextResult['omitted_context']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip warn">Omitted Context</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($recontextResult['omitted_context']['omissions'] ?? [] as $omission)
                                            <div style="margin-top: 8px;">• {{ $omission }}</div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- Prosecutorial Misconduct Module --}}
    @if ($activeModule === 'misconduct')
        <div dusk="misconduct-panel" class="bg-gradient-to-br from-red-950/30 to-orange-950/30 border border-red-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">⚠️</span>
                    Prosecutorial Misconduct Detection
                </h2>
                <p class="text-gray-400 text-sm">
                    Detect and document misconduct (ZKP Čl. 9, Zakon o Državnom odvjetništvu)
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Misconduct Type</label>
                    <select
                        wire:model.live="misconductType"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                        @foreach ($misconductTypes as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Misconduct Details</label>
                    <textarea
                        dusk="misconduct-details"
                        wire:model.defer="misconductDetails"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 resize-none"
                        rows="4"
                        placeholder="Describe the misconduct... (e.g., 'Search warrant backdated by 2 days, original timestamp shows it was created after the search')"></textarea>
                    @error('misconductDetails')
                        <p class="mt-2 text-red-400 text-sm flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mt-6">
                <button
                    wire:click="detectMisconduct"
                    wire:loading.attr="disabled"
                    wire:target="detectMisconduct"
                    class="flex-1 min-w-[200px] bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                    <span wire:loading.remove wire:target="detectMisconduct" class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Detect Misconduct
                    </span>
                    <span wire:loading wire:target="detectMisconduct" class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Analyzing...
                    </span>
                </button>

                @if ($misconductResult)
                    <button
                        wire:click="generateDismissalMotion"
                        wire:loading.attr="disabled"
                        wire:target="generateDismissalMotion"
                        class="flex-1 min-w-[200px] bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                        <span wire:loading.remove wire:target="generateDismissalMotion" class="flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Generate Dismissal Motion
                        </span>
                        <span wire:loading wire:target="generateDismissalMotion" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating...
                        </span>
                    </button>

                    <button
                        wire:click="generateComplaint"
                        wire:loading.attr="disabled"
                        wire:target="generateComplaint"
                        class="flex-1 min-w-[200px] bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-700 hover:to-amber-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                        <span wire:loading.remove wire:target="generateComplaint" class="flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Generate Complaint
                        </span>
                        <span wire:loading wire:target="generateComplaint" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating...
                        </span>
                    </button>
                @endif
            </div>

            @if ($misconductResult)
                <details open style="margin-top: 24px;">
                    <summary>📊 Misconduct Analysis</summary>
                    <div class="detail-content">
                        <ul class="seg-list">
                            <li class="seg">
                                <div class="head">
                                    <span class="chip error">Misconduct Detected</span>
                                    <span>Severity: {{ $misconductResult['severity'] ?? 'Unknown' }}/100</span>
                                </div>
                                @if (isset($misconductResult['description']))
                                    <div class="txt">{{ $misconductResult['description'] }}</div>
                                @endif
                            </li>

                            @if (isset($misconductResult['legal_violations']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip warn">Legal Violations</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($misconductResult['legal_violations'] as $violation)
                                            <div style="margin-top: 8px;">
                                                <strong>{{ $violation['article'] ?? 'Unknown' }}</strong>
                                                <br>{{ $violation['description'] ?? '' }}
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            @if (isset($misconductResult['remedies']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip success">Available Remedies</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($misconductResult['remedies'] as $remedy)
                                            <div style="margin-top: 8px;">• {{ $remedy }}</div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </details>
            @endif

            @if ($dismissalMotion)
                <details open style="margin-top: 16px;">
                    <summary>📄 Dismissal Motion (Zahtjev za odbacivanje)</summary>
                    <div class="detail-content">
                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                            <button
                                onclick="navigator.clipboard.writeText(document.getElementById('dismissal-motion-text').textContent)"
                                class="btn info"
                                style="font-size: 12px; padding: 6px 10px;">
                                📋 Copy to Clipboard
                            </button>
                            <button
                                onclick="window.print()"
                                class="btn"
                                style="font-size: 12px; padding: 6px 10px;">
                                🖨️ Print
                            </button>
                        </div>
                        <div id="dismissal-motion-text" class="txt" style="white-space: pre-wrap; font-family: monospace; font-size: 12px;">{{ $dismissalMotion['content'] ?? 'No content' }}</div>
                    </div>
                </details>
            @endif

            @if ($complaint)
                <details open style="margin-top: 16px;">
                    <summary>📝 Complaint (Prijava)</summary>
                    <div class="detail-content">
                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                            <button
                                onclick="navigator.clipboard.writeText(document.getElementById('complaint-text').textContent)"
                                class="btn info"
                                style="font-size: 12px; padding: 6px 10px;">
                                📋 Copy to Clipboard
                            </button>
                            <button
                                onclick="window.print()"
                                class="btn"
                                style="font-size: 12px; padding: 6px 10px;">
                                🖨️ Print
                            </button>
                        </div>
                        <div id="complaint-text" class="txt" style="white-space: pre-wrap; font-family: monospace; font-size: 12px;">{{ $complaint['content'] ?? 'No content' }}</div>
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- Topic Framework Module --}}
    @if ($activeModule === 'topics')
        <div dusk="topics-panel" class="bg-gradient-to-br from-green-950/30 to-emerald-950/30 border border-green-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">📊</span>
                    Topic Framework - Abuse Pattern Detection
                </h2>
                <p class="text-gray-400 text-sm">
                    Modular detection of specific abuse patterns (drug overcharging, home searches, etc.)
                </p>
            </div>

            {{-- Topic Selector --}}
            <div class="mb-6">
                <label class="block text-gray-300 text-sm font-medium mb-2">Select Topic</label>
                <select
                    dusk="topic-selector"
                    wire:model.live="selectedTopic"
                    class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                    @foreach ($topics as $key => $name)
                        <option value="{{ $key }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Drug Charge Analysis --}}
            @if ($selectedTopic === 'drug_charge_severity')
                <div class="mt-6 pt-6 border-t border-gray-700/50">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <span class="mr-2">🧪</span>
                        Drug Charge Overcharging Analysis
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">Drug Type</label>
                            <select
                                wire:model.live="drugType"
                                class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                                @foreach ($drugTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">Amount (grams/pills)</label>
                            <input
                                dusk="drug-amount"
                                type="number"
                                wire:model.defer="amount"
                                step="0.1"
                                class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            @error('amount')
                                <p class="mt-2 text-red-400 text-sm flex items-center">
                                    <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">Charged As</label>
                            <select
                                wire:model.live="chargedAs"
                                class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                                @foreach ($chargeTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-gray-300 text-sm font-medium mb-3">Evidence of Dealing (check all that apply)</label>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($evidenceOptions as $key => $name)
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model.live="evidenceOfDealing"
                                        value="{{ $key }}"
                                        class="w-4 h-4 bg-gray-800 border-gray-600 rounded text-green-600 focus:ring-2 focus:ring-green-500 focus:ring-offset-0 transition-all duration-200">
                                    <span class="text-gray-300 text-sm">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button
                        wire:click="analyzeTopic"
                        wire:loading.attr="disabled"
                        wire:target="analyzeTopic"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center mt-6">
                        <span wire:loading.remove wire:target="analyzeTopic" class="flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Analyze Drug Case
                        </span>
                        <span wire:loading wire:target="analyzeTopic" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Analyzing...
                        </span>
                    </button>

                    @if ($topicResult)
                        <details open style="margin-top: 24px;">
                            <summary>📊 Analysis Results</summary>
                            <div class="detail-content">
                                {{-- Overcharge Detection --}}
                                @if (isset($topicResult['overcharge_detected']))
                                    <div class="stats">
                                        <div class="stat">
                                            <div class="stat-label">Overcharge Status</div>
                                            <div class="stat-value" style="color: @if($topicResult['overcharge_detected']) var(--error) @else var(--success) @endif;">
                                                @if($topicResult['overcharge_detected']) ⚠️ DETECTED @else ✓ None @endif
                                            </div>
                                        </div>

                                        <div class="stat">
                                            <div class="stat-label">Severity</div>
                                            <div class="stat-value">{{ $topicResult['overcharge_severity'] ?? 0 }}<span style="font-size: 14px;">/100</span></div>
                                        </div>

                                        @if (isset($topicResult['threshold_analysis']['percentage_of_threshold']))
                                            <div class="stat">
                                                <div class="stat-label">% of Threshold</div>
                                                <div class="stat-value">{{ $topicResult['threshold_analysis']['percentage_of_threshold'] }}<span style="font-size: 14px;">%</span></div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <ul class="seg-list" style="margin-top: 16px;">
                                    {{-- Threshold Analysis --}}
                                    @if (isset($topicResult['threshold_analysis']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip info">Threshold Analysis</span>
                                                @if ($topicResult['threshold_analysis']['personal_use_likely'] ?? false)
                                                    <span class="chip success">Personal Use Likely</span>
                                                @else
                                                    <span class="chip warn">May Indicate Dealing</span>
                                                @endif
                                            </div>
                                            <div class="txt">
                                                <div>Amount: {{ $topicResult['threshold_analysis']['actual_amount'] ?? 'N/A' }}</div>
                                                <div>Threshold: {{ $topicResult['threshold_analysis']['threshold_amount'] ?? 'N/A' }}</div>
                                                <div style="margin-top: 8px;">{{ $topicResult['threshold_analysis']['analysis'] ?? '' }}</div>
                                            </div>
                                        </li>
                                    @endif

                                    {{-- Patterns --}}
                                    @if (!empty($topicResult['overcharging_patterns']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip warn">Detected Patterns</span>
                                                <span class="chip">{{ count($topicResult['overcharging_patterns']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($topicResult['overcharging_patterns'] as $pattern)
                                                    <div style="margin-top: 12px; padding: 12px; background: rgba(234,179,8,0.1); border-left: 3px solid var(--warn); border-radius: 6px;">
                                                        <div style="font-weight: 600;">{{ $pattern['description'] }}</div>
                                                        <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                                            Severity: {{ $pattern['severity'] }}/100 | {{ $pattern['legal_basis'] ?? '' }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    {{-- Defense Strategies --}}
                                    @if (!empty($topicResult['defense_strategy']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip success">Defense Strategies</span>
                                                <span class="chip">{{ count($topicResult['defense_strategy']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($topicResult['defense_strategy'] as $strategy)
                                                    <div style="margin-top: 12px; padding: 12px; background: rgba(34,197,94,0.1); border-left: 3px solid var(--success); border-radius: 6px;">
                                                        <div style="font-weight: 600;">{{ $strategy['title'] }}</div>
                                                        <div style="font-size: 12px; margin-top: 4px;">{{ $strategy['description'] }}</div>
                                                        <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                                            Priority: {{ $strategy['priority'] }} | {{ $strategy['legal_basis'] ?? '' }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    {{-- Recommended Charge --}}
                                    @if (isset($topicResult['recommended_charge']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip info">Recommended Charge</span>
                                            </div>
                                            <div class="txt">
                                                <div style="font-size: 18px; font-weight: 600; color: var(--accent);">
                                                    {{ $topicResult['recommended_charge'] }}
                                                </div>
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </details>
                    @endif

                    {{-- Regional Comparison --}}
                    <div class="mt-8 pt-6 border-t border-gray-700/50">
                        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <span class="mr-2">🗺️</span>
                            Regional Comparison
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2">Region 1</label>
                                <select
                                    wire:model.live="region1"
                                    class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                    @foreach ($regions as $region)
                                        <option value="{{ $region }}">{{ $region }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2">Region 2</label>
                                <select
                                    wire:model.live="region2"
                                    class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                    @foreach ($regions as $region)
                                        <option value="{{ $region }}">{{ $region }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2">Year</label>
                                <input
                                    type="number"
                                    wire:model.defer="comparisonYear"
                                    class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                @error('comparisonYear')
                                    <p class="mt-2 text-red-400 text-sm flex items-center">
                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <button
                            wire:click="compareRegions"
                            wire:loading.attr="disabled"
                            wire:target="compareRegions"
                            class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center mt-6">
                            <span wire:loading.remove wire:target="compareRegions" class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Compare Regions
                            </span>
                            <span wire:loading wire:target="compareRegions" class="flex items-center">
                                <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Comparing...
                            </span>
                        </button>

                        @if ($comparisonResult)
                            <details open style="margin-top: 24px;">
                                <summary>📊 Regional Comparison Results</summary>
                                <div class="detail-content">
                                    {{-- Worse Region --}}
                                    @if (isset($comparisonResult['worse_region']))
                                        <div style="padding: 16px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 10px; margin-bottom: 16px;">
                                            <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">WORSE REGION</div>
                                            <div style="font-size: 24px; font-weight: 700; color: var(--error);">
                                                {{ $comparisonResult['worse_region']['worse_region'] ?? 'Unknown' }}
                                            </div>
                                            @if (isset($comparisonResult['worse_region']['analysis']))
                                                <div style="margin-top: 8px; font-size: 14px;">
                                                    {{ $comparisonResult['worse_region']['analysis'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Side by Side Stats --}}
                                    <div class="stats">
                                        <div class="stat">
                                            <div class="stat-label">{{ $comparisonResult['region1']['name'] ?? 'Region 1' }}</div>
                                            <div class="stat-value">
                                                {{ $comparisonResult['region1']['statistics']['overcharge_percentage'] ?? 0 }}<span style="font-size: 14px;">%</span>
                                            </div>
                                        </div>

                                        <div class="stat">
                                            <div class="stat-label">{{ $comparisonResult['region2']['name'] ?? 'Region 2' }}</div>
                                            <div class="stat-value">
                                                {{ $comparisonResult['region2']['statistics']['overcharge_percentage'] ?? 0 }}<span style="font-size: 14px;">%</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- AI Analysis --}}
                                    @if (isset($comparisonResult['analysis']))
                                        <div class="seg">
                                            <div class="head">
                                                <span class="chip info">AI Analysis</span>
                                            </div>
                                            <div class="txt" style="white-space: pre-wrap;">{{ $comparisonResult['analysis'] }}</div>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Legal Concept Analysis Module --}}
    @if ($activeModule === 'concepts')
        <div dusk="concepts-panel" class="bg-gradient-to-br from-indigo-950/30 to-purple-950/30 border border-indigo-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">🧠</span>
                    Legal Concept Analysis Module
                </h2>
                <p class="text-gray-400 text-sm">
                    Analyze legal concepts with AI: get definitions, find related concepts, discover precedents, and analyze doctrine origins.
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Concept to Analyze</label>
                    <input
                        dusk="concept-query"
                        wire:model.defer="conceptQuery"
                        type="text"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                        placeholder="Enter legal concept (e.g., 'proportionality', 'due process', 'illegal search')">
                    @error('conceptQuery')
                        <p class="mt-2 text-red-400 text-sm flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Operation</label>
                    <select
                        dusk="concept-operation"
                        wire:model.live="conceptOperation"
                        class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                        @foreach ($conceptOperations as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button
                wire:click="analyzeConcept"
                wire:loading.attr="disabled"
                wire:target="analyzeConcept"
                class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center mt-6">
                <span wire:loading.remove wire:target="analyzeConcept" class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    Analyze Concept
                </span>
                <span wire:loading wire:target="analyzeConcept" class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Analyzing...
                </span>
            </button>

            @if ($conceptResult)
                <details open style="margin-top: 24px;">
                    <summary>📊 Concept Analysis Results</summary>
                    <div class="detail-content">
                        <ul class="seg-list">
                            <li class="seg">
                                <div class="head">
                                    <span class="chip info">Concept</span>
                                    <span>{{ $conceptResult['concept'] ?? 'Unknown' }}</span>
                                </div>
                            </li>

                            {{-- Define Operation Results --}}
                            @if (isset($conceptResult['definition']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip success">Definition (Croatian)</span>
                                    </div>
                                    <div class="txt">{{ $conceptResult['definition'] }}</div>
                                </li>
                            @endif

                            @if (isset($conceptResult['legal_basis']) && !empty($conceptResult['legal_basis']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip info">Legal Basis</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($conceptResult['legal_basis'] as $basis)
                                            <div style="margin-top: 4px;">• {{ $basis }}</div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            @if (isset($conceptResult['examples']) && !empty($conceptResult['examples']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip">Example Cases</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($conceptResult['examples'] as $example)
                                            <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                                <strong>{{ $example['court'] ?? 'Unknown Court' }}</strong>
                                                @if (isset($example['date']))
                                                    <span style="color: var(--muted);"> - {{ $example['date'] }}</span>
                                                @endif
                                                <div style="margin-top: 4px;">{{ $example['excerpt'] ?? '' }}</div>
                                                @if (isset($example['relevance_score']))
                                                    <div style="margin-top: 4px; font-size: 0.9em; color: var(--muted);">
                                                        Relevance: {{ $example['relevance_score'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            {{-- Related Concepts Results --}}
                            @if (isset($conceptResult['related_concepts']) && !empty($conceptResult['related_concepts']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip success">Related Concepts</span>
                                        <span class="chip">{{ $conceptResult['count'] ?? 0 }} found</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($conceptResult['related_concepts'] as $related)
                                            <div style="margin-top: 4px;">
                                                • <strong>{{ $related['concept'] }}</strong>
                                                <span style="color: var(--muted);"> (Relevance: {{ $related['relevance_score'] }})</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            {{-- Precedents Results --}}
                            @if (isset($conceptResult['precedents']) && !empty($conceptResult['precedents']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip warn">Precedent Cases</span>
                                        <span class="chip">{{ $conceptResult['count'] ?? 0 }} found</span>
                                    </div>
                                    <div class="txt">
                                        @foreach ($conceptResult['precedents'] as $precedent)
                                            <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                                <strong>{{ $precedent['court'] ?? 'Unknown Court' }}</strong>
                                                @if (isset($precedent['date']))
                                                    <span style="color: var(--muted);"> - {{ $precedent['date'] }}</span>
                                                @endif
                                                @if (isset($precedent['decision_id']))
                                                    <div style="margin-top: 4px; font-size: 0.9em; color: var(--muted);">
                                                        ID: {{ $precedent['decision_id'] }}
                                                    </div>
                                                @endif
                                                <div style="margin-top: 4px;">{{ $precedent['excerpt'] ?? '' }}</div>
                                                @if (isset($precedent['relevance_score']))
                                                    <div style="margin-top: 4px; font-size: 0.9em; color: var(--muted);">
                                                        Relevance: {{ $precedent['relevance_score'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endif

                            {{-- Doctrine Analysis Results --}}
                            @if (isset($conceptResult['doctrine_type']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip info">Doctrine Type</span>
                                        <span>{{ ucfirst($conceptResult['doctrine_type']) }}</span>
                                    </div>
                                </li>
                            @endif

                            @if (isset($conceptResult['origin']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip">Origin</span>
                                    </div>
                                    <div class="txt">{{ $conceptResult['origin'] }}</div>
                                </li>
                            @endif

                            @if (isset($conceptResult['application']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip success">Application in Croatian Law</span>
                                    </div>
                                    <div class="txt">{{ $conceptResult['application'] }}</div>
                                </li>
                            @endif

                            @if (isset($conceptResult['exceptions']) && !empty($conceptResult['exceptions']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip warn">Exceptions</span>
                                    </div>
                                    <div class="txt">
                                        @if (is_array($conceptResult['exceptions']))
                                            @foreach ($conceptResult['exceptions'] as $exception)
                                                <div style="margin-top: 4px;">• {{ $exception }}</div>
                                            @endforeach
                                        @else
                                            {{ $conceptResult['exceptions'] }}
                                        @endif
                                    </div>
                                </li>
                            @endif

                            @if (isset($conceptResult['croatian_equivalent']))
                                <li class="seg">
                                    <div class="head">
                                        <span class="chip info">Croatian Equivalent</span>
                                    </div>
                                    <div class="txt">{{ $conceptResult['croatian_equivalent'] }}</div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- Case Analysis Module --}}
    @if ($activeModule === 'case_analysis')
        <div dusk="case-analysis-panel" class="bg-gradient-to-br from-orange-950/30 to-red-950/30 border border-orange-800/30 rounded-xl p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white mb-2 flex items-center">
                    <span class="mr-3">📈</span>
                    Case Analysis Module
                </h2>
                <p class="text-gray-400 text-sm">
                    Analyze case strength, risks, timeline, and evidence quality using AI-powered insights.
                </p>
            </div>

            <div class="mb-6">
                <label class="block text-gray-300 text-sm font-medium mb-2">Analysis Type</label>
                <select
                    dusk="analysis-type"
                    wire:model.live="caseAnalysisOperation"
                    class="w-full bg-gray-800 border border-gray-600 text-gray-100 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200">
                    @foreach ($caseAnalysisOperations as $key => $name)
                        <option value="{{ $key }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3 mt-6">
                <button
                    wire:click="analyzeCase"
                    wire:loading.attr="disabled"
                    wire:target="analyzeCase"
                    class="flex-1 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center justify-center">
                    <span wire:loading.remove wire:target="analyzeCase" class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Analyze Case
                    </span>
                    <span wire:loading wire:target="analyzeCase" class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Analyzing...
                    </span>
                </button>

                <button
                    wire:click="resetResults"
                    wire:loading.attr="disabled"
                    wire:target="resetResults"
                    class="bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg flex items-center">
                    <span wire:loading.remove wire:target="resetResults" class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Clear
                    </span>
                    <span wire:loading wire:target="resetResults" class="flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Clearing...
                    </span>
                </button>
            </div>

            @if ($caseAnalysisResult && !isset($caseAnalysisResult['error']))
                <div style="margin-top: 16px; padding: 12px; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); border-radius: 10px;">
                    <span style="color: #86efac; font-weight: 600;">✓ Analysis Complete</span>
                </div>
                <div style="margin-top: 24px;" x-data="{ generatingPdf: false }">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 16px;">Analysis Results</h3>
                        <button
                            @click="generatingPdf = true; setTimeout(() => { window.print(); generatingPdf = false; }, 100)"
                            class="btn info"
                            style="font-size: 12px; padding: 6px 10px;">
                            <span x-show="!generatingPdf">📄 Export to PDF</span>
                            <span x-show="generatingPdf">Generating PDF...</span>
                        </button>
                    </div>

                    {{-- Strength Analysis Results --}}
                    @if ($caseAnalysisOperation === 'strength' && isset($caseAnalysisResult['strength_score']))
                        <details open style="margin-top: 16px;">
                            <summary>💪 Case Strength Analysis</summary>
                            <div class="detail-content">
                                <div class="stats">
                                    <div class="stat">
                                        <div class="stat-label">Strength Score</div>
                                        <div dusk="strength-score" class="stat-value" style="color: @if($caseAnalysisResult['strength_score'] >= 70) var(--success) @elseif($caseAnalysisResult['strength_score'] >= 50) var(--warn) @else var(--error) @endif;">
                                            {{ $caseAnalysisResult['strength_score'] }}<span style="font-size: 14px;">/100</span>
                                        </div>
                                    </div>
                                </div>

                                <ul class="seg-list" style="margin-top: 16px;">
                                    @if (!empty($caseAnalysisResult['strengths']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip success">Case Strengths</span>
                                                <span class="chip">{{ count($caseAnalysisResult['strengths']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['strengths'] as $strength)
                                                    <div style="margin-top: 8px;">• {{ $strength }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($caseAnalysisResult['weaknesses']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip warn">Case Weaknesses</span>
                                                <span class="chip">{{ count($caseAnalysisResult['weaknesses']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['weaknesses'] as $weakness)
                                                    <div style="margin-top: 8px;">• {{ $weakness }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    @if (isset($caseAnalysisResult['overall_assessment']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip info">Overall Assessment</span>
                                            </div>
                                            <div class="txt">{{ $caseAnalysisResult['overall_assessment'] }}</div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </details>
                    @endif

                    {{-- Risk Analysis Results --}}
                    @if ($caseAnalysisOperation === 'risk' && isset($caseAnalysisResult['risk_level']))
                        <details open style="margin-top: 16px;">
                            <summary>⚠️ Risk Assessment</summary>
                            <div class="detail-content">
                                <div class="stats">
                                    <div class="stat">
                                        <div class="stat-label">Risk Level</div>
                                        <div dusk="risk-level" class="stat-value" style="color: @if($caseAnalysisResult['risk_level'] === 'high') var(--error) @elseif($caseAnalysisResult['risk_level'] === 'medium') var(--warn) @else var(--success) @endif;">
                                            {{ ucfirst($caseAnalysisResult['risk_level']) }}
                                        </div>
                                    </div>
                                </div>

                                <ul class="seg-list" style="margin-top: 16px;">
                                    @if (!empty($caseAnalysisResult['risks']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip error">Identified Risks</span>
                                                <span class="chip">{{ count($caseAnalysisResult['risks']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['risks'] as $risk)
                                                    <div style="margin-top: 8px;">• {{ $risk }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($caseAnalysisResult['mitigation_strategies']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip success">Mitigation Strategies</span>
                                                <span class="chip">{{ count($caseAnalysisResult['mitigation_strategies']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['mitigation_strategies'] as $strategy)
                                                    <div style="margin-top: 8px;">• {{ $strategy }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </details>
                    @endif

                    {{-- Timeline Analysis Results --}}
                    @if ($caseAnalysisOperation === 'timeline' && isset($caseAnalysisResult['events']))
                        <details open style="margin-top: 16px;">
                            <summary>📅 Case Timeline</summary>
                            <div class="detail-content">
                                <ul class="seg-list">
                                    @if (!empty($caseAnalysisResult['events']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip info">Events</span>
                                                <span class="chip">{{ count($caseAnalysisResult['events']) }}</span>
                                            </div>
                                            <div dusk="timeline-events" class="txt">
                                                @foreach ($caseAnalysisResult['events'] as $event)
                                                    <div style="margin-top: 12px; padding: 12px; background: rgba(255,255,255,0.05); border-left: 3px solid var(--accent); border-radius: 6px;">
                                                        <div style="font-weight: 600;">{{ $event['event'] }}</div>
                                                        <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                                            {{ $event['date'] }} | {{ $event['type'] }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($caseAnalysisResult['key_dates']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip success">Key Dates</span>
                                                <span class="chip">{{ count($caseAnalysisResult['key_dates']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['key_dates'] as $date)
                                                    <div style="margin-top: 4px;">• {{ $date }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </details>
                    @endif

                    {{-- Evidence Analysis Results --}}
                    @if ($caseAnalysisOperation === 'evidence' && isset($caseAnalysisResult['evidence_count']))
                        <details open style="margin-top: 16px;">
                            <summary>📁 Evidence Quality Assessment</summary>
                            <div class="detail-content">
                                <div class="stats">
                                    <div class="stat">
                                        <div class="stat-label">Evidence Count</div>
                                        <div dusk="evidence-list" class="stat-value">
                                            {{ $caseAnalysisResult['evidence_count'] }}
                                        </div>
                                    </div>

                                    <div class="stat">
                                        <div class="stat-label">Evidence Quality</div>
                                        <div dusk="evidence-quality" class="stat-value" style="color: @if($caseAnalysisResult['evidence_quality'] === 'good') var(--success) @elseif($caseAnalysisResult['evidence_quality'] === 'adequate') var(--warn) @else var(--error) @endif;">
                                            {{ ucfirst($caseAnalysisResult['evidence_quality']) }}
                                        </div>
                                    </div>
                                </div>

                                <ul class="seg-list" style="margin-top: 16px;">
                                    @if (!empty($caseAnalysisResult['categories']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip info">Evidence Categories</span>
                                                <span class="chip">{{ count($caseAnalysisResult['categories']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['categories'] as $category)
                                                    <div style="margin-top: 4px;">• {{ $category }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif

                                    @if (isset($caseAnalysisResult['admissibility_issues']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip warn">Admissibility Issues</span>
                                            </div>
                                            <div class="txt">
                                                @if (empty($caseAnalysisResult['admissibility_issues']))
                                                    <div style="color: var(--success);">No admissibility issues identified</div>
                                                @else
                                                    @foreach ($caseAnalysisResult['admissibility_issues'] as $issue)
                                                        <div style="margin-top: 8px;">• {{ $issue }}</div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </li>
                                    @endif

                                    @if (!empty($caseAnalysisResult['recommendations']))
                                        <li class="seg">
                                            <div class="head">
                                                <span class="chip success">Recommendations</span>
                                                <span class="chip">{{ count($caseAnalysisResult['recommendations']) }}</span>
                                            </div>
                                            <div class="txt">
                                                @foreach ($caseAnalysisResult['recommendations'] as $recommendation)
                                                    <div style="margin-top: 8px;">• {{ $recommendation }}</div>
                                                @endforeach
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </details>
                    @endif
                </div>
            @endif

            @if (isset($caseAnalysisResult['error']))
                <div class="chip error" style="display: block; margin-top: 16px;">
                    ✗ Error: {{ $caseAnalysisResult['error'] }}
                </div>
            @endif
        </div>
    @endif
</div>
