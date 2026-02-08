<div class="min-h-screen p-8" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <x-page-header
            title="Sync Status"
            subtitle="Monitor synchronization status and history"
            route-name="ekom.sync-status"
        >
            <x-slot:actions>
                <button
                    wire:click="refreshStats"
                    class="px-4 py-2 rounded-lg font-medium flex items-center gap-2"
                    style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);"
                >
                    <svg wire:loading.remove wire:target="refreshStats" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="refreshStats" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Refresh
                </button>

                <button
                    wire:click="triggerSync"
                    class="px-4 py-2 rounded-lg font-medium flex items-center gap-2"
                    style="background: var(--accent, #3b82f6); color: white;"
                >
                    <svg wire:loading.remove wire:target="triggerSync" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <svg wire:loading wire:target="triggerSync" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Trigger Sync
                </button>

                <x-back-button route="ekom.dashboard" label="Dashboard" />
            </x-slot:actions>
        </x-page-header>

        {{-- Status Message --}}
        @if($statusMessage)
            <div class="mb-6 p-4 rounded-lg" style="background: var(--success-bg, #064e3b); border: 1px solid var(--success-border, #065f46);">
                {{ $statusMessage }}
            </div>
        @endif

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {{-- Predmeti Card --}}
            <div class="p-6 rounded-lg" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 rounded-lg" style="background: var(--accent-bg, #1e3a5f);">
                        <svg class="w-6 h-6" style="color: var(--accent, #3b82f6);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold">Predmeti</h3>
                        <p class="text-sm" style="color: var(--muted, #6b7280);">Cases</p>
                    </div>
                </div>
                <div class="text-3xl font-bold mb-2">{{ number_format($stats['predmeti_count'] ?? 0) }}</div>
                <div class="text-sm" style="color: var(--muted, #6b7280);">
                    Last sync:
                    @if($stats['predmeti_last_sync'] ?? null)
                        {{ \Carbon\Carbon::parse($stats['predmeti_last_sync'])->diffForHumans() }}
                    @else
                        Never
                    @endif
                </div>
            </div>

            {{-- Podnesci Card --}}
            <div class="p-6 rounded-lg" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 rounded-lg" style="background: var(--success-bg, #064e3b);">
                        <svg class="w-6 h-6" style="color: var(--success, #10b981);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold">Podnesci</h3>
                        <p class="text-sm" style="color: var(--muted, #6b7280);">Submissions</p>
                    </div>
                </div>
                <div class="text-3xl font-bold mb-2">{{ number_format($stats['podnesci_count'] ?? 0) }}</div>
                <div class="text-sm" style="color: var(--muted, #6b7280);">
                    Last sync:
                    @if($stats['podnesci_last_sync'] ?? null)
                        {{ \Carbon\Carbon::parse($stats['podnesci_last_sync'])->diffForHumans() }}
                    @else
                        Never
                    @endif
                </div>
            </div>

            {{-- Otpravci Card --}}
            <div class="p-6 rounded-lg" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 rounded-lg" style="background: var(--warning-bg, #713f12);">
                        <svg class="w-6 h-6" style="color: var(--warning, #f59e0b);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold">Otpravci</h3>
                        <p class="text-sm" style="color: var(--muted, #6b7280);">Dispatches</p>
                    </div>
                </div>
                <div class="text-3xl font-bold mb-2">{{ number_format($stats['otpravci_count'] ?? 0) }}</div>
                <div class="text-sm" style="color: var(--muted, #6b7280);">
                    Last sync:
                    @if($stats['otpravci_last_sync'] ?? null)
                        {{ \Carbon\Carbon::parse($stats['otpravci_last_sync'])->diffForHumans() }}
                    @else
                        Never
                    @endif
                </div>
            </div>
        </div>

        {{-- Scheduled Sync Info --}}
        <div class="p-6 rounded-lg mb-8" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);">
            <h2 class="text-lg font-semibold mb-4">Scheduled Sync</h2>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" style="color: var(--success, #10b981);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Runs hourly</span>
                </div>
                <span style="color: var(--muted, #6b7280);">|</span>
                <span style="color: var(--muted, #6b7280);">Max 5 pages per entity type</span>
                <span style="color: var(--muted, #6b7280);">|</span>
                <span style="color: var(--muted, #6b7280);">Single server only</span>
            </div>
        </div>

        {{-- Sync History Placeholder --}}
        <div class="p-6 rounded-lg" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155);">
            <h2 class="text-lg font-semibold mb-4">Sync History</h2>
            <p style="color: var(--muted, #6b7280);">
                Recent sync activity will be displayed here. Check application logs for detailed sync history.
            </p>
        </div>

        {{-- Back to Dashboard is now in the page header --}}
    </div>
</div>
