<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-dashboard">
    {{-- Unified Header --}}
    <x-page-header
        title="E-Komunikacije Dashboard"
        subtitle="Manage court communications, submissions, and dispatches"
        route-name="ekom.dashboard"
    >
        <x-slot:actions>
            <button
                wire:click="refreshStats"
                wire:loading.attr="disabled"
                wire:target="refreshStats"
                dusk="refresh-btn"
                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: 0.875rem; border: 1px solid var(--border, #1f2937); border-radius: 0.5rem;">
                <span wire:loading.remove wire:target="refreshStats">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </span>
                <span wire:loading wire:target="refreshStats">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                Refresh
            </button>
        </x-slot:actions>
    </x-page-header>

    <main class="max-w-7xl mx-auto px-4 py-8" dusk="ekom-main">
        {{-- Action Status Messages --}}
        @if($actionStatus)
            <div class="mb-6 p-4 rounded-lg" dusk="action-message"
                 style="background: {{ $actionStatus === 'success' ? 'rgba(34,197,94,0.15)' : ($actionStatus === 'warning' ? 'rgba(234,179,8,0.15)' : 'rgba(239,68,68,0.15)') }};
                        border: 1px solid {{ $actionStatus === 'success' ? 'rgba(34,197,94,0.35)' : ($actionStatus === 'warning' ? 'rgba(234,179,8,0.35)' : 'rgba(239,68,68,0.35)') }};
                        color: {{ $actionStatus === 'success' ? '#86efac' : ($actionStatus === 'warning' ? '#fde047' : '#fca5a5') }};">
                {{ $actionMessage }}
            </div>
        @endif

        {{-- Statistics Cards --}}
        <section class="mb-8" dusk="stats-section">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #38bdf8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Statistics
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" dusk="stats-grid">
                {{-- Predmeti Count --}}
                <div class="card p-4" dusk="stat-predmeti" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl p-3" style="background: rgba(59,130,246,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #3b82f6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--muted)">Cases (Predmeti)</p>
                            <p class="text-2xl font-bold" style="color: #3b82f6" dusk="predmeti-count">{{ $stats['predmeti_count'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- Podnesci Count --}}
                <div class="card p-4" dusk="stat-podnesci" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl p-3" style="background: rgba(16,185,129,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #10b981" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--muted)">Submissions (Podnesci)</p>
                            <p class="text-2xl font-bold" style="color: #10b981" dusk="podnesci-count">{{ $stats['podnesci_count'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- Otpravci Count --}}
                <div class="card p-4" dusk="stat-otpravci" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl p-3" style="background: rgba(139,92,246,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #a78bfa" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--muted)">Dispatches (Otpravci)</p>
                            <p class="text-2xl font-bold" style="color: #a78bfa" dusk="otpravci-count">{{ $stats['otpravci_count'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- Last Sync --}}
                <div class="card p-4" dusk="stat-last-sync" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl p-3" style="background: rgba(245,158,11,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #f59e0b" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--muted)">Last Sync</p>
                            <p class="text-lg font-semibold" style="color: #f59e0b" dusk="last-sync">
                                {{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->diffForHumans() : 'Never' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Secondary Stats Row --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4" dusk="stats-secondary">
                {{-- Pending Otpravci --}}
                <div class="card p-4" dusk="stat-pending" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl p-2" style="background: rgba(239,68,68,0.15);">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #ef4444" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm" style="color: var(--muted)">Pending Receipt Confirmation</p>
                                <p class="text-xl font-bold" style="color: {{ $stats['pending_otpravci'] > 0 ? '#ef4444' : '#10b981' }}" dusk="pending-count">
                                    {{ $stats['pending_otpravci'] }}
                                </p>
                            </div>
                        </div>
                        @if($stats['pending_otpravci'] > 0)
                            <a href="{{ route('ekom.otpravci') }}" class="text-sm font-medium" style="color: #ef4444">View pending</a>
                        @endif
                    </div>
                </div>

                {{-- Draft Podnesci --}}
                <div class="card p-4" dusk="stat-drafts" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl p-2" style="background: rgba(245,158,11,0.15);">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #f59e0b" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm" style="color: var(--muted)">Draft Submissions</p>
                                <p class="text-xl font-bold" style="color: #f59e0b" dusk="draft-count">
                                    {{ $stats['draft_podnesci'] }}
                                </p>
                            </div>
                        </div>
                        @if($stats['draft_podnesci'] > 0)
                            <a href="{{ route('ekom.podnesci') }}" class="text-sm font-medium" style="color: #f59e0b">View drafts</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Quick Navigation --}}
        <section class="mb-8" dusk="quick-nav-section">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #10b981" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Quick Actions
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" dusk="quick-nav-grid">
                {{-- Browse Cases --}}
                <a href="{{ route('ekom.predmeti') }}" class="card p-4 block transition hover:transform hover:-translate-y-0.5" dusk="nav-predmeti" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl p-3" style="background: rgba(59,130,246,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" style="color: #3b82f6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg" style="color: var(--fg)">Browse Cases</h3>
                            <p class="text-sm" style="color: var(--muted)">View and search all synchronized cases</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2" style="color: #3b82f6">
                        <span class="text-sm font-medium">Open</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                {{-- Create Submission --}}
                <a href="{{ route('ekom.podnesci.create') }}" class="card p-4 block transition hover:transform hover:-translate-y-0.5" dusk="nav-create-podnesak" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl p-3" style="background: rgba(16,185,129,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" style="color: #10b981" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg" style="color: var(--fg)">Create Submission</h3>
                            <p class="text-sm" style="color: var(--muted)">Draft and send new court submissions</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2" style="color: #10b981">
                        <span class="text-sm font-medium">Create</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                {{-- Pending Receipts --}}
                <a href="{{ route('ekom.otpravci') }}" class="card p-4 block transition hover:transform hover:-translate-y-0.5" dusk="nav-otpravci" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl p-3" style="background: rgba(139,92,246,0.15);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" style="color: #a78bfa" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg" style="color: var(--fg)">Court Dispatches</h3>
                            <p class="text-sm" style="color: var(--muted)">View incoming court communications</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2" style="color: #a78bfa">
                        <span class="text-sm font-medium">View</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            </div>
        </section>

        {{-- Recent Activity --}}
        <section dusk="activity-section">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2" style="color: var(--fg)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #f59e0b" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Recent Activity
            </h2>
            <div class="card" dusk="activity-list" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
                @if(count($recentActivity) > 0)
                    <div class="divide-y" style="border-color: var(--border, #1f2937);">
                        @foreach($recentActivity as $activity)
                            <a href="{{ $activity['url'] }}" class="block p-4 hover:bg-opacity-50 transition" style="background: transparent;" dusk="activity-item-{{ $loop->index }}">
                                <div class="flex items-center gap-4">
                                    <div class="rounded-lg p-2" style="background: {{ $activity['type'] === 'predmet' ? 'rgba(59,130,246,0.15)' : ($activity['type'] === 'podnesak' ? 'rgba(16,185,129,0.15)' : 'rgba(139,92,246,0.15)') }};">
                                        @if($activity['type'] === 'predmet')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #3b82f6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                            </svg>
                                        @elseif($activity['type'] === 'podnesak')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #10b981" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #a78bfa" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate" style="color: var(--fg)">{{ $activity['title'] }}</p>
                                        <p class="text-sm truncate" style="color: var(--muted)">{{ $activity['subtitle'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm" style="color: var(--muted)">
                                            {{ $activity['timestamp'] ? \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans() : '' }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center" dusk="no-activity">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" style="color: var(--muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="font-medium" style="color: var(--fg)">No recent activity</p>
                        <p class="text-sm mt-1" style="color: var(--muted)">Activity will appear here once you start syncing with E-Komunikacije</p>
                    </div>
                @endif
            </div>
        </section>
    </main>
</div>
