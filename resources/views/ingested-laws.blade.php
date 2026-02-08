<x-app-layout>
    @section('title', 'Ingested Laws Manager')

    <style>
        .wrap{ margin:24px auto; padding:0 16px 40px; max-width: 1400px; }
        .card{ background:var(--card, #111827); border:1px solid var(--border, #1f2937); border-radius:14px; padding:20px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }

        .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:16px 0; }
        .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .ctrl label{ font-size:12px; color:var(--muted, #9ca3af); font-weight:500; }
        .in{ background:var(--bg, #0b1220); color:var(--fg, #e5e7eb); border:1px solid var(--border, #1f2937); border-radius:10px; padding:9px 12px; min-width:200px; font-size:14px; }
        .in:focus{ outline:none; border-color:var(--accent, #22d3ee); box-shadow:0 0 0 3px rgba(34,211,238,0.1); }
        .in.small{ min-width:160px; }
        .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border, #1f2937);
              color:var(--fg, #e5e7eb); padding:9px 14px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
              transition:transform .06s ease, filter .15s ease; white-space:nowrap; }
        .btn:hover{ filter:brightness(1.15); }
        .btn:active{ transform:translateY(1px); }
        .btn:disabled{ opacity:0.5; cursor:not-allowed; }
        .btn.success{ background:linear-gradient(180deg,var(--success, #22c55e),#16a34a); border-color:#15803d; }
        .btn.info{ background:linear-gradient(180deg,var(--info, #0ea5e9),#0284c7); border-color:#0369a1; }
        .btn.warn{ background:linear-gradient(180deg,var(--warn, #eab308),#ca8a04); border-color:#a16207; }
        .btn.error{ background:linear-gradient(180deg,var(--error, #ef4444),#dc2626); border-color:#b91c1c; }
        .btn.primary{ background:linear-gradient(180deg,#3b82f6,#2563eb); border-color:#1d4ed8; }

        .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:999px;
               background:var(--chip, #334155); color:#d1d5db; border:1px solid var(--border, #1f2937); font-size:12px; font-weight:500; }
        .chip.success{ background:rgba(34,197,94,0.15); border-color:rgba(34,197,94,0.3); color:#86efac; }
        .chip.info{ background:rgba(14,165,233,0.15); border-color:rgba(14,165,233,0.3); color:#7dd3fc; }
        .chip.warn{ background:rgba(234,179,8,0.15); border-color:rgba(234,179,8,0.3); color:#fde047; }
        .chip.error{ background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); color:#fca5a5; }
        .chip.primary{ background:rgba(59,130,246,0.15); border-color:rgba(59,130,246,0.3); color:#93c5fd; }

        ul.seg-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px; }
        .seg{ background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937); border-radius:12px; padding:14px 16px;
              transition:border-color .2s ease, background .2s ease; }
        .seg:hover{ border-color:#2d3748; background:#0d1421; }
        .seg.clickable{ cursor:pointer; }
        .seg.active{ border-color:var(--accent, #22d3ee); background:#0d1421; }
        .seg .head{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .seg .txt{ line-height:1.6; color:#e2e8f0; font-size:14px; margin-top:8px; }
        .seg .label{ font-size:11px; color:var(--muted, #9ca3af); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
        .seg .value{ font-size:14px; color:var(--fg, #e5e7eb); font-weight:500; }

        details{ margin-top:16px; }
        details summary{ cursor:pointer; font-weight:600; color:#e2e8f0; padding:12px 16px;
                        background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937); border-radius:10px;
                        transition:background .2s ease; }
        details summary:hover{ background:#131b2e; }
        details[open] summary{ border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom-color:transparent; }
        details .detail-content{ background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937); border-top:none;
                                 border-radius:0 0 10px 10px; padding:16px; }

        .tabs{ display:flex; gap:2px; border-bottom:1px solid var(--border, #1f2937); margin-bottom:16px; }
        .tab{ padding:10px 16px; font-size:14px; font-weight:500; cursor:pointer; color:var(--muted, #9ca3af);
              border-bottom:2px solid transparent; transition:color .2s ease, border-color .2s ease; }
        .tab:hover{ color:var(--fg, #e5e7eb); }
        .tab.active{ color:var(--accent, #22d3ee); border-bottom-color:var(--accent, #22d3ee); }

        .modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,0.85); display:flex; align-items:center;
                        justify-content:center; z-index:50; padding:20px; backdrop-filter:blur(4px); }
        .modal{ background:var(--card, #111827); border:1px solid var(--border, #1f2937); border-radius:14px;
                max-width:700px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
        .modal-header{ display:flex; align-items:center; justify-content:space-between; padding:20px 24px;
                      border-bottom:1px solid var(--border, #1f2937); }
        .modal-header h2{ font-size:18px; font-weight:600; margin:0; }
        .modal-body{ padding:24px; }
        .modal-section{ margin-bottom:20px; }
        .modal-section:last-child{ margin-bottom:0; }
        .modal-label{ font-weight:600; color:#cbd5e1; margin-bottom:8px; font-size:13px; }
        .modal-value{ color:#e2e8f0; font-size:14px; line-height:1.6; }
        .modal-value.mono{ font-family:monospace; font-size:12px; word-break:break-all; }

        .table-container{ overflow-x:auto; border:1px solid var(--border, #1f2937); border-radius:10px; background:var(--bg, #0b1220); }
        table{ width:100%; border-collapse:collapse; font-size:13px; }
        thead{ background:#0d1421; }
        thead th{ padding:10px 12px; text-align:left; color:var(--muted, #9ca3af); font-weight:600;
                  text-transform:uppercase; font-size:11px; letter-spacing:0.5px; border-bottom:1px solid var(--border, #1f2937); }
        tbody tr{ border-bottom:1px solid rgba(31,41,55,0.5); transition:background .15s ease; }
        tbody tr:hover{ background:#0d1421; }
        tbody tr:last-child{ border-bottom:none; }
        tbody td{ padding:10px 12px; color:var(--fg, #e5e7eb); vertical-align:top; }
        tbody td.actions{ text-align:right; white-space:nowrap; }

        .form-grid{ display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; }
        .form-group{ display:flex; flex-direction:column; gap:6px; }
        .form-group.span-2{ grid-column:span 2; }
        .form-group label{ font-size:12px; color:var(--muted, #9ca3af); font-weight:500; }
        .form-group input, .form-group textarea, .form-group select{
            background:var(--bg, #0b1220); color:var(--fg, #e5e7eb); border:1px solid var(--border, #1f2937); border-radius:8px;
            padding:9px 12px; font-size:14px; font-family:inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus{
            outline:none; border-color:var(--accent, #22d3ee); box-shadow:0 0 0 3px rgba(34,211,238,0.1);
        }
        .form-group textarea{ resize:vertical; min-height:80px; }
        .form-actions{ display:flex; gap:10px; justify-content:flex-end; padding-top:16px;
                      border-top:1px solid var(--border, #1f2937); margin-top:16px; }
        .error-text{ color:var(--error, #ef4444); font-size:12px; margin-top:4px; }

        .grid-2{ display:grid; grid-template-columns:repeat(2, 1fr); gap:20px; }
        @media (max-width: 1024px) {
            .grid-2{ grid-template-columns:1fr; }
        }

        .pagination{ display:flex; gap:8px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
        .pagination a, .pagination span{ padding:8px 12px; border-radius:8px; font-size:13px; font-weight:500; }
        .pagination a{ background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937); color:var(--fg, #e5e7eb); text-decoration:none; }
        .pagination a:hover{ background:#131b2e; border-color:#2d3748; }
        .pagination span{ background:var(--accent, #22d3ee); border:1px solid var(--accent, #22d3ee); color:#0f172a; }

        @keyframes pulse{ 0%, 100%{ opacity:1; } 50%{ opacity:0.5; } }
        [wire\:loading]{ animation:pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
@auth
<div style="position: fixed; top: 12px; right: 16px; z-index: 45;">
    @livewire('notification-panel')
</div>
@endauth
<div class="wrap">
    <div class="card">
        <x-breadcrumbs :items="breadcrumbs('ingested-laws.index')" />
        <livewire:ingested-laws-manager />
    </div>
</x-app-layout>
