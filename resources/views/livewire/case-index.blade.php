<div class="min-h-screen p-6 md:p-8" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    <div class="max-w-7xl mx-auto">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Predmeti</h1>
                <p class="text-sm" style="color: var(--muted, #9ca3af);">Pregled svih pravnih predmeta</p>
            </div>
        </div>

        {{-- Search & Filters --}}
        <div class="flex flex-col sm:flex-row gap-3 mb-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
            <div class="flex-1">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Pretraži po broju predmeta, naslovu, klijentu, protivniku, sudu..."
                    class="w-full px-3 py-2 rounded-lg text-sm"
                    style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);"
                >
            </div>
            <select
                wire:model.live="statusFilter"
                class="px-3 py-2 rounded-lg text-sm"
                style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);"
            >
                <option value="">Svi statusi</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Case List --}}
        <div class="space-y-3">
            @forelse($cases as $case)
                <a href="{{ route('case.overview', $case->id) }}" class="block rounded-lg transition-all hover:scale-[1.005]" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); text-decoration: none;">
                    <div class="p-4 sm:p-5">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-sm font-semibold truncate" style="color: var(--accent, #60a5fa);">
                                        {{ $case->title ?: $case->case_number }}
                                    </h3>
                                    @if($case->status)
                                        <span class="flex-shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded"
                                              style="background: {{ $case->status === 'active' ? 'rgba(34,197,94,0.15)' : ($case->status === 'closed' ? 'rgba(107,114,128,0.15)' : 'rgba(59,130,246,0.15)') }};
                                                     color: {{ $case->status === 'active' ? '#22c55e' : ($case->status === 'closed' ? '#9ca3af' : '#3b82f6') }};">
                                            {{ strtoupper($case->status) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs" style="color: var(--muted, #9ca3af);">
                                    {{ $case->case_number }}
                                    @if($case->court) &middot; {{ $case->court }} @endif
                                    @if($case->judge) &middot; Sudac: {{ $case->judge }} @endif
                                </p>
                            </div>
                            <div class="text-xs flex-shrink-0" style="color: var(--muted, #9ca3af);">
                                @if($case->filing_date)
                                    {{ $case->filing_date->format('d.m.Y') }}
                                @endif
                            </div>
                        </div>

                        {{-- Parties --}}
                        <div class="flex flex-wrap gap-3 mb-3 text-xs">
                            @if($case->client_name)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(34,197,94,0.15); color: #22c55e;">KLIJENT</span>
                                    <span>{{ $case->client_name }}</span>
                                </div>
                            @endif
                            @if($case->opponent_name)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #ef4444;">PROTIVNIK</span>
                                    <span>{{ $case->opponent_name }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Tags & Stats --}}
                        <div class="flex flex-wrap items-center gap-3 text-[10px]" style="color: var(--muted, #9ca3af);">
                            @if($case->case_type)
                                <span class="font-semibold px-1.5 py-0.5 rounded" style="background: rgba(139,92,246,0.15); color: #a855f7;">{{ $case->case_type }}</span>
                            @endif
                            @if(is_array($case->tags))
                                @foreach($case->tags as $tag)
                                    <span class="font-semibold px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220);">{{ $tag }}</span>
                                @endforeach
                            @endif
                            <span class="ml-auto flex items-center gap-4">
                                <span>{{ $case->uploads_count }} datoteka</span>
                                <span>{{ $case->documents_count }} dokumenata</span>
                                <span>{{ $case->textract_jobs_count }} OCR</span>
                            </span>
                        </div>

                        @if($case->description)
                            <p class="mt-2 text-xs truncate" style="color: var(--muted, #9ca3af);">{{ Str::limit($case->description, 200) }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <div class="text-center py-20 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <svg class="mx-auto h-12 w-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <h3 class="text-sm font-semibold mb-1">Nema predmeta</h3>
                    <p class="text-xs" style="color: var(--muted, #9ca3af);">
                        @if($search || $statusFilter)
                            Nema rezultata za trenutne filtere.
                        @else
                            Nema registriranih pravnih predmeta.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($cases->hasPages())
            <div class="mt-6">
                {{ $cases->links() }}
            </div>
        @endif
    </div>
</div>
