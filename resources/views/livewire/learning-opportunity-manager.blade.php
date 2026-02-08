<div class="learning-opportunity-manager min-h-screen" style="background: var(--bg, #0b1220);" dusk="learning-opportunity-manager">
    {{-- Page Header --}}
    <x-page-header
        :breadcrumbs="breadcrumbs('learning.opportunities')"
        title="Learning Opportunities"
        subtitle="Review and provide feedback on AI learning opportunities"
    />

    <div class="p-6">
    {{-- Success Message with Fade Animation --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             class="alert alert-success mb-4"
             dusk="success-message"
             role="alert"
             aria-live="polite">
            {{ session('message') }}
        </div>
    @endif

    {{-- Filter Section --}}
    <div class="filter-section mb-6" dusk="filter-container">
        <label for="filterType" class="block text-sm font-medium text-slate-300 mb-2">
            Filter by Type:
        </label>
        <div class="relative">
            <select wire:model="filterType"
                    id="filterType"
                    class="filter-select block w-full md:w-64 px-4 py-2.5 pr-10 text-base border border-slate-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-800 text-slate-200 transition-all duration-200"
                    dusk="filter-type"
                    aria-label="Filter opportunities by type">
                <option value="">All Types</option>
                <option value="decision_discovery">Decision Discovery</option>
                <option value="precedent_analysis">Precedent Analysis</option>
            </select>
            <div wire:loading wire:target="filterType"
                 class="absolute inset-y-0 right-10 flex items-center pr-3 pointer-events-none"
                 dusk="filter-loading">
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Empty State --}}
    @if ($opportunities->isEmpty())
        <div class="empty-state text-center py-12 px-4 bg-gradient-to-br from-slate-800/50 to-slate-900/50 rounded-xl border-2 border-dashed border-slate-600"
             dusk="empty-state"
             role="status"
             aria-live="polite">
            <svg class="mx-auto h-16 w-16 text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium text-slate-300">No pending learning opportunities</p>
            <p class="text-sm text-slate-500 mt-2">Check back later or adjust your filter settings</p>
        </div>
    @else
        {{-- Loading Overlay for Opportunities List --}}
        <div class="relative">
            <div wire:loading wire:target="filterType,selectOpportunity"
                 class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm z-10 rounded-xl flex items-center justify-center"
                 dusk="opportunities-loading-overlay">
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm font-medium text-slate-300">Loading...</p>
                </div>
            </div>

            {{-- Opportunities List --}}
            <div class="opportunities-list grid grid-cols-1 gap-4 md:gap-6"
                 dusk="opportunities-list"
                 role="list"
                 aria-label="Learning opportunities">
                @foreach ($opportunities as $index => $opportunity)
                    <article class="opportunity-card group relative bg-gradient-to-br from-slate-800/50 to-slate-800/30 rounded-xl border border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 ease-in-out transform hover:-translate-y-1 overflow-hidden"
                             dusk="opportunity-card-{{ $index }}"
                             data-opportunity-id="{{ $opportunity->id }}"
                             data-opportunity-index="{{ $index }}"
                             aria-labelledby="opportunity-title-{{ $index }}">

                        {{-- Decorative gradient border on hover --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300 -z-10" style="margin: -2px; border-radius: inherit;"></div>

                        <div class="p-6">
                            {{-- Header Section --}}
                            <div class="opportunity-header mb-4 pb-4 border-b border-slate-700"
                                 dusk="opportunity-header-{{ $index }}"
                                 id="opportunity-title-{{ $index }}">
                                <div class="flex flex-wrap gap-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-900/50 text-blue-300"
                                          dusk="opportunity-type-{{ $index }}">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ ucwords(str_replace('_', ' ', $opportunity->opportunity_type)) }}
                                    </span>

                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                 {{ $opportunity->confidence_score < 0.5 ? 'bg-red-900/50 text-red-300' :
                                                    ($opportunity->confidence_score < 0.7 ? 'bg-yellow-900/50 text-yellow-300' :
                                                    'bg-green-900/50 text-green-300') }}"
                                          dusk="opportunity-confidence-{{ $index }}">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                                        </svg>
                                        Confidence: {{ number_format($opportunity->confidence_score * 100, 1) }}%
                                    </span>

                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-900/50 text-purple-300"
                                          dusk="opportunity-source-{{ $index }}">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ ucwords(str_replace('_', ' ', $opportunity->source_type)) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Details Section --}}
                            <div class="opportunity-details space-y-3 mb-4"
                                 dusk="opportunity-details-{{ $index }}">
                                @if (isset($opportunity->ai_output['id']))
                                    <div class="flex items-start" dusk="opportunity-decision-id-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">Decision ID:</span>
                                        <span class="text-sm text-slate-400 font-mono">{{ $opportunity->ai_output['id'] }}</span>
                                    </div>
                                @endif

                                @if (isset($opportunity->ai_output['decision_id']))
                                    <div class="flex items-start" dusk="opportunity-decision-id-alt-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">Decision ID:</span>
                                        <span class="text-sm text-slate-400 font-mono">{{ $opportunity->ai_output['decision_id'] }}</span>
                                    </div>
                                @endif

                                @if (isset($opportunity->ai_output['score']))
                                    <div class="flex items-start" dusk="opportunity-ai-score-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">AI Score:</span>
                                        <span class="text-sm text-slate-400">{{ $opportunity->ai_output['score'] }}</span>
                                    </div>
                                @endif

                                @if (isset($opportunity->ai_output['applicability_score']))
                                    <div class="flex items-start" dusk="opportunity-applicability-score-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">Applicability:</span>
                                        <span class="text-sm text-slate-400">{{ $opportunity->ai_output['applicability_score'] }}</span>
                                    </div>
                                @endif

                                @if (isset($opportunity->ai_output['reasoning']))
                                    <div class="flex items-start" dusk="opportunity-reasoning-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">Reasoning:</span>
                                        <span class="text-sm text-slate-400 flex-1">{{ $opportunity->ai_output['reasoning'] }}</span>
                                    </div>
                                @endif

                                @if (isset($opportunity->ai_output['topic']))
                                    <div class="flex items-start" dusk="opportunity-topic-{{ $index }}">
                                        <span class="text-sm font-semibold text-slate-300 min-w-[140px]">Topic:</span>
                                        <span class="text-sm text-slate-400">{{ $opportunity->ai_output['topic'] }}</span>
                                    </div>
                                @endif

                                @if ($opportunity->uncertainty_reason)
                                    <div class="flex items-start p-3 bg-yellow-900/20 border border-yellow-800 rounded-lg"
                                         dusk="opportunity-uncertainty-{{ $index }}">
                                        <svg class="w-5 h-5 text-yellow-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-semibold text-yellow-300 block mb-1">Uncertainty:</span>
                                            <span class="text-sm text-yellow-400">{{ $opportunity->uncertainty_reason }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Actions Section --}}
                            <div class="opportunity-actions flex gap-3" dusk="opportunity-actions-{{ $index }}">
                                <button wire:click="selectOpportunity({{ $opportunity->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="selectOpportunity"
                                        class="btn-provide-feedback flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                        dusk="provide-feedback-btn-{{ $index }}"
                                        aria-label="Provide feedback for opportunity {{ $opportunity->id }}">
                                    <span wire:loading.remove wire:target="selectOpportunity">
                                        <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Provide Feedback
                                    </span>
                                    <span wire:loading wire:target="selectOpportunity" class="inline-flex items-center">
                                        <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Opening...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @endif
    </div>

    {{-- Feedback Modal with Alpine.js Transitions --}}
    <div x-data="{ open: @entangle('selectedOpportunityId').live }"
         x-show="!!open"
         x-cloak
         @keydown.escape.window="$wire.cancelFeedback()"
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true"
         dusk="feedback-modal">

        {{-- Background Overlay --}}
        <div x-show="!!open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity"
             dusk="modal-backdrop"
             @click="$wire.cancelFeedback()">
        </div>

        {{-- Modal Content --}}
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="!!open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl"
                 dusk="feedback-modal-content"
                 @click.stop>

                {{-- Modal Header --}}
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 sm:px-8 sm:py-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl sm:text-2xl font-bold text-white flex items-center"
                            id="modal-title"
                            dusk="feedback-modal-title">
                            <svg class="w-7 h-7 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                            </svg>
                            Submit Feedback
                        </h3>
                        <button wire:click="cancelFeedback"
                                wire:loading.attr="disabled"
                                wire:target="cancelFeedback"
                                class="text-white/80 hover:text-white transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-white/50 rounded-lg p-1"
                                dusk="modal-close-button"
                                aria-label="Close modal">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-6 sm:px-8 sm:py-8">
                    {{-- Error Messages --}}
                    <div class="space-y-3 mb-6">
                        @error('feedbackData')
                            <div x-data="{ show: true }"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform scale-95"
                                 x-transition:enter-end="opacity-100 transform scale-100"
                                 class="alert-error flex items-start p-4 bg-red-900/20 border border-red-800 rounded-lg"
                                 dusk="error-feedback-data"
                                 role="alert">
                                <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-red-300">{{ $message }}</span>
                            </div>
                        @enderror

                        @error('selectedOpportunityId')
                            <div x-data="{ show: true }"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform scale-95"
                                 x-transition:enter-end="opacity-100 transform scale-100"
                                 class="alert-error flex items-start p-4 bg-red-900/20 border border-red-800 rounded-lg"
                                 dusk="error-selected-opportunity"
                                 role="alert">
                                <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-red-300">{{ $message }}</span>
                            </div>
                        @enderror

                        @error('submitFeedback')
                            <div x-data="{ show: true }"
                                 x-show="show"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform scale-95"
                                 x-transition:enter-end="opacity-100 transform scale-100"
                                 class="alert-error flex items-start p-4 bg-red-900/20 border border-red-800 rounded-lg"
                                 dusk="error-submit-feedback"
                                 role="alert">
                                <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-red-300">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>

                    {{-- Form --}}
                    <div class="form-group" dusk="feedback-form-group">
                        <label for="feedbackTextarea"
                               class="block text-sm font-semibold text-slate-300 mb-2"
                               dusk="feedback-label">
                            Feedback Data (JSON):
                        </label>
                        <textarea wire:model.defer="feedbackData"
                                  id="feedbackTextarea"
                                  class="feedback-textarea block w-full px-4 py-3 border border-slate-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-900 text-slate-200 font-mono text-sm transition-colors duration-200"
                                  rows="8"
                                  dusk="feedback-textarea"
                                  placeholder='{"key": "value", "another_key": "another_value"}'
                                  aria-describedby="feedback-hint"></textarea>
                        <p class="mt-2 text-sm text-slate-500"
                           id="feedback-hint"
                           dusk="feedback-hint">
                            <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            Enter feedback as JSON key-value pairs
                        </p>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="bg-slate-900 px-6 py-4 sm:px-8 sm:py-5 flex flex-col-reverse sm:flex-row gap-3 sm:gap-4"
                     dusk="feedback-form-actions">
                    <button wire:click="cancelFeedback"
                            wire:loading.attr="disabled"
                            wire:target="submitFeedback,cancelFeedback"
                            type="button"
                            class="btn-cancel flex-1 sm:flex-initial inline-flex items-center justify-center px-6 py-2.5 border border-slate-600 text-sm font-semibold rounded-lg text-slate-300 bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            dusk="cancel-feedback-btn"
                            aria-label="Cancel and close modal">
                        <span wire:loading.remove wire:target="cancelFeedback">
                            <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </span>
                        <span wire:loading wire:target="cancelFeedback" class="inline-flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Canceling...
                        </span>
                    </button>

                    <button wire:click="submitFeedback"
                            wire:loading.attr="disabled"
                            wire:target="submitFeedback"
                            wire:confirm="Are you sure you want to submit this feedback? This action cannot be undone."
                            type="button"
                            class="btn-submit flex-1 sm:flex-initial inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            dusk="submit-feedback-btn"
                            aria-label="Submit feedback">
                        <span wire:loading.remove wire:target="submitFeedback">
                            <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            Submit Feedback
                        </span>
                        <span wire:loading wire:target="submitFeedback" class="inline-flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Submitting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Alpine.js x-cloak Styles --}}
    <style>
        [x-cloak] { display: none !important; }
    </style>
</div>
