<x-app-layout>
    @section('title', 'Transcript Preview')

    <style>
        .wrap{ margin:24px auto; padding:0 16px 40px; max-width: 1100px; }
        .card{ background:var(--card, #111827); border:1px solid var(--border, #1f2937); border-radius:14px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }

        .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:10px 0 12px; }
        .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .ctrl label{ font-size:12px; color:var(--muted, #9ca3af); }
        .in{ background:var(--bg, #0b1220); color:var(--fg, #e5e7eb); border:1px solid var(--border, #1f2937); border-radius:10px; padding:9px 10px; min-width:260px; }
        .in.small{ min-width:220px; }
        .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border, #1f2937);
              color:var(--fg, #e5e7eb); padding:9px 12px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
              transition:transform .06s ease, filter .15s ease; }
        .btn:hover{ filter:brightness(1.1); }
        .btn:active{ transform:translateY(1px); }
        .switch{ display:inline-flex; gap:8px; align-items:center; background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937);
                 border-radius:999px; padding:7px 10px; font-size:13px; }
        .switch input{ accent-color: var(--accent, #22d3ee); }
        .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--chip, #334155); color:#d1d5db; border:1px solid var(--border, #1f2937); font-size:12px; }

        ul.seg-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
        .seg{ background:var(--bg, #0b1220); border:1px solid var(--border, #1f2937); border-radius:12px; padding:10px 12px; }
        .seg .head{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-bottom:6px; }
        .seg .txt{ line-height:1.6; color:#e2e8f0; font-size:14px; }
        mark{ background: color-mix(in oklab, var(--warn, #eab308) 55%, transparent); color:#111827; padding:0 3px; border-radius:4px; }
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
        <x-breadcrumbs :items="breadcrumbs('transcript')" />
        <h1>Pregled transkripta</h1>
        <div class="sub">Elegantni pregled s filtrima i isticanjem. Baza vremena: <strong>2025-06-09 14:45:00</strong> (Europe/Zagreb).</div>
        <livewire:transcript-previewer />
    </div>
</x-app-layout>
