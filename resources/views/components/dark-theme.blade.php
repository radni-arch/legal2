<style>
    /* Unified dark theme variables */
    :root {
        --bg: #0b1220;
        --surface: #0f172a;
        --card: #111827;
        --border: #1f2937;
        --fg: #e5e7eb;
        --muted: #94a3b8;
        --accent: #38bdf8;
        --accent-hover: #0ea5e9;
        --success: #22c55e;
        --warn: #eab308;
        --info: #0ea5e9;
        --chip: #334155;
    }

    body.dark-theme { background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); }

    /* Cards */
    .card { background: var(--card, #111827) !important; border: 1px solid var(--border, #1f2937) !important; color: var(--fg, #e5e7eb) !important; border-radius: 1rem; transition: transform .15s, box-shadow .15s; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(0,0,0,.25); }

    /* Badges (neutral + variants) */
    .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: var(--chip, #334155); color: #d1d5db; border: 1px solid var(--border, #1f2937); white-space: nowrap; }
    .badge-info { background: rgba(14,165,233,0.15); color: #7dd3fc; border-color: rgba(14,165,233,0.35); }
    .badge-success { background: rgba(34,197,94,0.15); color: #86efac; border-color: rgba(34,197,94,0.35); }
    .badge-warn { background: rgba(234,179,8,0.15); color: #fde047; border-color: rgba(234,179,8,0.35); }
    .badge-error { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.35); }

    /* Buttons */
    .btn-secondary { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .9rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: .875rem; border:1px solid var(--border, #1f2937); border-radius:.5rem; cursor:pointer; transition:.2s; }
    .btn-secondary:hover { background:#131b2e; border-color:#2d3748; }

    .btn-primary { display:inline-flex; align-items:center; justify-content:center; padding:.6rem 1.1rem; background: linear-gradient(180deg, var(--accent, #38bdf8), var(--accent-hover, #0ea5e9)); color: #fff; font-weight: 600; font-size: .875rem; border-radius:.5rem; border:1px solid #0284c7; cursor:pointer; transition:.2s; }
    .btn-primary:hover { filter:brightness(1.05); box-shadow: 0 6px 16px rgba(56,189,248,.25); }

    /* Inputs */
    .dt-input { width:100%; padding:.55rem .8rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border:1px solid var(--border, #1f2937); border-radius:.6rem; font-size:.9rem; }
    .dt-input::placeholder { color:#64748b; }
    .dt-input:focus { outline:none; border-color: var(--accent, #38bdf8); box-shadow: 0 0 0 3px rgba(56,189,248,.12); }

    /* Compact tweaks for dashboard tiles */
    .tiles-compact { gap: 12px !important; }
    .tiles-compact .card { padding: 14px !important; }
    .tiles-compact .card h3 { font-size: 1rem !important; }
    .tiles-compact .card p { font-size: .8rem !important; color: var(--muted, #94a3b8) !important; }

    /* Header shell */
    .dash-header { background: linear-gradient(180deg, var(--surface, #0f172a), var(--bg, #0b1220)); border-bottom: 1px solid var(--border, #1f2937); }
    .dash-shell { background: rgba(17,24,39,0.65); border: 1px solid var(--border, #1f2937); border-radius: 1rem; }
    .muted { color: var(--muted, #94a3b8) !important; }

    /* Compact UI variant */
    body.ui-compact .dash-shell { padding: 14px; }
    body.ui-compact h1 { font-size: 1.75rem; }
    body.ui-compact h2 { font-size: 1.1rem; }
    body.ui-compact h3 { font-size: 0.95rem; }
    body.ui-compact .tile { padding: 10px; }
    body.ui-compact .badge { font-size: 0.7rem; padding: 2px 6px; }
    body.ui-compact .btn-primary,
    body.ui-compact .btn-secondary { padding: .45rem .75rem; font-size: .8rem; }
    body.ui-compact .dt-input { padding: .45rem .7rem; font-size: .85rem; }
    body.ui-compact .tiles-compact { gap: 10px !important; }
    body.ui-compact .card { border-radius: 0.75rem; }
    body.ui-compact .card .card-body { padding: 1rem; }
    body.ui-compact .muted { color: color-mix(in oklab, var(--muted, #94a3b8) 85%, #94a3b8) !important; }
</style>
