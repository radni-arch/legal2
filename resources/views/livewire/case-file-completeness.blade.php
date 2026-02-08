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
                        <a href="{{ route('case.overview', $caseData['id']) }}" class="p-1.5 rounded-lg transition-colors hover:opacity-80" style="background: var(--surface, #1f2937);" title="Natrag na pregled">
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
                <button wire:click="loadCase" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;" title="Osvježi podatke">
                    <span wire:loading.remove wire:target="loadCase">Osvježi</span>
                    <span wire:loading wire:target="loadCase" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Učitavanje...
                    </span>
                </button>
            </div>

            {{-- Tab Navigation --}}
            <div class="flex gap-1 mb-6 p-1 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <a href="{{ route('case.overview', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors hover:opacity-80" style="color: var(--muted, #9ca3af);">
                    Pregled
                </a>
                <a href="{{ route('case.completeness', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;">
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
            <div wire:loading wire:target="loadCase" class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span class="text-sm">Učitavanje podataka...</span>
            </div>

            <div wire:loading.class="opacity-50 pointer-events-none" wire:target="loadCase">

            {{-- Parties --}}
            @if($caseData['client_name'] || $caseData['opponent_name'])
                <div class="flex flex-wrap gap-4 mb-4 text-sm">
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

            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="text-xs font-medium mb-1" style="color: var(--muted, #9ca3af);">Ukupno dokumenata</div>
                    <div class="text-2xl font-bold">{{ $stats['total_uploads'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #22c55e; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                    <div class="text-xs font-medium mb-1" style="color: var(--muted, #9ca3af);">Obrađeno</div>
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $stats['processed'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #eab308; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                    <div class="text-xs font-medium mb-1" style="color: var(--muted, #9ca3af);">U obradi</div>
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $stats['pending'] }}</div>
                </div>
                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #ef4444; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                    <div class="text-xs font-medium mb-1" style="color: var(--muted, #9ca3af);">Neuspjelo</div>
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $stats['failed'] }}</div>
                </div>
            </div>

            {{-- Completeness Progress --}}
            @php
                $completeness = $stats['total_uploads'] > 0 ? round(100 * $stats['processed'] / $stats['total_uploads']) : 0;
            @endphp
            <div class="mb-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium">Kompletnost obrade</span>
                    <span class="text-sm font-bold" style="color: {{ $completeness >= 80 ? '#22c55e' : ($completeness >= 50 ? '#eab308' : '#ef4444') }};">{{ $completeness }}%</span>
                </div>
                <div class="w-full rounded-full h-2" style="background: var(--bg, #0b1220);">
                    <div class="h-2 rounded-full transition-all" style="width: {{ $completeness }}%; background: {{ $completeness >= 80 ? '#22c55e' : ($completeness >= 50 ? '#eab308' : '#ef4444') }};"></div>
                </div>
                <div class="text-[10px] mt-1" style="color: var(--muted, #9ca3af);">
                    {{ $stats['processed'] }} od {{ $stats['total_uploads'] }} dokumenata obrađeno (OCR + embedding)
                </div>
            </div>

            {{-- View Mode & Filter Controls --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                        @foreach(['matrica' => 'Matrica', 'lista' => 'Lista', 'klasa' => 'Kategorije'] as $mode => $label)
                            <button
                                wire:click="setViewMode('{{ $mode }}')"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                                style="{{ $viewMode === $mode
                                    ? 'background: var(--accent, #60a5fa); color: white;'
                                    : 'background: var(--surface, #1f2937); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    @php $filteredCount = count($this->filteredUploads); @endphp
                    @if($filter !== 'svi')
                        <span class="text-[10px] px-2 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">
                            Prikazano {{ $filteredCount }} od {{ $stats['total_uploads'] }}
                        </span>
                    @endif
                </div>
                <div class="flex gap-1">
                    @foreach(['svi' => 'Svi', 'prisutni' => 'Obrađeni', 'nedostaju' => 'Neobrađeni'] as $f => $label)
                        @php
                            $filterCount = match($f) {
                                'prisutni' => collect($uploads)->where('has_document', true)->count(),
                                'nedostaju' => collect($uploads)->where('has_document', false)->count(),
                                default => count($uploads),
                            };
                        @endphp
                        <button
                            wire:click="setFilter('{{ $f }}')"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                            style="{{ $filter === $f
                                ? 'background: ' . ($f === 'prisutni' ? '#22c55e' : ($f === 'nedostaju' ? '#ef4444' : 'var(--accent, #60a5fa)')) . '; color: white;'
                                : 'background: var(--surface, #1f2937); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);' }}"
                        >
                            {{ $label }} <span class="text-[10px] opacity-75">({{ $filterCount }})</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Content Area --}}
            @if(empty($uploads))
                <div class="text-center py-16 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                    <svg class="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    <p class="text-sm">Nema dokumenata za prikaz</p>
                    <p class="text-xs mt-1">No documents uploaded for this case yet.</p>
                </div>
            @else

                {{-- MATRICA VIEW - Grid of upload cards --}}
                @if($viewMode === 'matrica')
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach($this->filteredUploads as $upload)
                            @php
                                $isProcessed = $upload['has_document'];
                                $isFailed = $upload['status'] === 'failed';
                                $borderColor = $isFailed ? 'rgba(239,68,68,0.3)' : ($isProcessed ? 'rgba(34,197,94,0.3)' : 'rgba(234,179,8,0.3)');
                                $ext = pathinfo($upload['filename'] ?? '', PATHINFO_EXTENSION);
                                $size = $upload['file_size'] ? round($upload['file_size'] / 1024) : null;
                            @endphp
                            <div class="p-4 rounded-lg transition-all" style="background: var(--surface, #1f2937); border: 1px solid {{ $borderColor }};">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        @if($isProcessed)
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center" style="background: rgba(34,197,94,0.15);">
                                                <svg class="w-3.5 h-3.5" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        @elseif($isFailed)
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center" style="background: rgba(239,68,68,0.15);">
                                                <svg class="w-3.5 h-3.5" style="color: #ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </div>
                                        @else
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center" style="background: rgba(234,179,8,0.15);">
                                                <svg class="w-3.5 h-3.5" style="color: #eab308;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        @if($ext)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ $ext }}</span>
                                        @endif
                                    </div>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" style="background: rgba({{ $isProcessed ? '34,197,94' : ($isFailed ? '239,68,68' : '234,179,8') }},0.1); color: {{ $isProcessed ? '#22c55e' : ($isFailed ? '#ef4444' : '#eab308') }};">
                                        {{ $isProcessed ? 'OBRAĐENO' : ($isFailed ? 'NEUSPJELO' : 'ČEKA') }}
                                    </span>
                                </div>
                                <h3 class="text-sm font-semibold mb-0.5 truncate" title="{{ $upload['filename'] }}">{{ $upload['filename'] }}</h3>
                                <div class="text-[10px]" style="color: var(--muted, #9ca3af);">
                                    @if($size) {{ $size > 1024 ? round($size / 1024, 1) . ' MB' : $size . ' KB' }} &middot; @endif
                                    {{ $upload['uploaded_at'] }}
                                </div>
                                @if($upload['error'])
                                    <div class="mt-2 text-[10px] truncate" style="color: #ef4444;">{{ $upload['error'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- LISTA VIEW - Table --}}
                @if($viewMode === 'lista')
                    <div class="rounded-lg overflow-hidden" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="overflow-x-auto">
                            <table class="min-w-full" style="color: var(--fg, #e5e7eb);">
                                <thead>
                                    <tr style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">
                                        <th class="px-3 py-2 text-left text-xs font-medium">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium">Datoteka</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium">Tip</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium">Veličina</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium">Učitano</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium">Greška</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->filteredUploads as $upload)
                                        @php
                                            $isProcessed = $upload['has_document'];
                                            $isFailed = $upload['status'] === 'failed';
                                            $size = $upload['file_size'] ? round($upload['file_size'] / 1024) : null;
                                        @endphp
                                        <tr style="border-top: 1px solid var(--border, #374151);">
                                            <td class="px-3 py-2">
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba({{ $isProcessed ? '34,197,94' : ($isFailed ? '239,68,68' : '234,179,8') }},0.15); color: {{ $isProcessed ? '#22c55e' : ($isFailed ? '#ef4444' : '#eab308') }};">
                                                    {{ $isProcessed ? 'OBRAĐENO' : ($isFailed ? 'NEUSPJELO' : 'ČEKA') }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-sm font-medium max-w-xs truncate">{{ $upload['filename'] }}</td>
                                            <td class="px-3 py-2 text-xs" style="color: var(--muted, #9ca3af);">{{ $upload['mime_type'] }}</td>
                                            <td class="px-3 py-2 text-xs text-right" style="color: var(--muted, #9ca3af);">
                                                @if($size) {{ $size > 1024 ? round($size / 1024, 1) . ' MB' : $size . ' KB' }} @else - @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs" style="color: var(--muted, #9ca3af);">{{ $upload['uploaded_at'] }}</td>
                                            <td class="px-3 py-2 text-xs max-w-xs truncate" style="color: #ef4444;">{{ $upload['error'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-8 text-center text-sm" style="color: var(--muted, #9ca3af);">
                                                Nema dokumenata za prikaz
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- KATEGORIJE VIEW - Grouped by category --}}
                @if($viewMode === 'klasa')
                    @php $byCategory = $this->docsByCategory; @endphp
                    @if(!empty($byCategory))
                        @foreach($byCategory as $group)
                            <div class="mb-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs font-bold px-2 py-1 rounded" style="background: var(--accent, #60a5fa); color: white;">{{ $group['category'] }}</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ $group['count'] }} chunks</span>
                                </div>
                                <div class="rounded-lg overflow-hidden" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    @foreach($group['documents'] as $doc)
                                        <div class="flex items-center gap-3 px-3 py-2 {{ !$loop->first ? 'border-t' : '' }}" style="{{ !$loop->first ? 'border-color: var(--border, #374151);' : '' }}">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium truncate">{{ $doc['title'] ?: 'Untitled chunk' }}</div>
                                                <div class="text-[10px]" style="color: var(--muted, #9ca3af);">
                                                    @if($doc['author']) {{ $doc['author'] }} &middot; @endif
                                                    @if($doc['document_date']) {{ $doc['document_date'] }} &middot; @endif
                                                    @if($doc['token_count']) {{ number_format($doc['token_count']) }} tokens @endif
                                                </div>
                                            </div>
                                            <div class="flex gap-1 flex-shrink-0">
                                                @if($doc['has_content'])
                                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(34,197,94,0.15); color: #22c55e;">TEKST</span>
                                                @endif
                                                @if($doc['has_embedding'])
                                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: rgba(59,130,246,0.15); color: #3b82f6;">EMBEDDING</span>
                                                @endif
                                                @if($doc['language'])
                                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ strtoupper($doc['language']) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-12 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                            <p class="text-sm">Nema obrađenih dokumenata za kategorizaciju</p>
                        </div>
                    @endif
                @endif

            @endif

            {{-- OCR Pipeline Status --}}
            @if(!empty($textractJobs))
                @php $reviewCount = collect($textractJobs)->where('needs_review', true)->count(); @endphp
                <div class="mt-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-sm font-semibold">OCR Pipeline Status</h3>
                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ count($textractJobs) }} poslova</span>
                        @if($reviewCount > 0)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #ef4444;">{{ $reviewCount }} za pregled</span>
                        @endif
                    </div>
                    <div class="space-y-2">
                        @foreach($textractJobs as $job)
                            @php
                                $statusColor = match($job['status']) {
                                    'completed', 'succeeded' => '#22c55e',
                                    'failed' => '#ef4444',
                                    'processing' => '#eab308',
                                    default => '#6b7280',
                                };
                                $embColor = match($job['embedding_status']) {
                                    'synced' => '#22c55e',
                                    'failed' => '#ef4444',
                                    'pending' => '#eab308',
                                    default => '#6b7280',
                                };
                                $graphColor = match($job['graph_sync_status']) {
                                    'synced' => '#22c55e',
                                    'failed', 'blocked' => '#ef4444',
                                    'pending' => '#eab308',
                                    default => '#6b7280',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-3 py-2 rounded" style="background: var(--bg, #0b1220);">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">{{ $job['filename'] }}</div>
                                    <div class="text-[10px]" style="color: var(--muted, #9ca3af);">
                                        {{ $job['ocr_engine'] }} &middot; {{ $job['created_at'] }}
                                        @if($job['manually_edited']) &middot; <span style="color: #a855f7;">manually edited</span> @endif
                                    </div>
                                </div>
                                <div class="flex gap-1.5 flex-shrink-0">
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: {{ $statusColor }}20; color: {{ $statusColor }};" title="OCR Status">{{ strtoupper($job['status'] ?? 'pending') }}</span>
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: {{ $embColor }}20; color: {{ $embColor }};" title="Embedding Status">EMB: {{ strtoupper($job['embedding_status'] ?? 'pending') }}</span>
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: {{ $graphColor }}20; color: {{ $graphColor }};" title="Graph Sync Status">GRAF: {{ strtoupper($job['graph_sync_status'] ?? 'pending') }}</span>
                                </div>
                                @if($job['needs_review'])
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" style="background: rgba(239,68,68,0.15); color: #ef4444;">PREGLED</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
