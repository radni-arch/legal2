<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unified Search - Legal Documents</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    <style>
        :root{
            --bg:#0b1220; --surface:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#94a3b8; --accent:#38bdf8;
            --accent-hover:#0ea5e9; --border:#1f2937; --chip:#334155; --shadow:0 10px 30px rgba(0,0,0,0.35);
            --success:#22c55e; --info:#0ea5e9; --warn:#eab308; --error:#ef4444;
        }
        html,body{ margin:0; padding:0; background:var(--bg); color:var(--fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .wrap{ margin:0 auto; padding:0 16px 40px; max-width: 1400px; }
        .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px; box-shadow:var(--shadow); }
        h1{ font-size:28px; margin:0 0 8px 0; letter-spacing:0.2px; font-weight:600; }
        .sub{ color:var(--muted); font-size:14px; margin-bottom:16px; }

        .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:16px 0; }
        .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .ctrl label{ font-size:12px; color:var(--muted); font-weight:500; }
        .in{ background:#0b1220; color:var(--fg); border:1px solid var(--border); border-radius:10px; padding:9px 12px; min-width:200px; font-size:14px; }
        .in:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(56,189,248,0.1); }
        .in.small{ min-width:160px; }
        .in:disabled{ opacity:0.5; cursor:not-allowed; }

        .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border);
              color:var(--fg); padding:9px 14px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
              transition:transform .06s ease, filter .15s ease; white-space:nowrap; }
        .btn:hover:not(:disabled){ filter:brightness(1.15); }
        .btn:active:not(:disabled){ transform:translateY(1px); }
        .btn:disabled{ opacity:0.5; cursor:not-allowed; }
        .btn.primary{ background:linear-gradient(180deg,var(--accent),#0ea5e9); border-color:#0891b2; color:#0f172a; }
        .btn.success{ background:linear-gradient(180deg,var(--success),#16a34a); border-color:#15803d; }
        .btn.info{ background:linear-gradient(180deg,var(--info),#0284c7); border-color:#0369a1; }
        .btn.warn{ background:linear-gradient(180deg,var(--warn),#ca8a04); border-color:#a16207; }
        .btn.error{ background:linear-gradient(180deg,var(--error),#dc2626); border-color:#b91c1c; }

        .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:999px;
               background:var(--chip); color:#d1d5db; border:1px solid var(--border); font-size:12px; font-weight:500; cursor:default; }
        .chip.clickable{ cursor:pointer; transition:background .2s ease, border-color .2s ease; }
        .chip.clickable:hover{ background:#3f4a5a; border-color:#475569; }
        .chip.active{ background:rgba(56,189,248,0.2); border-color:rgba(56,189,248,0.4); color:var(--accent); }
        .chip.success{ background:rgba(34,197,94,0.15); border-color:rgba(34,197,94,0.3); color:#86efac; }
        .chip.info{ background:rgba(14,165,233,0.15); border-color:rgba(14,165,233,0.3); color:#7dd3fc; }
        .chip.warn{ background:rgba(234,179,8,0.15); border-color:rgba(234,179,8,0.3); color:#fde047; }
        .chip.error{ background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); color:#fca5a5; }

        ul.seg-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px; }
        .seg{ background:#0b1220; border:1px solid var(--border); border-radius:12px; padding:14px 16px;
              transition:border-color .2s ease; }
        .seg:hover{ border-color:#2d3748; }
        .seg .head{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .seg .txt{ line-height:1.6; color:#e2e8f0; font-size:14px; margin-top:8px; }

        details{ margin-top:16px; }
        details summary{ cursor:pointer; font-weight:600; color:#e2e8f0; padding:12px 16px;
                        background:#0b1220; border:1px solid var(--border); border-radius:10px;
                        transition:background .2s ease; list-style:none; }
        details summary::-webkit-details-marker { display:none; }
        details summary:hover{ background:#131b2e; }
        details[open] summary{ border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom-color:transparent; }
        details .detail-content{ background:#0b1220; border:1px solid var(--border); border-top:none;
                                 border-radius:0 0 10px 10px; padding:16px; }

        .stats{ display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
        .stat{ flex:1; min-width:140px; background:#0b1220; border:1px solid var(--border);
               border-radius:10px; padding:12px 14px; }
        .stat-label{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
        .stat-value{ font-size:24px; font-weight:700; line-height:1; }

        .pagination{ display:flex; gap:8px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
        .pagination button{ padding:8px 12px; border-radius:8px; font-size:13px; font-weight:500; }
        .pagination button:not(.primary){ background:#0b1220; border:1px solid var(--border); color:var(--fg); }
        .pagination button:not(.primary):hover:not(:disabled){ background:#131b2e; border-color:#2d3748; }
        .pagination button.primary{ background:var(--accent); border:1px solid var(--accent); color:#0f172a; }

        .error-banner{ background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:12px;
                      padding:16px; margin-top:20px; }
        .error-title{ color:#fca5a5; font-weight:600; margin-bottom:8px; font-size:15px; }
        .error-message{ color:#fecaca; font-size:14px; line-height:1.6; }

        .empty-state{ text-align:center; padding:60px 20px; }
        .empty-icon{ font-size:64px; margin-bottom:16px; opacity:0.5; }
        .empty-title{ font-size:20px; font-weight:600; color:#e2e8f0; margin:0 0 8px 0; }
        .empty-message{ color:var(--muted); font-size:14px; max-width:600px; margin:0 auto; line-height:1.6; }

        .result-card{ background:#0b1220; border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:12px;
                     transition:all .2s ease; }
        .result-card:hover{ border-color:#2d3748; box-shadow:0 4px 12px rgba(0,0,0,0.2); }
        .result-header{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:16px; }
        .result-title{ font-size:16px; font-weight:600; color:#e2e8f0; margin:0 0 6px 0; flex:1; }
        .result-meta{ font-size:12px; color:var(--muted); }
        .result-score{ background:rgba(59,130,246,0.2); color:#93c5fd; font-weight:600; padding:6px 12px;
                      border-radius:999px; white-space:nowrap; font-size:13px; }
        .result-snippet{ padding:12px; background:rgba(0,0,0,0.2); border-radius:8px; margin-bottom:8px;
                        color:#cbd5e1; font-size:14px; line-height:1.6; }
        .result-badges{ display:flex; gap:8px; flex-wrap:wrap; font-size:11px; }

        .section-title{ font-size:18px; font-weight:600; color:#e2e8f0; margin:20px 0 12px 0;
                       display:flex; align-items:center; gap:8px; }
        .section-title .icon{ font-size:22px; }

        .metadata-info{ background:#0b1220; border:1px solid var(--border); border-radius:10px; padding:12px;
                       margin-top:16px; }
        .metadata-row{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
        .metadata-text{ color:#cbd5e1; font-size:14px; }
        .metadata-muted{ color:var(--muted); font-size:12px; margin-top:4px; }

        .text-muted{ color:var(--muted); }
        .text-xs{ font-size:12px; }
        .text-sm{ font-size:14px; }

        input[type="range"]{ width:100%; height:6px; border-radius:999px; background:#1f2937; outline:none;
                            -webkit-appearance:none; appearance:none; }
        input[type="range"]::-webkit-slider-thumb{ -webkit-appearance:none; appearance:none; width:18px; height:18px;
                                                   border-radius:50%; background:var(--accent); cursor:pointer;
                                                   transition:transform .2s ease; }
        input[type="range"]::-webkit-slider-thumb:hover{ transform:scale(1.1); }
        input[type="range"]::-moz-range-thumb{ width:18px; height:18px; border-radius:50%; background:var(--accent);
                                               cursor:pointer; border:none; transition:transform .2s ease; }
        input[type="range"]::-moz-range-thumb:hover{ transform:scale(1.1); }

        @keyframes pulse{ 0%, 100%{ opacity:1; } 50%{ opacity:0.5; } }
        [wire\:loading]{ animation:pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

        @keyframes fadeIn{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:translateY(0); } }
        .fade-in{ animation:fadeIn 0.3s ease-out; }
    </style>
</head>
<body>
@auth
<div style="position: fixed; top: 12px; right: 16px; z-index: 45;">
    @livewire('notification-panel')
</div>
@endauth
<div class="wrap">
    <div class="card fade-in">
        <livewire:unified-search />
    </div>
</div>
@livewireScripts
</body>
</html>
