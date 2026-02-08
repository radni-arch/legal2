<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" @pt="parallel-timeline-container">
    <style>
        .pt-controls { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:16px; border-radius:12px; background:var(--surface, #0f172a); border:1px solid var(--border, #1f2937); }
        .pt-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all .15s ease; border:1px solid var(--border, #1f2937); }
        .pt-btn:hover { filter:brightness(1.15); }
        .pt-btn:active { transform:translateY(1px); }
        .pt-btn-default { background:var(--surface, #0f172a); color:var(--fg, #e5e7eb); }
        .pt-btn-active { background:linear-gradient(180deg, var(--accent, #38bdf8), var(--accent-hover, #0ea5e9)); color:#fff; border-color:#0284c7; }
        .pt-btn-success { background:linear-gradient(180deg, var(--success, #22c55e), #16a34a); color:#fff; border-color:#15803d; }
        .pt-btn-muted { background:var(--chip, #334155); color:var(--muted, #94a3b8); }
        .pt-select { background:var(--bg, #0b1220); color:var(--fg, #e5e7eb); border:1px solid var(--border, #1f2937); border-radius:8px; padding:8px 12px; font-size:13px; }
        .pt-select:focus { outline:none; border-color:var(--accent, #38bdf8); box-shadow:0 0 0 3px rgba(56,189,248,0.12); }
        .pt-checkbox { accent-color:var(--accent, #38bdf8); }
        .pt-day { font-size:14px; font-weight:600; min-width:120px; text-align:center; color:var(--muted, #94a3b8); }

        .pt-lane { border-radius:12px; padding:20px; background:var(--card, #111827); border:1px solid var(--border, #1f2937); }
        .pt-lane-top { border-left:4px solid var(--accent, #38bdf8); }
        .pt-lane-bottom { border-left:4px solid #a78bfa; }
        .pt-lane-title { font-size:16px; font-weight:700; margin-bottom:4px; }
        .pt-lane-top .pt-lane-title { color:var(--accent, #38bdf8); }
        .pt-lane-bottom .pt-lane-title { color:#a78bfa; }
        .pt-lane-count { font-size:13px; color:var(--muted, #94a3b8); }
        .pt-lane-divider { border-bottom:1px solid var(--border, #1f2937); margin-bottom:16px; padding-bottom:12px; }

        .event-card { padding:14px; border-radius:10px; border:1px solid var(--border, #1f2937); transition:border-color .2s ease, background .2s ease; }
        .event-card:hover { border-color:#2d3748; }
        .event-filing { background:rgba(59,130,246,0.08); border-color:rgba(59,130,246,0.25); }
        .event-hearing { background:rgba(167,139,250,0.08); border-color:rgba(167,139,250,0.25); }
        .event-action { background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.25); }
        .event-deadline { background:rgba(234,179,8,0.08); border-color:rgba(234,179,8,0.25); }
        .event-decision { background:rgba(34,197,94,0.08); border-color:rgba(34,197,94,0.25); }
        .event-default { background:var(--surface, #0f172a); }

        .priority-high { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; background:rgba(239,68,68,0.15); color:#fca5a5; border:1px solid rgba(239,68,68,0.3); }
        .priority-medium { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3); }
        .priority-low { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.3); }
        .priority-default { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; background:var(--chip, #334155); color:var(--muted, #94a3b8); }

        .pt-status-completed { display:inline-flex; align-items:center; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.3); }
        .pt-status-pending { display:inline-flex; align-items:center; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; background:rgba(234,179,8,0.15); color:#fde047; border:1px solid rgba(234,179,8,0.3); }
        .pt-event-meta { font-size:12px; color:var(--muted, #94a3b8); }
        .pt-event-title { font-size:14px; font-weight:600; color:var(--fg, #e5e7eb); }
        .pt-event-desc { font-size:13px; color:var(--muted, #94a3b8); margin-top:4px; }
        .pt-event-btn { padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid var(--border, #1f2937); transition:all .15s ease; }
        .pt-event-btn:hover { filter:brightness(1.15); }
        .pt-event-btn-blue { background:rgba(59,130,246,0.2); color:#93c5fd; border-color:rgba(59,130,246,0.3); }
        .pt-event-btn-purple { background:rgba(167,139,250,0.2); color:#c4b5fd; border-color:rgba(167,139,250,0.3); }

        .pt-details-panel { padding:20px; border-radius:12px; background:var(--card, #111827); border:2px solid var(--accent, #38bdf8); }
        .pt-filter-panel { padding:16px; border-radius:10px; background:var(--surface, #0f172a); border:1px solid var(--border, #1f2937); margin-top:12px; }
        .pt-filter-label { font-size:12px; font-weight:600; color:var(--muted, #94a3b8); margin-bottom:6px; display:block; }
        .pt-info-bar { padding:12px 16px; border-radius:8px; background:var(--surface, #0f172a); border:1px solid var(--border, #1f2937); font-size:13px; color:var(--muted, #94a3b8); }
        .pt-empty-state { text-align:center; padding:48px 20px; border-radius:12px; background:var(--surface, #0f172a); border:2px dashed var(--border, #1f2937); }
    </style>

    <x-page-header
        title="Parallel Timeline"
        subtitle="Comparative dual-lane event timeline"
        route-name="parallel.timeline"
    >
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155); color: var(--fg, #e5e7eb);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Navigation and Controls Section -->
        <div @pt="controls-section" class="pt-controls mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 w-full">
                <!-- Date Navigation -->
                <div @pt="date-nav-container" class="flex items-center justify-between gap-2">
                    <button @pt="prev-day-btn" wire:click="previousDay" class="pt-btn pt-btn-active" title="Prethodni dan">
                        &larr; Prethodno
                    </button>
                    <span @pt="current-day-display" class="pt-day">{{ $day }}</span>
                    <button @pt="next-day-btn" wire:click="nextDay" class="pt-btn pt-btn-active" title="Sljedeci dan">
                        Sljedece &rarr;
                    </button>
                </div>

                <!-- View Mode Toggle -->
                <div @pt="view-mode-container" class="flex gap-2">
                    <button @pt="view-timeline-btn" wire:click="changeViewMode('timeline')" class="pt-btn {{ $viewMode === 'timeline' ? 'pt-btn-active' : 'pt-btn-default' }}">
                        Vremenski slijed
                    </button>
                    <button @pt="view-list-btn" wire:click="changeViewMode('list')" class="pt-btn {{ $viewMode === 'list' ? 'pt-btn-active' : 'pt-btn-default' }}">
                        Popis
                    </button>
                    <button @pt="view-comparison-btn" wire:click="changeViewMode('comparison')" class="pt-btn {{ $viewMode === 'comparison' ? 'pt-btn-active' : 'pt-btn-default' }}">
                        Usporedba
                    </button>
                </div>

                <!-- Filter Panel Toggle -->
                <div @pt="filter-toggle-container">
                    <button @pt="filter-panel-toggle-btn" wire:click="toggleFilterPanel" class="pt-btn pt-btn-default w-full">
                        @if($showFilterPanel) Filtri @else Prikazi Filtere @endif
                    </button>
                </div>

                <!-- Action Buttons -->
                <div @pt="actions-container" class="flex gap-2">
                    <button @pt="refresh-btn" wire:click="refreshTimeline" class="pt-btn pt-btn-success" title="Osvjezi vremenski slijed">
                        Osvjezi
                    </button>
                    <button @pt="clear-filters-btn" wire:click="clearFilters" class="pt-btn pt-btn-muted" title="Ocisti sve filtere">
                        Ocisti
                    </button>
                </div>
            </div>

            <!-- Filter Panel -->
            @if($showFilterPanel)
            <div @pt="filter-panel" class="pt-filter-panel w-full">
                <h3 @pt="filter-panel-title" class="font-semibold mb-3" style="color: var(--fg, #e5e7eb);">Filteri</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Lane Filter -->
                    <div @pt="lane-filter-group">
                        <label @pt="lane-filter-label" class="pt-filter-label">Linija:</label>
                        <select @pt="lane-filter-select" wire:change="filterByLane($event.target.value)" class="pt-select w-full">
                            <option value="all">Sve linije</option>
                            <option value="top">Gornja linija</option>
                            <option value="bottom">Donja linija</option>
                        </select>
                    </div>

                    <!-- Event Type Filter -->
                    <div @pt="event-type-filter-group">
                        <label @pt="event-type-filter-label" class="pt-filter-label">Tip dogadaja:</label>
                        <select @pt="event-type-filter-select" wire:change="filterByEventType($event.target.value)" class="pt-select w-full">
                            <option value="">Svi tipovi</option>
                            <option value="filing">Podnesak</option>
                            <option value="hearing">Rociste</option>
                            <option value="action">Radnja</option>
                            <option value="deadline">Rok</option>
                            <option value="decision">Odluka</option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div @pt="date-range-filter-group">
                        <label @pt="date-range-filter-label" class="pt-filter-label">Raspon datuma:</label>
                        <select @pt="date-range-filter-select" wire:change="applyDateRange($event.target.value)" class="pt-select w-full">
                            <option value="all">Svi periodi</option>
                            <option value="week">Prethodni tjedan</option>
                            <option value="month">Prethodni mjesec</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <label @pt="auto-update-label" class="flex items-center gap-2 text-sm" style="color: var(--muted, #94a3b8);">
                        <input @pt="auto-update-checkbox" type="checkbox" wire:change="toggleAutoUpdate" {{ $autoUpdate ? 'checked' : '' }} class="w-4 h-4 pt-checkbox" />
                        Automatska osvjezavanja
                    </label>
                </div>
            </div>
            @endif
        </div>

        <!-- Main Timeline Display -->
        <div @pt="timeline-main-container">
            @if(!$filteredTopEvents && !$filteredBottomEvents)
            <!-- Empty State -->
            <div @pt="empty-state" class="pt-empty-state">
                <p @pt="empty-state-message" class="text-lg" style="color: var(--muted, #94a3b8);">Nema dogadaja za prikaz</p>
                <p @pt="empty-state-hint" class="text-sm mt-2" style="color: var(--muted, #64748b);">Promijenite filtere ili odaberite drugaciji vremenski raspon</p>
            </div>
            @else
            <!-- Two-Lane Timeline -->
            <div @pt="two-lane-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Top Lane -->
                <div @pt="top-lane-container" class="pt-lane pt-lane-top">
                    <div @pt="top-lane-header" class="pt-lane-divider">
                        <h2 @pt="top-lane-title" class="pt-lane-title">Gornja Linija</h2>
                        <p @pt="top-lane-count" class="pt-lane-count">Dogadaja: <span @pt="top-lane-count-number">{{ $topLaneCount }}</span></p>
                    </div>

                    @if($filteredTopEvents)
                    <div @pt="top-events-list" class="space-y-3">
                        @foreach($filteredTopEvents as $index => $event)
                        <div @pt="top-event-card-{{ $index }}" class="event-card {{ $this->getEventTypeColor($event['type']) }}" data-event-id="{{ $event['id'] }}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 @pt="top-event-title-{{ $index }}" class="pt-event-title">{{ $event['title'] }}</h3>
                                    <p @pt="top-event-description-{{ $index }}" class="pt-event-desc">{{ $event['description'] }}</p>
                                    <div @pt="top-event-meta-{{ $index }}" class="flex gap-3 mt-2 pt-event-meta">
                                        <span @pt="top-event-date-{{ $index }}">{{ $event['date'] }}</span>
                                        <span @pt="top-event-time-{{ $index }}">{{ $event['time'] }}</span>
                                    </div>
                                </div>
                                <span @pt="top-event-priority-{{ $index }}" class="{{ $this->getPriorityBadgeColor($event['priority']) }}">
                                    {{ ucfirst($event['priority']) }}
                                </span>
                            </div>
                            <div @pt="top-event-buttons-{{ $index }}" class="mt-3 flex gap-2">
                                <button @pt="top-event-details-btn-{{ $index }}" wire:click="toggleEventDetails('{{ $event['id'] }}')" class="pt-event-btn pt-event-btn-blue">
                                    Detalji
                                </button>
                                <span @pt="top-event-status-{{ $index }}" class="{{ $event['status'] === 'completed' ? 'pt-status-completed' : 'pt-status-pending' }}">
                                    {{ ucfirst($event['status']) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p @pt="top-events-empty" class="text-sm" style="color: var(--muted, #94a3b8);">Nema dogadaja u gornjoj liniji</p>
                    @endif
                </div>

                <!-- Bottom Lane -->
                <div @pt="bottom-lane-container" class="pt-lane pt-lane-bottom">
                    <div @pt="bottom-lane-header" class="pt-lane-divider">
                        <h2 @pt="bottom-lane-title" class="pt-lane-title">Donja Linija</h2>
                        <p @pt="bottom-lane-count" class="pt-lane-count">Dogadaja: <span @pt="bottom-lane-count-number">{{ $bottomLaneCount }}</span></p>
                    </div>

                    @if($filteredBottomEvents)
                    <div @pt="bottom-events-list" class="space-y-3">
                        @foreach($filteredBottomEvents as $index => $event)
                        <div @pt="bottom-event-card-{{ $index }}" class="event-card {{ $this->getEventTypeColor($event['type']) }}" data-event-id="{{ $event['id'] }}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 @pt="bottom-event-title-{{ $index }}" class="pt-event-title">{{ $event['title'] }}</h3>
                                    <p @pt="bottom-event-description-{{ $index }}" class="pt-event-desc">{{ $event['description'] }}</p>
                                    <div @pt="bottom-event-meta-{{ $index }}" class="flex gap-3 mt-2 pt-event-meta">
                                        <span @pt="bottom-event-date-{{ $index }}">{{ $event['date'] }}</span>
                                        <span @pt="bottom-event-time-{{ $index }}">{{ $event['time'] }}</span>
                                    </div>
                                </div>
                                <span @pt="bottom-event-priority-{{ $index }}" class="{{ $this->getPriorityBadgeColor($event['priority']) }}">
                                    {{ ucfirst($event['priority']) }}
                                </span>
                            </div>
                            <div @pt="bottom-event-buttons-{{ $index }}" class="mt-3 flex gap-2">
                                <button @pt="bottom-event-details-btn-{{ $index }}" wire:click="toggleEventDetails('{{ $event['id'] }}')" class="pt-event-btn pt-event-btn-purple">
                                    Detalji
                                </button>
                                <span @pt="bottom-event-status-{{ $index }}" class="{{ $event['status'] === 'completed' ? 'pt-status-completed' : 'pt-status-pending' }}">
                                    {{ ucfirst($event['status']) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p @pt="bottom-events-empty" class="text-sm" style="color: var(--muted, #94a3b8);">Nema dogadaja u donjoj liniji</p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Event Details Panel -->
        @if($showEventDetails && !empty($selectedEvent))
        <div @pt="event-details-panel" class="mt-6 pt-details-panel">
            <div class="flex justify-between items-start mb-4">
                <h3 @pt="details-panel-title" class="text-lg font-bold" style="color: var(--fg, #e5e7eb);">Detalji Dogadaja</h3>
                <button @pt="close-details-btn" wire:click="closeEventDetails" class="pt-btn" style="background:rgba(239,68,68,0.15); color:#fca5a5; border-color:rgba(239,68,68,0.3);">
                    Zatvori
                </button>
            </div>

            <div @pt="details-content" class="grid grid-cols-2 gap-4">
                <div>
                    <p @pt="details-id" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">ID:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['id'] ?? 'N/A' }}</span></p>
                    <p @pt="details-title" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Naslov:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['title'] ?? 'N/A' }}</span></p>
                    <p @pt="details-type" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Tip:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['type'] ?? 'N/A' }}</span></p>
                    <p @pt="details-date" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Datum:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['date'] ?? 'N/A' }}</span></p>
                </div>
                <div>
                    <p @pt="details-time" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Vrijeme:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['time'] ?? 'N/A' }}</span></p>
                    <p @pt="details-status" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Status:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['status'] ?? 'N/A' }}</span></p>
                    <p @pt="details-priority" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Prioritet:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['priority'] ?? 'N/A' }}</span></p>
                    <p @pt="details-description" class="text-sm mb-2"><span style="color:var(--muted,#94a3b8); font-weight:600;">Opis:</span> <span style="color:var(--fg,#e5e7eb);">{{ $selectedEvent['description'] ?? 'N/A' }}</span></p>
                </div>
            </div>
        </div>
        @endif

        <!-- Responsive Information -->
        <div @pt="responsive-info" class="mt-6 pt-info-bar">
            <p @pt="responsive-label">Informacija: Komponenta je responzivna. Na mobilnim uredajima linije se prikazuju u odvojenoj vizualizaciji.</p>
        </div>
    </div>
</div>
