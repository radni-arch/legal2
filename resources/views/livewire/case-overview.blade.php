<div class="min-h-screen p-6 md:p-8" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    {{-- Offline Banner --}}
    <div wire:offline class="fixed top-0 inset-x-0 z-50 px-4 py-2 text-center text-sm font-medium" style="background: #ef4444; color: white;">
        Izgubljena veza s poslužiteljem. Promjene neće biti spremljene.
    </div>

    <div class="max-w-7xl mx-auto">

        @if(!$caseData)
            <div class="text-center py-20" style="color: var(--muted, #9ca3af);">
                <svg class="mx-auto h-16 w-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h2 class="text-xl font-semibold mb-2">Predmet nije pronađen</h2>
                <p class="text-sm">Case not found or has been removed.</p>
            </div>
        @else
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <a href="{{ url()->previous() }}" class="p-1.5 rounded-lg transition-colors hover:opacity-80" style="background: var(--surface, #1f2937);" title="Natrag">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold">{{ $caseData['title'] ?: $caseData['case_number'] }}</h1>
                            <p class="text-sm" style="color: var(--muted, #9ca3af);">
                                {{ $caseData['case_number'] }} &middot; {{ $caseData['court'] }}
                                @if($caseData['judge'] !== 'N/A') &middot; Sudac: {{ $caseData['judge'] }} @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if($caseData['status'])
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded" style="background: rgba(59,130,246,0.15); color: #3b82f6;">{{ strtoupper($caseData['status']) }}</span>
                        @endif
                        @if($caseData['case_type'])
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded" style="background: rgba(139,92,246,0.15); color: #a855f7;">{{ $caseData['case_type'] }}</span>
                        @endif
                        @foreach($caseData['tags'] as $tag)
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded" style="background: var(--surface, #1f2937); color: var(--muted, #9ca3af);">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
                <button wire:click="loadOverview" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;" title="Osvježi podatke">
                    <span wire:loading.remove wire:target="loadOverview">Osvježi</span>
                    <span wire:loading wire:target="loadOverview" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Učitavanje...
                    </span>
                </button>
            </div>

            {{-- Tab Navigation --}}
            <div class="flex gap-1 mb-6 p-1 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <a href="{{ route('case.overview', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;">
                    Pregled
                </a>
                <a href="{{ route('case.completeness', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors hover:opacity-80" style="color: var(--muted, #9ca3af);">
                    Kompletnost
                </a>
                <a href="{{ route('case.analysis', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors hover:opacity-80" style="color: var(--muted, #9ca3af);">
                    Analiza
                </a>
            </div>

            {{-- Error Banner --}}
            @if($error)
                <div class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444;">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span class="text-sm">{{ $error }}</span>
                </div>
            @endif

            {{-- Loading Overlay --}}
            <div wire:loading wire:target="loadOverview" class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span class="text-sm">Učitavanje podataka...</span>
            </div>

            <div wire:loading.class="opacity-50 pointer-events-none" wire:target="loadOverview">

            {{-- Parties --}}
            @if($caseData['client_name'] || $caseData['opponent_name'])
                <div class="flex flex-wrap gap-4 mb-6 text-sm">
                    @if($caseData['client_name'])
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(34,197,94,0.15); color: #22c55e;">KLIJENT</span>
                            <span>{{ $caseData['client_name'] }}</span>
                        </div>
                    @endif
                    @if($caseData['opponent_name'])
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #ef4444;">PROTIVNIK</span>
                            <span>{{ $caseData['opponent_name'] }}</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Case Info --}}
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 mb-6">
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Nadležnost</div>
                    <div class="text-sm font-semibold">{{ $caseData['jurisdiction'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Sudac</div>
                    <div class="text-sm font-semibold">{{ $caseData['judge'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Datum podnošenja</div>
                    <div class="text-sm font-semibold">{{ $caseData['filing_date'] ?? '-' }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Vrsta predmeta</div>
                    <div class="text-sm font-semibold">{{ $caseData['case_type'] ?? '-' }}</div>
                </div>
            </div>

            {{-- Description --}}
            @if($caseData['description'])
                <div class="mb-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Opis</div>
                    <div class="text-sm leading-relaxed">{{ $caseData['description'] }}</div>
                </div>
            @endif

            {{-- Quick Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Učitani dokumenti</div>
                    <div class="text-2xl font-bold">{{ $stats['uploads'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Obrađeni chunk-ovi</div>
                    <div class="text-2xl font-bold" style="color: #3b82f6;">{{ $stats['documents'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Embeddings</div>
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $stats['embeddings'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Predikcije</div>
                    <div class="text-2xl font-bold" style="color: #f97316;">{{ $stats['predictions'] }}</div>
                </div>
            </div>

            {{-- OCR Pipeline Summary --}}
            @if($stats['textract_jobs'] > 0)
                @php
                    $ocrPct = $stats['textract_jobs'] > 0 ? round(100 * $stats['textract_completed'] / $stats['textract_jobs']) : 0;
                @endphp
                <div class="mb-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">OCR Pipeline</span>
                        <span class="text-sm font-bold" style="color: {{ $ocrPct >= 80 ? '#22c55e' : ($ocrPct >= 50 ? '#eab308' : '#ef4444') }};">{{ $ocrPct }}%</span>
                    </div>
                    <div class="w-full rounded-full h-2" style="background: var(--bg, #0b1220);">
                        <div class="h-2 rounded-full transition-all" style="width: {{ $ocrPct }}%; background: {{ $ocrPct >= 80 ? '#22c55e' : ($ocrPct >= 50 ? '#eab308' : '#ef4444') }};"></div>
                    </div>
                    <div class="text-[10px] mt-1" style="color: var(--muted, #9ca3af);">
                        {{ $stats['textract_completed'] }} od {{ $stats['textract_jobs'] }} OCR poslova završeno
                    </div>
                </div>
            @endif

            {{-- Status Indicators --}}
            <div class="grid gap-3 md:grid-cols-2 mb-6">
                <div class="p-4 rounded-lg flex items-center gap-3" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: {{ $stats['has_features'] ? 'rgba(34,197,94,0.15)' : 'rgba(107,114,128,0.15)' }};">
                        @if($stats['has_features'])
                            <svg class="w-4 h-4" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-4 h-4" style="color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-semibold">Značajke predmeta</div>
                        <div class="text-[10px]" style="color: var(--muted, #9ca3af);">{{ $stats['has_features'] ? 'Ekstrahirane' : 'Čekaju ekstrakciju' }}</div>
                    </div>
                </div>
                <div class="p-4 rounded-lg flex items-center gap-3" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: {{ $stats['has_strategy'] ? 'rgba(34,197,94,0.15)' : 'rgba(107,114,128,0.15)' }};">
                        @if($stats['has_strategy'])
                            <svg class="w-4 h-4" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-4 h-4" style="color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-semibold">Strategija</div>
                        <div class="text-[10px]" style="color: var(--muted, #9ca3af);">{{ $stats['has_strategy'] ? 'Generirana' : 'Čeka generiranje' }}</div>
                    </div>
                </div>
            </div>

            {{-- Navigation Cards --}}
            <div class="grid gap-4 md:grid-cols-2">
                <a href="{{ route('case.completeness', $caseData['id']) }}" class="group p-6 rounded-lg transition-all hover:scale-[1.01]" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.15);">
                            <svg class="w-5 h-5" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold">Kompletnost spisa</h3>
                            <p class="text-[10px]" style="color: var(--muted, #9ca3af);">Pregled učitanih dokumenata, OCR statusa i obrade</p>
                        </div>
                    </div>
                    <div class="flex gap-4 text-[10px]" style="color: var(--muted, #9ca3af);">
                        <span>{{ $stats['uploads'] }} učitanih</span>
                        <span>{{ $stats['documents'] }} obrađenih</span>
                        <span>{{ $stats['embeddings'] }} embedding-a</span>
                    </div>
                </a>

                <a href="{{ route('case.analysis', $caseData['id']) }}" class="group p-6 rounded-lg transition-all hover:scale-[1.01]" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.15);">
                            <svg class="w-5 h-5" style="color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold">Analiza predmeta</h3>
                            <p class="text-[10px]" style="color: var(--muted, #9ca3af);">Značajke, strategija, predikcije i statistike</p>
                        </div>
                    </div>
                    <div class="flex gap-4 text-[10px]" style="color: var(--muted, #9ca3af);">
                        <span>{{ $stats['has_features'] ? 'Značajke ✓' : 'Nema značajki' }}</span>
                        <span>{{ $stats['has_strategy'] ? 'Strategija ✓' : 'Nema strategije' }}</span>
                        <span>{{ $stats['predictions'] }} predikcija</span>
                    </div>
                </a>
            </div>

            </div>{{-- /wire:loading overlay --}}

            {{-- Last Loaded Footer --}}
            @if($lastLoadedAt)
                <div class="mt-6 pt-3 text-center" style="border-top: 1px solid var(--border, #374151);">
                    <span class="text-[10px]" style="color: var(--muted, #9ca3af);">Zadnje osvježavanje: {{ $lastLoadedAt }}</span>
                </div>
            @endif
        @endif
    </div>
</div>
