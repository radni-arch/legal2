{{-- E-Predmet Lookup Tab --}}
{{-- Case lookup form and data display --}}

<form wire:submit.prevent="fetch" class="grid gap-2 md:grid-cols-12 items-end" dusk="fetch-form">
    <div class="md:col-span-3">
        <label for="sud" class="block text-[11px] muted font-medium">Sud ID</label>
        <input id="sud" type="text" wire:model.defer="sud" class="mt-1 dt-input epw-input" placeholder="5107" dusk="sud-input"
               @if($loading) disabled @endif>
        @error('sud')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;" dusk="sud-error">{{ $message }}</div>@enderror
    </div>
    <div class="md:col-span-6">
        <label for="oznakaBroj" class="block text-[11px] muted font-medium">Oznaka/Broj</label>
        <input id="oznakaBroj" type="text" wire:model.defer="oznakaBroj" class="mt-1 dt-input epw-input" placeholder="Pp Prz-74/2025" dusk="oznaka-broj-input"
               @if($loading) disabled @endif>
        @error('oznakaBroj')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;" dusk="oznaka-broj-error">{{ $message }}</div>@enderror
    </div>
    <div class="md:col-span-3" style="display:flex; gap:.35rem; align-items:end;">
        <button type="submit" class="btn-primary epw-btn" dusk="fetch-button"
                wire:loading.attr="disabled" wire:target="fetch"
                @if($loading) disabled @endif>
            <span wire:loading wire:target="fetch" class="epw-spinner"></span>
            <span wire:loading.remove wire:target="fetch">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <span wire:loading wire:target="fetch">Fetching...</span>
            <span wire:loading.remove wire:target="fetch">Fetch</span>
        </button>
        <button type="button" class="btn-secondary epw-btn" wire:click="$set('data', null)" dusk="clear-button"
                @if($loading) disabled @endif>
            Clear
        </button>
    </div>
</form>

@if ($error)
    <div class="mt-2 alert alert-error" style="padding:.5rem;" dusk="error-alert">
        <div class="font-semibold mb-1">Error</div>
        <div dusk="error-message">{{ $error }}</div>
        <div class="mt-1 text-xs" style="color:#fca5a5; opacity:.85;">Tip: ensure GRAPHQL_ENDPOINT and GRAPHQL_TOKEN are set, or that fallback hints match actual API.</div>
    </div>
@endif

{{-- Loading skeleton --}}
<div class="mt-2" wire:loading wire:target="fetch" dusk="loading-indicator">
    <div class="card" style="padding:.75rem; background: var(--bg);">
        <div style="display:flex; align-items:center; gap:.5rem; color: var(--muted);">
            <span class="epw-spinner"></span>
            <span>Fetching case data from e-Predmet API...</span>
        </div>
        <div class="mt-2 grid gap-2 md:grid-cols-3">
            @for($i = 0; $i < 3; $i++)
                <div class="card epw-card" style="opacity:.3;">
                    <div style="height:.6rem; width:40%; background:var(--muted); border-radius:.25rem; margin-bottom:.3rem;"></div>
                    <div style="height:.9rem; width:70%; background:var(--muted); border-radius:.25rem;"></div>
                </div>
            @endfor
        </div>
    </div>
</div>

