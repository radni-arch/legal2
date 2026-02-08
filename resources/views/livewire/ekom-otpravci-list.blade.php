<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-otpravci-list">
    {{-- Header Section --}}
    <x-page-header
        title="Dispatches (Otpravci)"
        subtitle="Browse and confirm receipt of court dispatches"
        route-name="ekom.otpravci"
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
                    <label for="search" class="block text-sm font-medium mb-1" style="color: var(--muted);">Search by Remote ID</label>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        id="search"
                        placeholder="e.g. RO123456"
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

                {{-- Pending Only Toggle --}}
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--muted);">Pending Only</label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            wire:model.live="pendingOnly"
                            type="checkbox"
                            class="w-5 h-5 rounded"
                            style="accent-color: #f59e0b;"
                            dusk="pending-only-checkbox"
                        />
                        <span class="text-sm" style="color: var(--fg);">Show unconfirmed only</span>
                    </label>
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
                            <th class="px-4 py-3 text-left">
                                <button
                                    wire:click="sortBy('predmet_remote_id')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-predmet"
                                >
                                    Predmet (Case)
                                    @if($sortField === 'predmet_remote_id')
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
                                    wire:click="sortBy('vrijeme_slanja_sa_suda')"
                                    class="flex items-center gap-1 text-sm font-semibold"
                                    style="color: var(--muted);"
                                    dusk="sort-sent"
                                >
                                    Sent At
                                    @if($sortField === 'vrijeme_slanja_sa_suda')
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
                                Confirmed At
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
                        @forelse($otpravci as $otpravak)
                            <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="hover:bg-opacity-50 transition" dusk="otpravak-row-{{ $otpravak->id }}">
                                {{-- Remote ID --}}
                                <td class="px-4 py-3">
                                    <span class="font-medium" style="color: var(--fg);">{{ $otpravak->remote_id }}</span>
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'kreiran' => ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#6b7280'],
                                            'poslan' => ['bg' => 'rgba(59,130,246,0.15)', 'text' => '#3b82f6'],
                                            'dostavljen' => ['bg' => 'rgba(16,185,129,0.15)', 'text' => '#10b981'],
                                            'istekao_rok' => ['bg' => 'rgba(239,68,68,0.15)', 'text' => '#ef4444'],
                                        ];
                                        $colors = $statusColors[$otpravak->status] ?? $statusColors['kreiran'];
                                    @endphp
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        style="background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};"
                                    >
                                        {{ ucfirst(str_replace('_', ' ', $otpravak->status)) }}
                                    </span>
                                    @if($otpravak->primljen_zbog_isteka_roka)
                                        <span
                                            class="inline-flex ml-1 px-2 py-1 rounded-full text-xs font-medium"
                                            style="background: rgba(245,158,11,0.15); color: #f59e0b;"
                                            title="Received due to deadline expiration"
                                        >
                                            Expired
                                        </span>
                                    @endif
                                </td>

                                {{-- Predmet --}}
                                <td class="px-4 py-3">
                                    @if($otpravak->predmet_remote_id)
                                        <a
                                            href="#"
                                            class="text-sm hover:underline"
                                            style="color: #3b82f6;"
                                        >
                                            {{ $otpravak->predmet_remote_id }}
                                        </a>
                                    @else
                                        <span class="text-sm" style="color: var(--muted);">-</span>
                                    @endif
                                </td>

                                {{-- Sent At --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $otpravak->vrijeme_slanja_sa_suda ? \Carbon\Carbon::parse($otpravak->vrijeme_slanja_sa_suda)->format('d.m.Y H:i') : '-' }}
                                    </span>
                                </td>

                                {{-- Confirmed At --}}
                                <td class="px-4 py-3">
                                    @if($otpravak->vrijeme_potvrde_primitka)
                                        <span class="text-sm" style="color: #10b981;">
                                            {{ \Carbon\Carbon::parse($otpravak->vrijeme_potvrde_primitka)->format('d.m.Y H:i') }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                            style="background: rgba(245,158,11,0.15); color: #f59e0b;"
                                        >
                                            Pending
                                        </span>
                                    @endif
                                </td>

                                {{-- Last Synced --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--muted);">
                                        {{ $otpravak->last_synced_at ? $otpravak->last_synced_at->diffForHumans() : 'Never' }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right">
                                    @if(!$otpravak->vrijeme_potvrde_primitka)
                                        <button
                                            wire:click="confirmReceipt({{ $otpravak->id }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50"
                                            wire:target="confirmReceipt({{ $otpravak->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium transition hover:opacity-80"
                                            style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3);"
                                            dusk="confirm-receipt-{{ $otpravak->id }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span wire:loading.remove wire:target="confirmReceipt({{ $otpravak->id }})">
                                                Confirm Receipt
                                            </span>
                                            <span wire:loading wire:target="confirmReceipt({{ $otpravak->id }})">
                                                Confirming...
                                            </span>
                                        </button>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium"
                                            style="background: rgba(107,114,128,0.15); color: #6b7280;"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Confirmed
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" style="color: var(--muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="font-medium" style="color: var(--fg)">No dispatches found</p>
                                    <p class="text-sm mt-1" style="color: var(--muted)">
                                        @if($search || $statusFilter || $pendingOnly)
                                            Try adjusting your filters or search query
                                        @else
                                            No dispatches have been synchronized yet
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($otpravci->hasPages())
                <div class="px-4 py-3" style="border-top: 1px solid var(--border, #1f2937);">
                    {{ $otpravci->links() }}
                </div>
            @endif
        </div>

        {{-- Results Summary --}}
        <div class="mt-4 text-sm" style="color: var(--muted);">
            Showing {{ $otpravci->firstItem() ?? 0 }} to {{ $otpravci->lastItem() ?? 0 }} of {{ $otpravci->total() }} dispatches
        </div>
    </main>
</div>
