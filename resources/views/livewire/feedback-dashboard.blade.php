<div class="relative min-h-screen" style="background: var(--bg, #0b1220);" dusk="feedback-dashboard">
    {{-- Page Header --}}
    <x-page-header
        :breadcrumbs="breadcrumbs('feedback.dashboard')"
        title="Feedback Dashboard"
        subtitle="Monitor learning opportunities and feedback metrics"
    />

    <div class="p-6">
    <!-- Loading Overlay for Stats Refresh -->
    <div wire:loading wire:target="refreshStats" class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm flex items-center justify-center z-50" dusk="loading-overlay">
        <div class="text-center">
            <svg class="animate-spin h-16 w-16 text-blue-400 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-white text-lg font-medium" dusk="loading-message">Refreshing statistics...</p>
        </div>
    </div>

    <!-- Statistics Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Opportunities Card -->
        <div class="stat-card relative overflow-hidden rounded-xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-200 bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6"
             dusk="stat-card-total">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium uppercase tracking-wider opacity-90" dusk="total-label">Total Opportunities</h3>
                    <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p class="text-4xl font-bold" dusk="total-opportunities">{{ $totalOpportunities }}</p>
                <p class="text-xs mt-2 opacity-80" dusk="total-description">All learning opportunities</p>
            </div>
        </div>

        <!-- Pending Review Card -->
        <div class="stat-card relative overflow-hidden rounded-xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-200 bg-gradient-to-br from-amber-500 to-orange-600 text-white p-6"
             dusk="stat-card-pending">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium uppercase tracking-wider opacity-90" dusk="pending-label">Pending Review</h3>
                    <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-4xl font-bold" dusk="pending-opportunities">{{ $pendingOpportunities }}</p>
                <p class="text-xs mt-2 opacity-80" dusk="pending-description">Awaiting human review</p>
            </div>
        </div>

        <!-- Reviewed Card -->
        <div class="stat-card relative overflow-hidden rounded-xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-200 bg-gradient-to-br from-green-500 to-emerald-600 text-white p-6"
             dusk="stat-card-reviewed">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium uppercase tracking-wider opacity-90" dusk="reviewed-label">Reviewed</h3>
                    <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-4xl font-bold" dusk="reviewed-opportunities">{{ $reviewedOpportunities }}</p>
                <p class="text-xs mt-2 opacity-80" dusk="reviewed-description">Completed reviews</p>
            </div>
        </div>

        <!-- Completion Rate Card -->
        <div class="stat-card relative overflow-hidden rounded-xl shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-200 bg-gradient-to-br from-purple-500 to-indigo-600 text-white p-6"
             dusk="stat-card-completion">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium uppercase tracking-wider opacity-90" dusk="completion-label">Completion Rate</h3>
                    <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <p class="text-4xl font-bold" dusk="completion-rate">{{ $completionRate }}%</p>
                <p class="text-xs mt-2 opacity-80" dusk="completion-description">Review progress</p>
            </div>
        </div>
    </div>

    <!-- Secondary Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Type Breakdown Card -->
        <div class="breakdown-card relative bg-slate-800/50 backdrop-blur border border-slate-700 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200 p-6"
             dusk="breakdown-card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-white" dusk="breakdown-title">Breakdown by Type</h3>
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                </svg>
            </div>

            @if (empty($typeBreakdown))
                <div class="text-center py-8" dusk="no-pending-wrapper">
                    <svg class="w-16 h-16 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-slate-400 font-medium" dusk="no-pending-message">No pending opportunities</p>
                </div>
            @else
                <div class="space-y-3" dusk="type-breakdown-list">
                    @foreach ($typeBreakdown as $type => $count)
                        <div class="flex items-center justify-between p-3 bg-slate-700/30 rounded-lg hover:bg-slate-700/50 transition-colors duration-200"
                             dusk="type-item-{{ $loop->index }}">
                            <div class="flex items-center space-x-3">
                                <div class="w-2 h-2 bg-indigo-600 rounded-full"></div>
                                <span class="font-semibold text-white" dusk="type-name-{{ $loop->index }}">{{ $type }}</span>
                            </div>
                            <span class="px-3 py-1 bg-indigo-900/50 text-indigo-300 rounded-full text-sm font-bold" dusk="type-count-{{ $loop->index }}">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Average Confidence Card -->
        <div class="stat-card relative overflow-hidden bg-slate-800/50 backdrop-blur border border-slate-700 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200 p-6"
             dusk="stat-card-confidence">
            <div class="absolute top-0 right-0 -mt-8 -mr-8 h-32 w-32 rounded-full bg-gradient-to-br from-cyan-900/30 to-blue-900/30"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-white" dusk="confidence-title">Average Confidence</h3>
                    <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="text-center py-4">
                    <p class="text-5xl font-bold bg-gradient-to-r from-cyan-600 to-blue-600 bg-clip-text text-transparent" dusk="average-confidence">{{ $averageConfidence }}</p>
                    <p class="text-slate-400 mt-2" dusk="confidence-description">Average AI confidence score</p>
                </div>

                <!-- Confidence Progress Bar -->
                <div class="mt-4" dusk="confidence-progress-wrapper">
                    <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full transition-all duration-500"
                             style="width: {{ min($averageConfidence, 1) * 100 }}%"
                             dusk="confidence-progress-bar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Card -->
    <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-200 p-6 mb-8" dusk="recent-activity-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white" dusk="activity-title">Recent Feedback Activity</h3>
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        @if (empty($recentActivity))
            <div class="text-center py-12" dusk="no-activity-wrapper">
                <svg class="w-20 h-20 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-slate-500 font-medium" dusk="no-recent-activity-message">No recent activity</p>
                <p class="text-slate-500 text-sm mt-1">Feedback reviews will appear here</p>
            </div>
        @else
            <div class="space-y-3" dusk="recent-activity-list">
                @foreach ($recentActivity as $activity)
                    <div class="flex items-start space-x-4 p-4 bg-slate-700/30 rounded-lg hover:bg-slate-700/50 transition-colors duration-200"
                         dusk="activity-item-{{ $loop->index }}">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-white" dusk="activity-type-{{ $loop->index }}">{{ $activity['type'] }}</p>
                            <p class="text-sm text-slate-400 mt-1">
                                <span dusk="activity-reviewer-{{ $loop->index }}">Reviewed by {{ $activity['reviewed_by'] }}</span>
                                <span class="mx-2">•</span>
                                <span class="text-slate-500" dusk="activity-time-{{ $loop->index }}">{{ $activity['reviewed_at'] }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Refresh Button -->
    <div class="flex justify-center" dusk="refresh-button-wrapper">
        <button
            wire:click="refreshStats"
            wire:loading.attr="disabled"
            wire:target="refreshStats"
            dusk="refresh-button"
            class="group relative px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
            aria-label="Refresh statistics">
            <span class="flex items-center space-x-2">
                <!-- Default State Icon -->
                <svg wire:loading.remove wire:target="refreshStats" class="w-5 h-5 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>

                <!-- Loading State Icon -->
                <svg wire:loading wire:target="refreshStats" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <!-- Button Text -->
                <span wire:loading.remove wire:target="refreshStats" dusk="refresh-text">Refresh Statistics</span>
                <span wire:loading wire:target="refreshStats" dusk="refresh-loading-text">Refreshing...</span>
            </span>
        </button>
    </div>
    </div>
</div>
