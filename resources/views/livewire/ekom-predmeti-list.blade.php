<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-predmeti-list">
    {{-- Header Section --}}
    <x-page-header
        title="Cases (Predmeti)"
        subtitle="Browse and manage synchronized court cases"
        route-name="ekom.predmeti"
    >
        <x-slot:actions>
            <x-back-button route="ekom.dashboard" label="Dashboard" />
        </x-slot:actions>
    </x-page-header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        {{-- Filters Section --}}
        <div class="card mb-6 p-4" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium mb-1" style="color: var(--muted);">Search by Case Number</label>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        id="search"
                        placeholder="e.g. Pp-123/2025"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="search-input"
                    />
                </div>

                {{-- Status Filter --}}
                <div>
                    <label for="statusFilter" class="block text-sm font-medium mb-1" style="color: var(--muted);">Status</label>
                    <select
                        wire:model.live="statusFilter"
                        id="statusFilter"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="status-filter"
                    >
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- DND Filter --}}
                <div>
                    <label for="dndFilter" class="block text-sm font-medium mb-1" style="color: var(--muted);">DND Status</label>
                    <select
                        wire:model.live="dndFilter"
                        id="dndFilter"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="dnd-filter"
                    >
                        @foreach($dndOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Per Page --}}
                <div>
                    <label for="perPage" class="block text-sm font-medium mb-1" style="color: var(--muted);">Per Page</label>
                    <select
                        wire:model.live="perPage"
                        id="perPage"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="per-page"
                    >
                        @foreach($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Results Table --}}
        <div class="card overflow-hidden" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border, #1f2937); background: rgba(17,24,39,0.5);">
                            <th class="px-4 py-3 text-left">
                                <button
                                    wire:click="sortBy('oznaka')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-oznaka"
                                >
                                    Case Number
                                    @if($sortField === 'oznaka')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button
                                    wire:click="sortBy('status')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-status"
                                >
                                    Status
                                    @if($sortField === 'status')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold" style="color: var(--muted);">
                                DND
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button
                                    wire:click="sortBy('last_synced_at')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-synced"
                                >
                                    Last Synced
                                    @if($sortField === 'last_synced_at')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-semibold" style="color: var(--muted);">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($predmeti as $predmet)
                            <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="hover:bg-opacity-50 transition" dusk="predmet-row-{{ $predmet->id }}">
                                {{-- Case Number --}}
                                <td class="px-4 py-3">
                                    <span class="font-medium" style="color: var(--fg);">{{ $predmet->oznaka }}</span>
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'otvoren' => ['bg' => 'rgba(59,130,246,0.15)', 'text' => '#3b82f6'],
                                            'aktivan' => ['bg' => 'rgba(16,185,129,0.15)', 'text' => '#10b981'],
                                            'u_tijeku' => ['bg' => 'rgba(245,158,11,0.15)', 'text' => '#f59e0b'],
                                            'zatvoren' => ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#6b7280'],
                                        ];
                                        $colors = $statusColors[$predmet->status] ?? $statusColors['otvoren'];
                                    @endphp
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        style="background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};"
                                    >
                                        {{ ucfirst(str_replace('_', ' ', $predmet->status)) }}
                                    </span>
                                </td>

                                {{-- DND Toggle --}}
                                <td class="px-4 py-3">
                                    <button
                                        wire:click="toggleDnd({{ $predmet->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleDnd({{ $predmet->id }})"
                                        class="p-2 rounded-lg transition"
                                        style="background: {{ $predmet->do_not_disturb ? 'rgba(239,68,68,0.15)' : 'rgba(107,114,128,0.15)' }};"
                                        title="{{ $predmet->do_not_disturb ? 'DND Enabled - Click to disable' : 'DND Disabled - Click to enable' }}"
                                        dusk="dnd-toggle-{{ $predmet->id }}"
                                    >
                                        @if($predmet->do_not_disturb)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                <line x1="4" y1="4" x2="20" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #6b7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                        @endif
                                    </button>
                                </td>

                                {{-- Last Synced --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $predmet->last_synced_at ? $predmet->last_synced_at->diffForHumans() : 'Never' }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('ekom.predmeti.show', $predmet->remote_id) }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium transition"
                                        style="background: rgba(59,130,246,0.15); color: #3b82f6;"
                                        dusk="view-predmet-{{ $predmet->id }}"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" style="color: var(--muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <p class="font-medium" style="color: var(--fg)">No cases found</p>
                                    <p class="text-sm mt-1" style="color: var(--muted)">
                                        @if($search || $statusFilter || $dndFilter)
                                            Try adjusting your filters or search query
                                        @else
                                            Sync with E-Komunikacije to see cases here
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($predmeti->hasPages())
                <div class="px-4 py-3" style="border-top: 1px solid var(--border, #1f2937);">
                    {{ $predmeti->links() }}
                </div>
            @endif
        </div>

        {{-- Results Summary --}}
        <div class="mt-4 text-sm" style="color: var(--muted);">
            Showing {{ $predmeti->firstItem() ?? 0 }} to {{ $predmeti->lastItem() ?? 0 }} of {{ $predmeti->total() }} cases
        </div>
    </main>
</div>
