<div dusk="citation-time-series-viewer" class="citation-time-series-viewer min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4 md:p-8">
    {{-- Loading Indicator --}}
    <div wire:loading dusk="loading-indicator" class="ts-loading active">
        <div class="ts-spinner"></div>
    </div>

    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --fg: #e5e7eb;
            --muted: #9ca3af;
            --accent: #22d3ee;
            --border: #1f2937;
            --chip: #334155;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            --success: #22c55e;
            --info: #0ea5e9;
            --warn: #eab308;
            --error: #ef4444;
        }

        .citation-time-series-viewer {
            color: var(--fg);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', 'Roboto', 'Arial', sans-serif;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .ts-header {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(17, 24, 39, 0.8));
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .ts-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            color: var(--fg);
        }

        .ts-subtitle {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 16px;
        }

        .ts-metadata {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .ts-meta-item {
            background: rgba(11, 18, 32, 0.5);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
        }

        .ts-meta-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .ts-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--accent);
        }

        /* Controls */
        .ts-controls {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .ts-control-group {
            margin-bottom: 16px;
        }

        .ts-control-group:last-child {
            margin-bottom: 0;
        }

        .ts-control-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--fg);
            margin-bottom: 10px;
            display: block;
        }

        .ts-control-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .ts-btn {
            background: linear-gradient(180deg, #1f2937, #111827);
            border: 1px solid var(--border);
            color: var(--fg);
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: transform 0.06s ease, filter 0.15s ease, background 0.2s ease;
            white-space: nowrap;
        }

        .ts-btn:hover:not(:disabled) {
            filter: brightness(1.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .ts-btn:active:not(:disabled) {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .ts-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(0.3);
        }

        .ts-btn.active {
            background: linear-gradient(180deg, var(--accent), #0891b2);
            border-color: #0369a1;
            color: #0f172a;
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.2);
            font-weight: 700;
        }

        .ts-btn.success {
            background: linear-gradient(180deg, var(--success), #16a34a);
            border-color: #15803d;
        }

        .ts-btn.export {
            background: linear-gradient(180deg, var(--info), #0284c7);
            border-color: #0369a1;
        }

        .ts-btn.reset {
            background: linear-gradient(180deg, var(--warn), #ca8a04);
            border-color: #a16207;
        }

        /* Filter section */
        .ts-filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: flex-end;
        }

        .ts-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ts-input {
            background: #0b1220;
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            transition: border-color 0.2s ease;
        }

        .ts-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.15);
            transform: translateY(-1px);
        }

        .ts-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #0a0f1a;
        }

        /* Statistics Grid */
        .ts-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .ts-stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .ts-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #0891b2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .ts-stat-card:hover::before {
            opacity: 1;
        }

        .ts-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.4);
            border-color: var(--accent);
        }

        .ts-stat-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .ts-stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }

        .ts-stat-trend {
            font-size: 12px;
            color: var(--success);
            margin-top: 6px;
        }

        .ts-stat-trend.down {
            color: var(--error);
        }

        /* Main Chart Container */
        .ts-chart-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .ts-chart-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--fg);
            margin-bottom: 20px;
        }

        .ts-chart-wrapper {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }

        .ts-chart-canvas {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .ts-line-chart {
            display: none;
        }

        .ts-line-chart.active {
            display: block;
        }

        .ts-bar-chart {
            display: none;
        }

        .ts-bar-chart.active {
            display: block;
        }

        /* Data Table */
        .ts-table-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .ts-table-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--fg);
            margin-bottom: 16px;
        }

        .ts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ts-table thead {
            background: rgba(11, 18, 32, 0.5);
        }

        .ts-table th {
            padding: 12px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        .ts-table td {
            padding: 12px 14px;
            font-size: 14px;
            border-bottom: 1px solid rgba(31, 41, 55, 0.5);
        }

        .ts-table tr:last-child td {
            border-bottom: none;
        }

        .ts-table tr {
            transition: all 0.2s ease;
        }

        .ts-table tr:hover {
            background: rgba(11, 18, 32, 0.3);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(34, 211, 238, 0.1);
        }

        .ts-trend-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .ts-trend-badge.up {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
        }

        .ts-trend-badge.down {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .ts-trend-badge.stable {
            background: rgba(100, 116, 139, 0.15);
            color: #cbd5e1;
        }

        /* Citing Courts */
        .ts-courts-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .ts-courts-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--fg);
            margin-bottom: 16px;
        }

        .ts-courts-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .ts-court-item {
            background: rgba(11, 18, 32, 0.5);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            transition: all 0.2s ease;
        }

        .ts-court-item:hover {
            background: rgba(11, 18, 32, 0.8);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 211, 238, 0.1);
        }

        .ts-court-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--fg);
            margin-bottom: 4px;
        }

        .ts-court-count {
            font-size: 12px;
            color: var(--accent);
        }

        /* Messages */
        .ts-alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            border: 1px solid transparent;
        }

        .ts-alert.success {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .ts-alert.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        /* Loading State */
        .ts-loading {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .ts-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: ts-spin 0.8s linear infinite;
        }

        @keyframes ts-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Pulse animation for loading states */
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(34, 211, 238, 0.4);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(34, 211, 238, 0);
            }
        }

        button[wire\:loading] {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        /* Fade-in animation for content */
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ts-header, .ts-controls, .ts-stat-card, .ts-chart-container, .ts-table-container, .ts-courts-container {
            animation: fade-in 0.4s ease-out;
        }

        /* Badges */
        .ts-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .ts-badge.hierarchy-5 {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .ts-badge.hierarchy-4 {
            background: rgba(234, 179, 8, 0.15);
            color: #fde047;
        }

        .ts-badge.hierarchy-3 {
            background: rgba(34, 211, 238, 0.15);
            color: #7dd3fc;
        }

        .ts-badge.hierarchy-2 {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
        }

        /* Loading overlay improvements */

        /* Smooth page transitions */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Chart loading state */
        .ts-chart-wrapper[wire\:loading] {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Table loading state */
        .ts-table-container[wire\:loading] {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .ts-header {
                padding: 16px;
            }

            .ts-title {
                font-size: 22px;
            }

            .ts-filter-row {
                grid-template-columns: 1fr;
            }

            .ts-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .ts-chart-wrapper {
                height: 300px;
            }

            .ts-courts-list {
                grid-template-columns: 1fr;
            }
        }
    </style>

    {{-- Error Message --}}
    @if ($error)
        <div dusk="error-message" class="ts-alert error">
            ✗ {{ $error }}
        </div>
    @endif

    {{-- Success Message --}}
    @if ($successMessage)
        <div dusk="success-message" class="ts-alert success">
            ✓ {{ $successMessage }}
        </div>
    @endif

    {{-- Unified Header --}}
    <x-page-header
        title="Citation Time Series"
        subtitle="Analyze citation patterns and trends over time"
        route-name="citation.time-series"
    />

    {{-- Header --}}
    @if ($decision)
        <div dusk="ts-header" class="ts-header">
            <div dusk="court-indicator" class="ts-badge hierarchy-{{ $courtHierarchyLevel }}">
                {{ $decision->court ?? 'Unknown' }}
            </div>
            <h1 dusk="ts-title" class="ts-title">Vremenski niz citiranja</h1>
            <p dusk="ts-subtitle" class="ts-subtitle">Citations Over Time - {{ $decision->case_number }}</p>

            <div class="ts-metadata">
                <div dusk="case-number-meta" class="ts-meta-item">
                    <div class="ts-meta-label">Broj slučaja</div>
                    <div class="ts-meta-value">{{ $decision->case_number ?? 'N/A' }}</div>
                </div>
                <div dusk="court-meta" class="ts-meta-item">
                    <div class="ts-meta-label">Sud</div>
                    <div class="ts-meta-value">{{ $decision->court ?? 'Unknown' }}</div>
                </div>
                <div dusk="decision-date-meta" class="ts-meta-item">
                    <div class="ts-meta-label">Datum Odluke</div>
                    <div class="ts-meta-value">{{ $decision->decision_date?->format('d. m. Y.') ?? 'N/A' }}</div>
                </div>
                <div dusk="finality-meta" class="ts-meta-item">
                    <div class="ts-meta-label">Finalnost</div>
                    <div class="ts-meta-value">{{ $decision->finality ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Controls --}}
    <div dusk="controls-card" class="ts-controls">
        {{-- Period Selector --}}
        <div dusk="period-selector" class="ts-control-group">
            <label class="ts-control-label">Razdoblje</label>
            <div class="ts-control-buttons">
                @foreach ($availablePeriods as $period => $label)
                    <button
                        dusk="period-{{ $period }}"
                        wire:click="selectPeriod('{{ $period }}')"
                        wire:loading.attr="disabled"
                        wire:target="selectPeriod"
                        class="ts-btn {{ $selectedPeriod === $period ? 'active' : '' }}"
                    >
                        <span wire:loading.remove wire:target="selectPeriod">{{ $label }}</span>
                        <span wire:loading wire:target="selectPeriod" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Chart Type Selector --}}
        <div dusk="chart-type-selector" class="ts-control-group">
            <label class="ts-control-label">Tip Grafa</label>
            <div class="ts-control-buttons">
                @foreach ($chartTypes as $type => $label)
                    <button
                        dusk="chart-type-{{ $type }}"
                        wire:click="selectChartType('{{ $type }}')"
                        wire:loading.attr="disabled"
                        wire:target="selectChartType"
                        class="ts-btn {{ $chartType === $type ? 'active' : '' }}"
                    >
                        <span wire:loading.remove wire:target="selectChartType">{{ $label }}</span>
                        <span wire:loading wire:target="selectChartType" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Date Range Filter --}}
        <div dusk="date-range-filter" class="ts-control-group">
            <label class="ts-control-label">Filtriranje po Datumima</label>
            <div class="ts-filter-row">
                <div class="ts-filter-group">
                    <label for="date-from-input" style="font-size: 12px; color: var(--muted);">Od</label>
                    <input
                        id="date-from-input"
                        dusk="date-from-input"
                        type="date"
                        wire:model.defer="dateFrom"
                        class="ts-input"
                        aria-label="Start date for filtering citation data"
                    >
                </div>
                <div class="ts-filter-group">
                    <label for="date-to-input" style="font-size: 12px; color: var(--muted);">Do</label>
                    <input
                        id="date-to-input"
                        dusk="date-to-input"
                        type="date"
                        wire:model.defer="dateTo"
                        class="ts-input"
                        aria-label="End date for filtering citation data"
                    >
                </div>
                <button
                    dusk="apply-date-filter"
                    wire:click="applyDateFilter"
                    wire:loading.attr="disabled"
                    wire:target="applyDateFilter"
                    class="ts-btn success"
                >
                    <span wire:loading.remove wire:target="applyDateFilter">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Primijeni
                    </span>
                    <span wire:loading wire:target="applyDateFilter" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Filtering...
                    </span>
                </button>
                <button
                    dusk="reset-filters"
                    wire:click="resetFilters"
                    wire:loading.attr="disabled"
                    wire:target="resetFilters"
                    class="ts-btn reset"
                >
                    <span wire:loading.remove wire:target="resetFilters">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Reset
                    </span>
                    <span wire:loading wire:target="resetFilters" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Resetting...
                    </span>
                </button>
            </div>
            @if (count($filteredData) > 0)
                <div dusk="filtered-records-count" style="margin-top: 8px; font-size: 12px; color: var(--muted);">
                    Prikazano {{ count($filteredData) }} od {{ count($timeSeriesData) }} zapisa
                </div>
            @endif
        </div>

        {{-- Export Buttons --}}
        <div dusk="export-buttons" class="ts-control-group">
            <label class="ts-control-label">Izvoz</label>
            <div class="ts-control-buttons">
                <button
                    dusk="export-csv-button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv"
                    class="ts-btn export"
                >
                    <span wire:loading.remove wire:target="exportCsv">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        CSV
                    </span>
                    <span wire:loading wire:target="exportCsv" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Exporting...
                    </span>
                </button>
                <button
                    dusk="export-pdf-button"
                    wire:click="exportPdf"
                    wire:loading.attr="disabled"
                    wire:target="exportPdf"
                    class="ts-btn export"
                >
                    <span wire:loading.remove wire:target="exportPdf">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        PDF
                    </span>
                    <span wire:loading wire:target="exportPdf" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Exporting...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Statistics --}}
    @if (!empty($statistics))
        <div dusk="stats-panel" class="ts-stats-grid">
            <div dusk="stats-total-citations" class="ts-stat-card">
                <div class="ts-stat-label">Ukupno Citiranja</div>
                <div class="ts-stat-value">{{ $statistics['total_citations'] }}</div>
                <div dusk="trend-indicators" class="ts-stat-trend {{ $statistics['growth_trend'] === 'down' ? 'down' : '' }}">
                    @if ($statistics['growth_trend'] === 'up')
                        <span dusk="trend-up-arrow">↗</span> Rast
                    @elseif ($statistics['growth_trend'] === 'down')
                        <span dusk="trend-down-arrow">↘</span> Pad
                    @else
                        <span dusk="trend-stable-arrow">→</span> Stabilno
                    @endif
                </div>
            </div>

            <div dusk="stats-avg-importance" class="ts-stat-card">
                <div class="ts-stat-label">Prosječna Važnost</div>
                <div class="ts-stat-value">{{ number_format($statistics['avg_importance'], 2) }}</div>
            </div>

            <div dusk="stats-total-periods" class="ts-stat-card">
                <div class="ts-stat-label">Razdoblja</div>
                <div class="ts-stat-value">{{ $statistics['total_periods'] }}</div>
            </div>

            <div dusk="stats-latest-period" class="ts-stat-card">
                <div class="ts-stat-label">Posljednje Razdoblje</div>
                <div class="ts-stat-value" style="font-size: 14px;">{{ $statistics['latest_period'] ?? 'N/A' }}</div>
            </div>

            @if ($statistics['max_citations'] > 0)
                <div dusk="stats-max-citations" class="ts-stat-card">
                    <div class="ts-stat-label">Max Citiranja</div>
                    <div class="ts-stat-value">{{ $statistics['max_citations'] }}</div>
                </div>

                <div dusk="stats-min-citations" class="ts-stat-card">
                    <div class="ts-stat-label">Min Citiranja</div>
                    <div class="ts-stat-value">{{ $statistics['min_citations'] }}</div>
                </div>
            @endif
        </div>
    @endif

    {{-- Chart Container --}}
    <div dusk="{{ $chartType === 'bar' ? 'bar-chart-container' : 'line-chart-container' }}" class="ts-chart-container">
        <div class="ts-chart-header">
            Vremenski Niz Citiranja
            @if ($selectedPeriod === 'daily')
                <span dusk="daily-label" style="margin-left: 8px; font-size: 12px; color: var(--accent);">(Dnevno)</span>
            @elseif ($selectedPeriod === 'weekly')
                <span dusk="weekly-label" style="margin-left: 8px; font-size: 12px; color: var(--accent);">(Tjedno)</span>
            @elseif ($selectedPeriod === 'monthly')
                <span dusk="monthly-label" style="margin-left: 8px; font-size: 12px; color: var(--accent);">(Mjesečno)</span>
                <span dusk="month-label" style="display: none;"></span>
            @elseif ($selectedPeriod === 'yearly')
                <span dusk="yearly-label" style="margin-left: 8px; font-size: 12px; color: var(--accent);">(Godišnje)</span>
                <span dusk="year-label" style="display: none;"></span>
            @endif
        </div>
        <div class="ts-chart-wrapper">
            <div dusk="chart-canvas" class="ts-chart-canvas">
                <canvas id="citationChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Period Comparison --}}
    @if (count($filteredData) > 1)
        <div dusk="comparison-chart" class="ts-chart-container" style="margin-bottom: 24px;">
            <div class="ts-chart-header">Usporedba Razdoblja</div>
            <div class="ts-chart-wrapper">
                <div class="ts-chart-canvas">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Comparison Table --}}
        <div dusk="comparison-table" class="ts-table-container" style="margin-bottom: 24px;">
            <div class="ts-table-header">Usporedba Podataka</div>
            <table class="ts-table">
                <thead>
                    <tr>
                        <th>Razdoblje</th>
                        <th>Citiranja</th>
                        <th>Promjena</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice(!empty($filteredData) ? $filteredData : $timeSeriesData, 0, 5) as $record)
                        <tr>
                            <td>{{ $record['period_label'] }}</td>
                            <td>{{ $record['citation_count'] }}</td>
                            <td>{{ $record['trend'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Data Table --}}
    @if (!empty($timeSeriesData))
        <div dusk="data-table" class="ts-table-container">
            <div class="ts-table-header">Tablica Podataka</div>
            <table class="ts-table">
                <thead>
                    <tr>
                        <th dusk="table-header-period">Razdoblje</th>
                        <th dusk="table-header-citations">Broj Citiranja</th>
                        <th dusk="table-header-incoming">Dolazna</th>
                        <th dusk="table-header-outgoing">Odlazna</th>
                        <th dusk="table-header-importance">Važnost</th>
                        <th dusk="table-header-trend">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (!empty($filteredData) ? $filteredData : $timeSeriesData as $record)
                        <tr>
                            <td dusk="table-period-{{ $loop->index }}">{{ $record['period_label'] }}</td>
                            <td dusk="table-citations-{{ $loop->index }}">{{ $record['citation_count'] }}</td>
                            <td dusk="table-incoming-{{ $loop->index }}">{{ $record['incoming_citations'] }}</td>
                            <td dusk="table-outgoing-{{ $loop->index }}">{{ $record['outgoing_citations'] }}</td>
                            <td dusk="table-importance-{{ $loop->index }}">{{ number_format($record['avg_citation_importance'], 2) }}</td>
                            <td dusk="table-trend-{{ $loop->index }}">
                                @php
                                    $trendClass = 'stable';
                                    $trendIcon = '→';
                                    $trendText = 'Stabilno';

                                    if (str_starts_with($record['trend'], 'up')) {
                                        $trendClass = 'up';
                                        $trendIcon = '↗';
                                        $trendText = 'Rast';
                                    } elseif (str_starts_with($record['trend'], 'down')) {
                                        $trendClass = 'down';
                                        $trendIcon = '↘';
                                        $trendText = 'Pad';
                                    } elseif ($record['trend'] === 'new') {
                                        $trendClass = 'up';
                                        $trendIcon = '✨';
                                        $trendText = 'Novo';
                                    }
                                @endphp
                                <span dusk="trend-indicator" class="ts-trend-badge {{ $trendClass }}">
                                    <span dusk="trend-{{ str_replace('_', '-', $record['trend']) }}-arrow">{{ $trendIcon }}</span>
                                    {{ $trendText }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--muted);">
                                Nema dostupnih podataka
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Citing Courts --}}
    @if (!empty($topCitingCourts))
        <div dusk="citing-courts-list" class="ts-courts-container">
            <div class="ts-courts-header">Najčešće Citirajući Sudovi</div>
            <div class="ts-courts-list">
                @foreach ($topCitingCourts as $court => $count)
                    <div dusk="citing-court-item" class="ts-court-item">
                        <div class="ts-court-name">{{ $court }}</div>
                        <div class="ts-court-count">{{ $count }} citiranja</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Court Hierarchy Badge --}}
    @if ($decision)
        <div dusk="court-hierarchy-badge" style="margin-top: 24px; padding: 16px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; text-align: center;">
            <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">Razina Sudske Hijerarhije</div>
            <div style="font-size: 20px; font-weight: 700; color: var(--accent);">
                @php
                    $level = $courtHierarchyLevel;
                    $levelText = match($level) {
                        5 => 'Vrhovni/Ustavni Sud',
                        4 => 'Viši Sud',
                        3 => 'Županijski Sud',
                        2 => 'Općinski Sud',
                        default => 'Ostali Sud'
                    };
                @endphp
                {{ $levelText }}
            </div>
        </div>
    @endif