@if ($data)
    <div class="mt-2 grid gap-2" dusk="case-data" wire:loading.class="opacity-50" wire:target="fetch">
        {{-- Pismena Analysis Highlights --}}
        @if(!empty($pismenaAnalysis['highlights'] ?? []))
            <div class="grid gap-1.5" dusk="pismena-highlights">
                @foreach($pismenaAnalysis['highlights'] as $highlight)
                    <div class="px-3 py-2 rounded-lg epw-highlight-{{ $highlight['type'] }}" style="font-size:.85rem;">
                        <span class="font-semibold" style="color: var(--fg)">{{ $highlight['text'] }}</span>
                        <span class="muted ml-1">{{ $highlight['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Summary grid -->
        <div class="grid gap-2 md:grid-cols-3">
            <div class="card epw-card" dusk="oznaka-broj-card">
                <div class="text-[10px] uppercase muted">Oznaka/Broj</div>
                <div class="font-semibold epw-title" dusk="oznaka-broj-value">{{ $data['oznakaBroj'] ?? '---' }}</div>
            </div>
            <div class="card epw-card" dusk="upisnik-card">
                <div class="text-[10px] uppercase muted">Upisnik</div>
                <div class="font-semibold epw-title" dusk="upisnik-value">{{ ($data['upisnikNaziv'] ?? null) ? ($data['upisnikNaziv'] . ' (' . ($data['upisnikOznaka'] ?? '---') . ')') : '---' }}</div>
            </div>
            <div class="card epw-card" dusk="vrsta-predmeta-card">
                <div class="text-[10px] uppercase muted">Vrsta predmeta</div>
                <div class="font-semibold epw-title" dusk="vrsta-predmeta-value">{{ $data['vrstaPredmeta'] ?? '---' }}</div>
            </div>
        </div>

        <div class="grid gap-2 md:grid-cols-3">
            <div class="card epw-card" dusk="vrsta-odluke-card">
                <div class="text-[10px] uppercase muted">Vrsta odluke</div>
                <div class="epw-text" dusk="vrsta-odluke-value">{{ $data['vrstaOdluke'] ?? '---' }}</div>
            </div>
            <div class="card epw-card" dusk="spis-status-card">
                <div class="text-[10px] uppercase muted">Spis status</div>
                <div class="epw-text" dusk="spis-status-value">Visi sud: <span class="font-medium">{{ ($data['spisNaVisemSudu'] ?? false) ? 'da' : 'ne' }}</span> - Izvan suda: <span class="font-medium">{{ ($data['spisIzvanSuda'] ?? false) ? 'da' : 'ne' }}</span></div>
            </div>
            <div class="card epw-card" dusk="last-update-card">
                <div class="text-[10px] uppercase muted">Last update</div>
                @if(!empty($data['lastUpdateTime'] ?? null))
                    <div class="epw-text" dusk="last-update-value"><span class="font-medium">{{ \Carbon\Carbon::parse($data['lastUpdateTime'])->format('Y-m-d H:i:s') }}</span></div>
                @else
                    <div class="epw-text" dusk="last-update-value"><span class="font-medium">-</span></div>
                @endif
            </div>
        </div>

        <!-- Dates panel -->
        <div class="card" style="border-radius:1rem; padding:0;" dusk="dates-panel">
            <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Kljucni datumi</div>
            <div class="p-3 grid gap-2 md:grid-cols-3 epw-text">
                @foreach ($dateLabels as $k => $label)
                    <div class="card epw-card" dusk="date-{{ $k }}">
                        <div class="text-[10px] uppercase muted">{{ $label }}</div>
                        @if (empty($data[$k] ?? null))
                            <div class="font-medium" style="color: var(--fg)">---</div>
                        @else
                            <div class="font-medium" style="color: var(--fg)">{{ Carbon\Carbon::parse($data[$k])->format('Y-m-d H:i:s') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pismena Analysis Summary --}}
        @if(!empty($pismenaAnalysis) && ($pismenaAnalysis['total_documents'] ?? 0) > 0)
            <div class="card" style="border-radius:1rem; padding:0;" dusk="pismena-analysis-panel">
                <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                    <span>Pismena Analysis</span>
                    <span class="text-xs muted font-normal">{{ $pismenaAnalysis['total_documents'] }} documents</span>
                </div>
                <div class="p-3">
                    <div class="grid gap-2 md:grid-cols-4 mb-3">
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Total Docs</div>
                            <div class="font-semibold text-lg" style="color: var(--fg)">{{ $pismenaAnalysis['total_documents'] }}</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Zahtjevi</div>
                            <div class="font-semibold text-lg" style="color: #3b82f6">{{ count($pismenaAnalysis['zahtjevi'] ?? []) }}</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Naredbe</div>
                            <div class="font-semibold text-lg" style="color: #eab308">{{ count($pismenaAnalysis['naredbe'] ?? []) }}</div>
                        </div>
                        <div class="card epw-card text-center">
                            <div class="text-[10px] uppercase muted">Uvid u spis</div>
                            <div class="font-semibold text-lg" style="color: #22c55e">{{ count($pismenaAnalysis['zahtjev_uvid_u_spis'] ?? []) }}</div>
                        </div>
                    </div>

                    @if($pismenaAnalysis['timeline_span'] ?? null)
                        <div class="text-xs muted mb-2">
                            Timeline: {{ $pismenaAnalysis['timeline_span']['from'] }} &rarr; {{ $pismenaAnalysis['timeline_span']['to'] }}
                            ({{ $pismenaAnalysis['timeline_span']['days'] }} days)
                        </div>
                    @endif

                    {{-- Zahtjev za uvid u spis details --}}
                    @if(!empty($pismenaAnalysis['zahtjev_uvid_u_spis']))
                        <div class="mb-2">
                            <div class="text-xs font-semibold mb-1" style="color:#22c55e;">Zahtjev za uvid u spis</div>
                            @foreach($pismenaAnalysis['zahtjev_uvid_u_spis'] as $uvid)
                                <div class="px-2 py-1 mb-1 rounded text-xs" style="background:rgba(34,197,94,0.08); border-left:2px solid #22c55e;">
                                    <span class="font-medium">{{ $uvid['datum'] ?? '---' }}</span>
                                    <span class="muted ml-1">{{ $uvid['vrsta'] }}</span>
                                    @if($uvid['podnositelj'])
                                        <span class="ml-1" style="color:#22c55e;">{{ $uvid['podnositelj'] }}</span>
                                    @endif
                                    <span class="epw-badge ml-1" style="background:rgba(34,197,94,0.15); color:#22c55e;">{{ $uvid['tip'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Document types breakdown --}}
                    @if(!empty($pismenaAnalysis['by_kind']))
                        <div class="mb-2">
                            <div class="text-xs font-semibold muted mb-1">Document Types (vrsta)</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($pismenaAnalysis['by_kind'], 0, 8) as $kind => $count)
                                    <span class="epw-badge" style="background:rgba(255,255,255,0.06); color:var(--fg);">
                                        {{ $kind }} <span class="ml-1 muted">{{ $count }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Submitters breakdown --}}
                    @if(!empty($pismenaAnalysis['submitters']))
                        <div>
                            <div class="text-xs font-semibold muted mb-1">Submitters</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($pismenaAnalysis['submitters'], 0, 6) as $submitter => $count)
                                    <span class="epw-badge" style="background:rgba(59,130,246,0.1); color:#60a5fa;">
                                        {{ $submitter }} <span class="ml-1" style="opacity:.7;">{{ $count }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (!empty($data['rocista'] ?? []))
            <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;" dusk="rocista-panel">
                <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Rocista</div>
                <table class="min-w-full epw-table" style="color: var(--fg)" dusk="rocista-table">
                    <thead style="background: var(--bg)">
                    <tr class="text-left" style="color: var(--muted)">
                        <th class="px-2 py-1.5">Vrsta</th>
                        <th class="px-2 py-1.5">St. pocetak</th>
                        <th class="px-2 py-1.5">St. zavrsetak</th>
                        <th class="px-2 py-1.5">Pl. pocetak</th>
                        <th class="px-2 py-1.5">Pl. zavrsetak</th>
                        <th class="px-2 py-1.5">Soba</th>
                        <th class="px-2 py-1.5">Odgoda</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (($data['rocista'] ?? []) as $index => $r)
                        <tr style="border-top:1px solid var(--border)" dusk="rociste-{{ $index }}">
                            <td class="px-2 py-1.5">{{ $r['vrstaRadnje'] ?? '---' }}</td>
                            <td class="px-2 py-1.5">{{ $r['stPocetak'] ?? '---' }}</td>
                            <td class="px-2 py-1.5">{{ $r['stZavrsetak'] ?? '---' }}</td>
                            <td class="px-2 py-1.5">{{ $r['plPocetak'] ?? '---' }}</td>
                            <td class="px-2 py-1.5">{{ $r['plZavrsetak'] ?? '---' }}</td>
                            <td class="px-2 py-1.5">{{ ($r['sobanaziv'] ?? '---') . (($r['sobaoznaka'] ?? null) ? ' (' . $r['sobaoznaka'] . ')' : '') }}</td>
                            <td class="px-2 py-1.5">{{ ($r['odgoda'] ?? false) ? 'da' : 'ne' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif


        <div class="grid gap-2 md:grid-cols-2">
            @if (!empty($data['povezaniPredmeti'] ?? []))
                <div class="card" style="border-radius:1rem; padding:0;" dusk="povezani-predmeti-panel">
                    <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Povezani predmeti</div>
                    <ul class="epw-text" style="border-top:0;">
                        @foreach (($data['povezaniPredmeti'] ?? []) as $index => $pp)
                            <li class="px-3 py-2" style="border-top:1px solid var(--border)" dusk="povezani-predmet-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $pp['vezaniOznakaBroj'] ?? ($pp['vezaniOznaka'] ?? '---') }}</div>
                                <div class="muted">{{ $pp['opis'] ?? '---' }}</div>
                                <div class="text-xs muted">{{ $pp['datumVeze'] ?? '---' }} - {{ $pp['tip'] ?? '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!empty($data['vjecnici'] ?? []))
                <div class="card" style="border-radius:1rem; padding:0;" dusk="vjecnici-panel">
                    <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Vijecnici</div>
                    <ul class="epw-text">
                        @foreach (($data['vjecnici'] ?? []) as $index => $v)
                            <li class="px-3 py-2" style="display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border)" dusk="vjecnik-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $v['ime'] ?? '---' }}</div>
                                <div class="muted">{{ $v['vrsta'] ?? '---' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if (!empty($data['stranke'] ?? []))
            <div x-data="{ open: true }" class="card" style="border-radius:1rem; padding:0;" dusk="stranke-panel">
                <button type="button"
                        class="w-full px-3 py-2 font-semibold"
                        style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;"
                        @click="open = !open"
                        aria-controls="stranke-panel"
                        :aria-expanded="open.toString()"
                        dusk="stranke-toggle">
                    <span>Stranke</span>
                    <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div id="stranke-panel" x-show="open" x-transition x-cloak dusk="stranke-content">
                    <ul class="epw-text">
                        @foreach (($data['stranke'] ?? []) as $index => $s)
                            <li class="px-3 py-2" style="display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border)" dusk="stranka-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $s['naziv'] ?? '---' }}</div>
                                <div class="muted">{{ $s['nazivuloge'] ?? '---' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (!empty($data['pismena'] ?? []))
            <div x-data="{ open: true }" class="card" style="border-radius:1rem; padding:0;" dusk="pismena-panel">
                <button type="button"
                        class="w-full px-3 py-2 font-semibold"
                        style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;"
                        @click="open = !open"
                        aria-controls="pismena-panel"
                        :aria-expanded="open.toString()"
                        dusk="pismena-toggle">
                    <span>Pismena <span class="text-xs font-normal muted">({{ count($data['pismena']) }})</span></span>
                    <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div id="pismena-panel" x-show="open" x-transition x-cloak class="overflow-x-auto" dusk="pismena-content">
                    <table class="min-w-full epw-table" style="color: var(--fg)" dusk="pismena-table">
                        <thead style="background: var(--bg)">
                        <tr class="text-left" style="color: var(--muted)">
                            <th class="px-2 py-1.5">Datum</th>
                            <th class="px-2 py-1.5">Vrsta</th>
                            <th class="px-2 py-1.5">Tip</th>
                            <th class="px-2 py-1.5">Podnositelj</th>
                            <th class="px-2 py-1.5">Prilozi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach (($data['pismena'] ?? []) as $index => $p)
                            @php
                                $vrsta = $p['vrsta'] ?? '';
                                $isUvidUSpis = preg_match('/uvid\s+u\s+spis/i', $vrsta);
                                $isZahtjev = preg_match('/zahtjev/i', $vrsta);
                                $isNaredba = preg_match('/naredba|nalog/i', $vrsta);
                                $isSoko = str_contains(strtoupper($p['podnositelj'] ?? ''), 'S.O.K.O');
                            @endphp
                            <tr style="border-top:1px solid var(--border)"
                                class="{{ $isUvidUSpis ? 'epw-row-highlight' : '' }}"
                                dusk="pismeno-{{ $index }}">
                                <td class="px-2 py-1.5">{{ $p['datum'] ?? '---' }}</td>
                                <td class="px-2 py-1.5">
                                    {{ $vrsta ?: '---' }}
                                    @if($isUvidUSpis)
                                        <span class="epw-badge ml-1" style="background:rgba(34,197,94,0.15); color:#22c55e;">UVID</span>
                                    @elseif($isNaredba)
                                        <span class="epw-badge ml-1" style="background:rgba(234,179,8,0.15); color:#eab308;">NAREDBA</span>
                                    @elseif($isZahtjev)
                                        <span class="epw-badge ml-1" style="background:rgba(59,130,246,0.15); color:#3b82f6;">ZAHTJEV</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5">{{ $p['tip'] ?? '---' }}</td>
                                <td class="px-2 py-1.5">
                                    @if($isSoko)
                                        <span style="color:#ef4444; font-weight:600;">{{ $p['podnositelj'] ?? '---' }}</span>
                                        <span class="epw-badge ml-1" style="background:rgba(239,68,68,0.15); color:#ef4444;">SOKO</span>
                                    @else
                                        {{ $p['podnositelj'] ?? '---' }}
                                    @endif
                                </td>
                                <td class="px-2 py-1.5">{{ $p['prilozi'] ?? '---' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Per-Case Defense Argument Card --}}
        @php
            $caseNumber = $data['oznakaBroj'] ?? $data['case_number'] ?? $oznakaBroj ?? null;
            $dbCase = $caseNumber ? \App\Models\CourtCase::where('case_number', 'LIKE', '%' . $caseNumber . '%')->first() : null;
        @endphp
        @if($dbCase)
            <div class="mt-3">
                <button type="button"
                        wire:click="generateCaseArguments({{ $dbCase->id }})"
                        class="btn-secondary epw-btn"
                        wire:loading.attr="disabled" wire:target="generateCaseArguments"
                        dusk="generate-arguments">
                    <span wire:loading wire:target="generateCaseArguments" class="epw-spinner" style="width:.85rem; height:.85rem;"></span>
                    <svg wire:loading.remove wire:target="generateCaseArguments" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Generate Defense Arguments
                </button>
            </div>
        @elseif(!empty($data))
            <div class="mt-3 text-xs muted">
                <em>Sync this case to database to generate defense arguments.</em>
            </div>
        @endif

        @if(!empty($caseArguments['arguments'] ?? []))
            <div class="mt-3 card" style="border-radius:1rem; border:1px solid rgba(139,92,246,0.3);">
                <div class="px-3 py-2" style="border-bottom:1px solid var(--border);">
                    <div class="flex items-center gap-2 flex-wrap">
                        <svg class="h-5 w-5" style="color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span class="font-semibold" style="color:#8b5cf6;">Defense Arguments</span>
                        <span class="text-xs muted">{{ $caseArguments['case_number'] }} — {{ $caseArguments['court'] }} — Judge {{ $caseArguments['judge'] }}</span>
                        <span class="ml-auto epw-badge" style="background:{{ $caseArguments['overall_strength'] >= 75 ? 'rgba(34,197,94,0.15)' : ($caseArguments['overall_strength'] >= 50 ? 'rgba(234,179,8,0.15)' : 'rgba(107,114,128,0.15)') }}; color:{{ $caseArguments['overall_strength'] >= 75 ? '#22c55e' : ($caseArguments['overall_strength'] >= 50 ? '#eab308' : '#6b7280') }};">
                            Strength: {{ $caseArguments['overall_strength'] }}/100
                        </span>
                    </div>
                </div>
                <div class="px-3 py-2">
                    @foreach($caseArguments['arguments'] as $arg)
                        <div class="flex items-start gap-2 py-1.5" style="border-bottom:1px solid rgba(255,255,255,0.04);">
                            <div class="epw-progress mt-1" style="width:40px; height:5px; flex-shrink:0;">
                                <div class="epw-progress-bar" style="width:{{ $arg['strength'] }}%; background:{{ $arg['strength'] >= 75 ? '#22c55e' : ($arg['strength'] >= 50 ? '#eab308' : '#6b7280') }};"></div>
                            </div>
                            <span class="text-xs font-medium" style="min-width:20px; color:{{ $arg['strength'] >= 75 ? '#22c55e' : ($arg['strength'] >= 50 ? '#eab308' : '#6b7280') }}">{{ $arg['strength'] }}</span>
                            <div>
                                <div class="text-xs font-semibold" style="color: var(--fg)">
                                    {{ $arg['title'] }}
                                    <span class="epw-badge ml-1" style="background:rgba(139,92,246,0.1); color:#8b5cf6; font-size:9px;">{{ $arg['article'] }}</span>
                                </div>
                                <div class="text-[10px] muted">{{ $arg['text'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
