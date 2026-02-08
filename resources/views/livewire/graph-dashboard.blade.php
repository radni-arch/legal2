<div class="min-h-screen" style="background: #0b1220; color: var(--fg, #e5e7eb);" dusk="graph-dashboard-container">
    {{-- Unified Header --}}
    <x-page-header
        title="Neo4j Graph Dashboard"
        subtitle="Explore legal knowledge graph with advanced AI-powered tools"
        route-name="graph.dashboard"
    />

    <main class="max-w-7xl mx-auto px-4 py-8" dusk="graph-dashboard-main">
        <!-- Navigation Tabs -->
        <div class="mb-6" dusk="tab-navigation-container">
            <div class="border-b" style="border-color: #1f2937;" dusk="tab-border">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs" dusk="tab-navigation">
                    @foreach($panels as $panelKey => $panelLabel)
                    <button
                        wire:click="switchPanel('{{ $panelKey }}')"
                        wire:loading.attr="disabled"
                        wire:target="switchPanel"
                        dusk="tab-{{ $panelKey }}"
                        class="@if($activePanel === $panelKey) border-blue-500 text-blue-400 shadow-lg @else border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300 @endif whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm transition-all duration-200 relative @if($activePanel === $panelKey) bg-gradient-to-t from-blue-500/10 to-transparent @endif"
                        style="@if($activePanel === $panelKey) text-shadow: 0 0 10px rgba(59, 130, 246, 0.5); @endif"
                        aria-current="@if($activePanel === $panelKey) page @endif"
                    >
                        <span wire:loading.remove wire:target="switchPanel" dusk="tab-label-{{ $panelKey }}">
                            {{ $panelLabel }}
                        </span>
                        <span wire:loading wire:target="switchPanel" class="inline-flex items-center" dusk="tab-loading-{{ $panelKey }}">
                            <svg class="animate-spin inline h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" dusk="tab-spinner-{{ $panelKey }}">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Active Panel Indicator -->
        <div class="mb-4 px-4 py-2 rounded-lg" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);" dusk="active-panel-indicator">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="active-indicator-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span class="text-sm font-medium text-blue-400" dusk="active-panel-name">Active: {{ $panels[$activePanel] ?? 'Unknown' }}</span>
            </div>
        </div>

        <!-- Panel Content with Loading Overlay -->
        <div class="panel-content relative" dusk="panel-content-container" style="min-height: 500px;">
            <!-- Loading Overlay -->
            <div
                wire:loading
                wire:target="switchPanel"
                class="absolute inset-0 z-50 flex items-center justify-center rounded-lg"
                style="background: rgba(11, 18, 32, 0.95); backdrop-filter: blur(8px);"
                dusk="panel-loading-overlay"
            >
                <div class="text-center" dusk="loading-content">
                    <svg class="animate-spin h-16 w-16 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="color: #3b82f6;" dusk="panel-loading-spinner">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div class="space-y-2">
                        <p class="text-blue-400 font-semibold text-xl" dusk="loading-message">Loading Panel Data...</p>
                        <p class="text-gray-400 text-sm" dusk="loading-submessage">Switching to {{ $panels[$activePanel] ?? 'panel' }}</p>
                    </div>
                    <!-- Animated progress bar -->
                    <div class="mt-6 w-64 mx-auto" dusk="loading-progress-container">
                        <div class="h-1 bg-gray-700 rounded-full overflow-hidden" dusk="loading-progress-track">
                            <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full animate-pulse" style="width: 60%;" dusk="loading-progress-bar"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Explorer Panel -->
            @if($activePanel === 'explorer')
                <div class="panel-wrapper fade-in" dusk="explorer-panel" style="animation: fadeIn 0.3s ease-in;">
                    <div class="mb-4 p-4 rounded-lg" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2);" dusk="explorer-panel-header">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="explorer-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h2 class="text-lg font-semibold text-green-400" dusk="explorer-panel-title">Graph Explorer</h2>
                        </div>
                    </div>
                    @livewire('graph-viewer')
                </div>
            @elseif($activePanel === 'llm_brain')
                <div class="panel-wrapper fade-in" dusk="llm-brain-panel" style="animation: fadeIn 0.3s ease-in;">
                    <div class="mb-4 p-4 rounded-lg" style="background: rgba(168, 85, 247, 0.05); border: 1px solid rgba(168, 85, 247, 0.2);" dusk="llm-brain-panel-header">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="llm-brain-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            <h2 class="text-lg font-semibold text-purple-400" dusk="llm-brain-panel-title">LLM Brain</h2>
                        </div>
                    </div>
                    @livewire('llm-brain-panel')
                </div>
            @elseif($activePanel === 'analytics')
                <div class="panel-wrapper fade-in" dusk="analytics-panel" style="animation: fadeIn 0.3s ease-in;">
                    <div class="mb-4 p-4 rounded-lg" style="background: rgba(249, 115, 22, 0.05); border: 1px solid rgba(249, 115, 22, 0.2);" dusk="analytics-panel-header">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="analytics-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h2 class="text-lg font-semibold text-orange-400" dusk="analytics-panel-title">Analytics</h2>
                        </div>
                    </div>
                    @livewire('analytics-panel')
                </div>
            @elseif($activePanel === 'temporal')
                <div class="panel-wrapper fade-in" dusk="temporal-panel" style="animation: fadeIn 0.3s ease-in;">
                    <div class="mb-4 p-4 rounded-lg" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);" dusk="temporal-panel-header">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="temporal-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h2 class="text-lg font-semibold text-blue-400" dusk="temporal-panel-title">Temporal Analysis</h2>
                        </div>
                    </div>
                    @livewire('temporal-panel')
                </div>
            @elseif($activePanel === 'admin')
                <div class="panel-wrapper fade-in" dusk="admin-panel" style="animation: fadeIn 0.3s ease-in;">
                    <div class="mb-4 p-4 rounded-lg" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2);" dusk="admin-panel-header">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="admin-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <h2 class="text-lg font-semibold text-red-400" dusk="admin-panel-title">Admin Tools</h2>
                        </div>
                    </div>
                    <div class="text-center py-12" style="color: #94a3b8;" dusk="admin-coming-soon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="admin-placeholder-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <h3 class="text-xl font-semibold mb-2" style="color: #e5e7eb;" dusk="admin-coming-soon-title">Admin Tools - Coming Soon</h3>
                        <p dusk="admin-coming-soon-description">Graph quality checks, data maintenance, and system utilities</p>
                    </div>
                </div>
            @endif
        </div>
    </main>
    @push('styles')

    <!-- Inline Animation Styles -->
    <style>
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

        .panel-wrapper {
            animation: fadeIn 0.3s ease-in;
        }

        /* Enhanced tab hover effects */
        [dusk^="tab-"]:hover:not([disabled]) {
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }

        [dusk^="tab-"][disabled] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Loading overlay animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to {
                opacity: 1;
                backdrop-filter: blur(8px);
            }
        }

        [dusk="panel-loading-overlay"] {
            animation: slideIn 0.2s ease-in;
        }

        /* Active tab glow effect */
        [dusk^="tab-"][class*="border-blue-500"] {
            position: relative;
        }

        [dusk^="tab-"][class*="border-blue-500"]::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            animation: glow 2s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% {
                opacity: 0.5;
            }
            50% {
                opacity: 1;
            }
        }
    </style>
    @endpush
</div>