</div>

{{-- Chart.js from CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartData = @json($chartData);
        const chartType = @json($chartType);

        if (chartData.labels.length === 0) {
            console.log('No chart data available');
            return;
        }

        const ctx = document.getElementById('citationChart');
        if (!ctx) return;

        const config = {
            type: chartType === 'bar' ? 'bar' : 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb',
                            font: { size: 12, weight: '600' },
                            padding: 15,
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#e5e7eb',
                        bodyColor: '#cbd5e1',
                        borderColor: '#1f2937',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    },
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Broj Citiranja',
                            color: '#cbd5e1',
                        },
                        ticks: {
                            color: '#9ca3af',
                        },
                        grid: {
                            color: 'rgba(31, 41, 55, 0.2)',
                        },
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Prosječna Važnost (%)',
                            color: '#cbd5e1',
                        },
                        ticks: {
                            color: '#9ca3af',
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Razdoblje',
                            color: '#cbd5e1',
                        },
                        ticks: {
                            color: '#9ca3af',
                        },
                        grid: {
                            color: 'rgba(31, 41, 55, 0.2)',
                        },
                    },
                },
            },
        };

        const myChart = new Chart(ctx, config);

        // Re-render chart when period or chart type changes
        window.addEventListener('chart-update', function() {
            myChart.destroy();
            const newChart = new Chart(ctx, config);
        });
    });
</script>

{{-- Livewire magic for reactive updates --}}
<script>
    document.addEventListener('livewire:navigated', function() {
        // Trigger chart update after Livewire updates
        window.dispatchEvent(new Event('chart-update'));
    });
</script>
