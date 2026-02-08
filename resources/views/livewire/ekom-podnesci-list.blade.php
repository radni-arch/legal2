<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-podnesci-list">
    {{-- Header Section --}}
    <x-page-header
        title="Submissions (Podnesci)"
        subtitle="Browse and manage court submissions"
        route-name="ekom.podnesci"
    >
        <x-slot:actions>
            <a href="{{ route('ekom.podnesci.create') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors duration-150" style="background: #3b82f6; color: #fff; border: none; border-radius: 0.5rem;" dusk="create-podnesak-link">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Submission
            </a>
            <x-back-button route="ekom.dashboard" label="Dashboard" />
        </x-slot:actions>
    </x-page-header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        {{-- Filters Section --}}
        <div class="card mb-6 p-4" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium mb-1" style="color: var(--muted);">Search by Remote ID</label>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        id="search"
                        placeholder="e.g. RP123456"
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
                                    wire:click="sortBy('remote_id')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-remote-id"
                                >
                                    Remote ID
                                    @if($sortField === 'remote_id')
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
                                Court
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold" style="color: var(--muted);">
                                Sent At
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold" style="color: var(--muted);">
                                Received At
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
                        @forelse($podnesci as $podnesak)
                            <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="hover:bg-opacity-50 transition" dusk="podnesak-row-{{ $podnesak->id }}">
                                {{-- Remote ID --}}
                                <td class="px-4 py-3">
                                    <span class="font-medium" style="color: var(--fg);">{{ $podnesak->remote_id }}</span>
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'kreiran' => ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#6b7280'],
                                            'poslan' => ['bg' => 'rgba(59,130,246,0.15)', 'text' => '#3b82f6'],
                                            'zaprimljen' => ['bg' => 'rgba(16,185,129,0.15)', 'text' => '#10b981'],
                                            'u_obradi' => ['bg' => 'rgba(245,158,11,0.15)', 'text' => '#f59e0b'],
                                        ];
                                        $colors = $statusColors[$podnesak->status] ?? $statusColors['kreiran'];
                                    @endphp
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        style="background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};"
                                    >
                                        {{ ucfirst(str_replace('_', ' ', $podnesak->status)) }}
                                    </span>
                                </td>

                                {{-- Court --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $podnesak->sud_remote_id ?? '-' }}
                                    </span>
                                </td>

                                {{-- Sent At --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $podnesak->vrijeme_slanja ? \Carbon\Carbon::parse($podnesak->vrijeme_slanja)->format('d.m.Y H:i') : '-' }}
                                    </span>
                                </td>

                                {{-- Received At --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $podnesak->vrijeme_zaprimanja ? \Carbon\Carbon::parse($podnesak->vrijeme_zaprimanja)->format('d.m.Y H:i') : '-' }}
                                    </span>
                                </td>

                                {{-- Last Synced --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $podnesak->last_synced_at ? $podnesak->last_synced_at->diffForHumans() : 'Never' }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right">
                                    <span
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium"
                                        style="background: rgba(107,114,128,0.15); color: #6b7280;"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" style="color: var(--muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="font-medium" style="color: var(--fg)">No submissions found</p>
                                    <p class="text-sm mt-1" style="color: var(--muted)">
                                        @if($search || $statusFilter)
                                            Try adjusting your filters or search query
                                        @else
                                            Create a new submission to get started
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($podnesci->hasPages())
                <div class="px-4 py-3" style="border-top: 1px solid var(--border, #1f2937);">
                    {{ $podnesci->links() }}
                </div>
            @endif
        </div>

        {{-- Results Summary --}}
        <div class="mt-4 text-sm" style="color: var(--muted);">
            Showing {{ $podnesci->firstItem() ?? 0 }} to {{ $podnesci->lastItem() ?? 0 }} of {{ $podnesci->total() }} submissions
        </div>
    </main>
</div>
