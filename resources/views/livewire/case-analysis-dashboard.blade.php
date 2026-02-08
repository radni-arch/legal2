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
                            <h1 class="text-2xl font-bold">Analiza: {{ $caseData['title'] ?: $caseData['case_number'] }}</h1>
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
                <button wire:click="loadAnalysis" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;" title="Osvježi podatke">
                    <span wire:loading.remove wire:target="loadAnalysis">Osvježi</span>
                    <span wire:loading wire:target="loadAnalysis" class="flex items-center gap-1.5">
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
                <a href="{{ route('case.completeness', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors hover:opacity-80" style="color: var(--muted, #9ca3af);">
                    Kompletnost
                </a>
                <a href="{{ route('case.analysis', $caseData['id']) }}" class="flex-1 text-center px-3 py-2 rounded-md text-sm font-medium transition-colors" style="background: var(--accent, #60a5fa); color: white;">
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

            {{-- Loading State --}}
            <div wire:loading wire:target="loadAnalysis" class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span class="text-sm">Učitavanje analize...</span>
            </div>

            <div wire:loading.class="opacity-50 pointer-events-none" wire:target="loadAnalysis">

                {{-- Quick Stats --}}
                <div class="grid gap-3 grid-cols-2 md:grid-cols-4 lg:grid-cols-6 mb-6">
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Dokumenti</div>
                        <div class="text-2xl font-bold">{{ $documentStats['total_uploads'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Chunk-ovi</div>
                        <div class="text-2xl font-bold">{{ number_format($documentStats['total_chunks'] ?? 0) }}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Tokeni</div>
                        <div class="text-2xl font-bold">{{ number_format($documentStats['total_tokens'] ?? 0) }}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Embeddings</div>
                        <div class="text-2xl font-bold" style="color: #3b82f6;">{{ $documentStats['with_embeddings'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">OCR obrađeno</div>
                        <div class="text-2xl font-bold" style="color: #22c55e;">{{ $textractStats['completed'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-1" style="color: var(--muted, #9ca3af);">Strategije</div>
                        <div class="text-2xl font-bold" style="color: #a855f7;">{{ $strategy ? 'v' . $strategy['version'] : '-' }}</div>
                    </div>
                </div>

                {{-- Case Features --}}
                @if($features)
                    <div class="mb-6">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="h-5 w-5" style="color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <h2 class="text-sm font-semibold">Značajke predmeta</h2>
                            @if($features['extracted_at'])
                                <span class="text-[10px]" style="color: var(--muted, #9ca3af);">Ekstrakt: {{ $features['extracted_at'] }}</span>
                            @endif
                        </div>

                        <div class="grid gap-4 md:grid-cols-3 mb-4">
                            {{-- Complexity --}}
                            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Složenost</div>
                                @if($features['complexity_score'])
                                    @php $cs = $features['complexity_score']; @endphp
                                    <div class="text-3xl font-black mb-1" style="color: {{ $cs > 0.7 ? '#ef4444' : ($cs > 0.4 ? '#eab308' : '#22c55e') }};">
                                        {{ round($cs * 100) }}
                                    </div>
                                    <div class="w-full rounded-full h-2 mb-1" style="background: var(--bg, #0b1220);">
                                        <div class="h-2 rounded-full" style="width: {{ round($cs * 100) }}%; background: {{ $cs > 0.7 ? '#ef4444' : ($cs > 0.4 ? '#eab308' : '#22c55e') }};"></div>
                                    </div>
                                @endif
                                @if($features['complexity_level'])
                                    <div class="text-xs font-semibold uppercase">{{ $features['complexity_level'] }}</div>
                                @endif
                            </div>

                            {{-- Evidence Strength --}}
                            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Snaga dokaza</div>
                                @if($features['evidence_strength_score'] !== null)
                                    @php $es = $features['evidence_strength_score']; @endphp
                                    <div class="text-3xl font-black mb-1" style="color: {{ $es > 0.7 ? '#22c55e' : ($es > 0.4 ? '#eab308' : '#ef4444') }};">
                                        {{ round($es * 100) }}
                                    </div>
                                    <div class="w-full rounded-full h-2 mb-1" style="background: var(--bg, #0b1220);">
                                        <div class="h-2 rounded-full" style="width: {{ round($es * 100) }}%; background: {{ $es > 0.7 ? '#22c55e' : ($es > 0.4 ? '#eab308' : '#ef4444') }};"></div>
                                    </div>
                                @else
                                    <div class="text-lg font-semibold" style="color: var(--muted, #9ca3af);">-</div>
                                @endif
                                <div class="text-[10px]" style="color: var(--muted, #9ca3af);">
                                    {{ count($features['evidence_types']) }} tipova dokaza
                                </div>
                            </div>

                            {{-- Key Numbers --}}
                            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Ključni brojevi</div>
                                <div class="space-y-1.5">
                                    @foreach([
                                        ['Dokumenata', $features['document_count']],
                                        ['Presedana', $features['precedent_count']],
                                        ['Stranaka', $features['party_count']],
                                        ['Zahtjeva', $features['claim_count']],
                                        ['Podnesaka', $features['motion_count']],
                                        ['Ročišta', $features['hearing_count']],
                                    ] as [$label, $val])
                                        @if($val !== null)
                                            <div class="flex justify-between text-sm">
                                                <span style="color: var(--muted, #9ca3af);">{{ $label }}</span>
                                                <span class="font-semibold">{{ $val }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Legal Issues & Applicable Laws --}}
                        <div class="grid gap-4 md:grid-cols-2">
                            @if(!empty($features['legal_issues']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Pravna pitanja</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($features['legal_issues'] as $issue)
                                            <span class="text-xs px-2 py-0.5 rounded" style="background: rgba(139,92,246,0.1); color: #a855f7;">{{ $issue }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if(!empty($features['applicable_laws']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Primjenjivi propisi</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($features['applicable_laws'] as $law)
                                            <span class="text-xs px-2 py-0.5 rounded" style="background: rgba(59,130,246,0.1); color: #3b82f6;">{{ $law }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Duration & Claim --}}
                        @if($features['estimated_duration_days'] || $features['claim_amount'])
                            <div class="grid gap-4 md:grid-cols-2 mt-4">
                                @if($features['estimated_duration_days'])
                                    <div class="p-3 rounded-lg flex items-center gap-3" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                        <svg class="h-5 w-5 flex-shrink-0" style="color: #eab308;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div>
                                            <div class="text-[10px] uppercase" style="color: var(--muted, #9ca3af);">Procijenjeno trajanje</div>
                                            <div class="text-sm font-semibold">{{ $features['estimated_duration_days'] }} dana</div>
                                        </div>
                                    </div>
                                @endif
                                @if($features['claim_amount'])
                                    <div class="p-3 rounded-lg flex items-center gap-3" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                        <svg class="h-5 w-5 flex-shrink-0" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div>
                                            <div class="text-[10px] uppercase" style="color: var(--muted, #9ca3af);">Iznos tužbenog zahtjeva</div>
                                            <div class="text-sm font-semibold">{{ number_format((float)$features['claim_amount'], 2) }} EUR @if($features['claim_amount_category']) ({{ $features['claim_amount_category'] }}) @endif</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Strategy --}}
                @if($strategy)
                    <div class="mb-6">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="h-5 w-5" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <h2 class="text-sm font-semibold">Strategija (v{{ $strategy['version'] }})</h2>
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: {{ $strategy['status'] === 'active' ? 'rgba(34,197,94,0.15)' : 'rgba(234,179,8,0.15)' }}; color: {{ $strategy['status'] === 'active' ? '#22c55e' : '#eab308' }};">
                                {{ strtoupper($strategy['status']) }}
                            </span>
                            @if($strategy['confidence_score'])
                                <span class="text-[10px] font-semibold" style="color: var(--muted, #9ca3af);">Pouzdanost: {{ round($strategy['confidence_score'] * 100) }}%</span>
                            @endif
                        </div>

                        @if($strategy['summary'])
                            <div class="p-4 rounded-lg mb-3" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                <div class="text-sm leading-relaxed">{{ $strategy['summary'] }}</div>
                            </div>
                        @endif

                        <div class="grid gap-3 md:grid-cols-2">
                            {{-- Arguments --}}
                            @if(!empty($strategy['arguments']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #22c55e; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: #22c55e;">Argumenti</div>
                                    <div class="space-y-1.5">
                                        @foreach($strategy['arguments'] as $arg)
                                            <div class="text-sm">
                                                @if(is_string($arg)) {{ $arg }}
                                                @elseif(is_array($arg)) {{ $arg['text'] ?? $arg['title'] ?? json_encode($arg) }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Risks --}}
                            @if(!empty($strategy['risks']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #ef4444; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: #ef4444;">Rizici</div>
                                    <div class="space-y-1.5">
                                        @foreach($strategy['risks'] as $risk)
                                            <div class="text-sm">
                                                @if(is_string($risk)) {{ $risk }}
                                                @elseif(is_array($risk)) {{ $risk['text'] ?? $risk['description'] ?? json_encode($risk) }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Objectives --}}
                            @if(!empty($strategy['objectives']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #3b82f6; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: #3b82f6;">Ciljevi</div>
                                    <div class="space-y-1.5">
                                        @foreach($strategy['objectives'] as $obj)
                                            <div class="text-sm">
                                                @if(is_string($obj)) {{ $obj }}
                                                @elseif(is_array($obj)) {{ $obj['text'] ?? $obj['title'] ?? json_encode($obj) }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Recommendations --}}
                            @if(!empty($strategy['recommendations']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border-left: 3px solid #a855f7; border-top: 1px solid var(--border, #374151); border-right: 1px solid var(--border, #374151); border-bottom: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: #a855f7;">Preporuke</div>
                                    <div class="space-y-1.5">
                                        @foreach($strategy['recommendations'] as $rec)
                                            <div class="text-sm">
                                                @if(is_string($rec)) {{ $rec }}
                                                @elseif(is_array($rec)) {{ $rec['text'] ?? $rec['title'] ?? json_encode($rec) }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Predictions --}}
                @if(!empty($predictions))
                    <div class="mb-6">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="h-5 w-5" style="color: #f97316;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            <h2 class="text-sm font-semibold">Predikcije</h2>
                        </div>
                        <div class="space-y-2">
                            @foreach($predictions as $pred)
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $pred['type'])) }}</span>
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ $pred['model_version'] }}</span>
                                            </div>
                                            @if($pred['reasoning'])
                                                <p class="text-xs mb-1" style="color: var(--muted, #9ca3af);">{{ \Illuminate\Support\Str::limit($pred['reasoning'], 200) }}</p>
                                            @endif
                                            @if(!empty($pred['prediction']))
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($pred['prediction'] as $key => $val)
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
                                                            {{ $key }}: {{ is_array($val) ? json_encode($val) : $val }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        @if($pred['confidence'])
                                            <div class="flex-shrink-0 text-right">
                                                @php $conf = $pred['confidence']; @endphp
                                                <div class="text-lg font-bold" style="color: {{ $conf > 0.7 ? '#22c55e' : ($conf > 0.4 ? '#eab308' : '#6b7280') }};">
                                                    {{ round($conf * 100) }}%
                                                </div>
                                                <div class="w-12 rounded-full h-1.5 mt-1" style="background: var(--bg, #0b1220);">
                                                    <div class="h-1.5 rounded-full" style="width: {{ round($conf * 100) }}%; background: {{ $conf > 0.7 ? '#22c55e' : ($conf > 0.4 ? '#eab308' : '#6b7280') }};"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-[10px] mt-2" style="color: var(--muted, #9ca3af);">{{ $pred['predicted_at'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Document Analysis --}}
                @if(!empty($documentStats) && ($documentStats['total_chunks'] ?? 0) > 0)
                    <div class="mb-6">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="h-5 w-5" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                            <h2 class="text-sm font-semibold">Analiza dokumenata</h2>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            {{-- By Category --}}
                            @if(!empty($documentStats['by_category']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Po kategoriji</div>
                                    @php $maxCat = max(array_values($documentStats['by_category'])) ?: 1; @endphp
                                    <div class="space-y-1.5">
                                        @foreach($documentStats['by_category'] as $cat => $count)
                                            <div>
                                                <div class="flex justify-between text-sm mb-0.5">
                                                    <span>{{ $cat ?: 'Uncategorized' }}</span>
                                                    <span class="font-semibold">{{ $count }}</span>
                                                </div>
                                                <div class="w-full rounded-full h-1" style="background: var(--bg, #0b1220);">
                                                    <div class="h-1 rounded-full" style="width: {{ round(100 * $count / $maxCat) }}%; background: #3b82f6;"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- By Language --}}
                            @if(!empty($documentStats['by_language']))
                                <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                                    <div class="text-[10px] uppercase font-semibold mb-2" style="color: var(--muted, #9ca3af);">Po jeziku</div>
                                    <div class="space-y-1.5">
                                        @foreach($documentStats['by_language'] as $lang => $count)
                                            <div class="flex justify-between text-sm">
                                                <span>{{ $lang ? strtoupper($lang) : 'Unknown' }}</span>
                                                <span class="font-semibold">{{ $count }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- OCR Pipeline --}}
                @if(!empty($textractStats) && ($textractStats['total'] ?? 0) > 0)
                    <div class="mb-6 p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                        <div class="text-[10px] uppercase font-semibold mb-3" style="color: var(--muted, #9ca3af);">OCR Pipeline</div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach([
                                ['Završeno', $textractStats['completed'], '#22c55e'],
                                ['Neuspjelo', $textractStats['failed'], '#ef4444'],
                                ['U obradi', $textractStats['pending'], '#eab308'],
                                ['Za pregled', $textractStats['needs_review'], '#f97316'],
                                ['Embeddings', $textractStats['embeddings_synced'], '#3b82f6'],
                                ['Graf', $textractStats['graph_synced'], '#8b5cf6'],
                                ['Ručno uređeno', $textractStats['manually_edited'], '#a855f7'],
                                ['Ukupno', $textractStats['total'], 'var(--fg, #e5e7eb)'],
                            ] as [$label, $count, $color])
                                <div>
                                    <div class="text-[10px]" style="color: var(--muted, #9ca3af);">{{ $label }}</div>
                                    <div class="text-lg font-bold" style="color: {{ $color }};">{{ $count }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Empty State --}}
                @if(!$features && !$strategy && empty($predictions) && ($documentStats['total_chunks'] ?? 0) === 0)
                    <div class="text-center py-16 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151); color: var(--muted, #9ca3af);">
                        <svg class="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-sm">Nema podataka za analizu</p>
                        <p class="text-xs mt-1">Upload documents and run analysis to see results here.</p>
                    </div>
                @endif

            </div>

            {{-- Last Loaded Footer --}}
            @if($lastLoadedAt)
                <div class="mt-6 pt-3 text-center" style="border-top: 1px solid var(--border, #374151);">
                    <span class="text-[10px]" style="color: var(--muted, #9ca3af);">Zadnje osvježavanje: {{ $lastLoadedAt }}</span>
                </div>
            @endif
        @endif
    </div>
</div>
