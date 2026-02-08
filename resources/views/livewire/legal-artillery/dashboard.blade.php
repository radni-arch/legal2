<div>
    <style>
        .la-dashboard { min-height: 100vh; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); }
        .la-header { background: linear-gradient(180deg, var(--surface, #0f172a), var(--bg, #0b1220)); border-bottom: 1px solid var(--border, #1f2937); padding: 1.5rem 0; }
        .la-stat-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 1.25rem; transition: transform .15s, box-shadow .15s; }
        .la-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.3); }
        .la-stat-label { font-size: 0.8rem; color: var(--muted, #94a3b8); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
        .la-stat-value { font-size: 2rem; font-weight: 700; margin-top: 0.25rem; }
        .la-run-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; transition: all .15s; }
        .la-run-card:hover { border-color: var(--accent, #38bdf8); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,.2); }
        .la-badge { display: inline-flex; align-items: center; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .la-badge-completed { background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.35); }
        .la-badge-running { background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.35); }
        .la-badge-pending { background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35); }
        .la-badge-failed { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35); }
        .la-filter-select { background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.85rem; }
        .la-filter-select:focus { outline: none; border-color: var(--accent, #38bdf8); box-shadow: 0 0 0 3px rgba(56,189,248,.12); }
        .la-btn-fire { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: linear-gradient(180deg, #ef4444, #dc2626); color: #fff; font-weight: 600; font-size: 0.875rem; border-radius: 0.5rem; border: 1px solid #b91c1c; text-decoration: none; transition: .2s; }
        .la-btn-fire:hover { filter: brightness(1.1); box-shadow: 0 6px 16px rgba(239,68,68,.3); color: #fff; }
        .la-score-bar { height: 4px; background: var(--border, #1f2937); border-radius: 2px; overflow: hidden; width: 60px; }
        .la-score-fill { height: 100%; border-radius: 2px; transition: width .3s; }
    </style>

    <div class="la-dashboard">
        {{-- Header --}}
        <div class="la-header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold" style="color: var(--fg, #e5e7eb);">Pravna Artiljerija</h1>
                        <p style="color: var(--muted, #94a3b8); font-size: 0.875rem;">Rekurzivno generiranje pravnih dopisa s Worker/Critic petljom</p>
                    </div>
                    <a href="{{ route('legal-artillery.new') }}" class="la-btn-fire">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16c3.314 0 6-2.686 6-6 0-3.686-5.088-9.188-5.618-9.773a.5.5 0 0 0-.764 0C7.088.812 2 6.314 2 10c0 3.314 2.686 6 6 6zm.5-9.5a.5.5 0 0 1 1 0v2h2a.5.5 0 0 1 0 1h-2v2a.5.5 0 0 1-1 0v-2h-2a.5.5 0 0 1 0-1h2v-2z"/>
                        </svg>
                        Nova Paljba
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {{-- Stats --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6 mb-6">
                <div class="la-stat-card">
                    <div class="la-stat-label">Ukupno</div>
                    <div class="la-stat-value" style="color: var(--fg, #e5e7eb);">{{ $stats['total'] }}</div>
                </div>
                <div class="la-stat-card">
                    <div class="la-stat-label">Zavrseno</div>
                    <div class="la-stat-value" style="color: var(--success, #22c55e);">{{ $stats['completed'] }}</div>
                </div>
                <div class="la-stat-card">
                    <div class="la-stat-label">Neuspjelo</div>
                    <div class="la-stat-value" style="color: #f87171;">{{ $stats['failed'] }}</div>
                </div>
                <div class="la-stat-card">
                    <div class="la-stat-label">U tijeku</div>
                    <div class="la-stat-value" style="color: var(--warn, #eab308);">{{ $stats['running'] }}</div>
                </div>
                <div class="la-stat-card">
                    <div class="la-stat-label">Prosj. Ocjena</div>
                    <div class="la-stat-value" style="color: var(--accent, #38bdf8);">{{ number_format($stats['avg_score'] ?? 0, 1) }}</div>
                </div>
                <div class="la-stat-card">
                    <div class="la-stat-label">Prosj. Iteracija</div>
                    <div class="la-stat-value" style="color: #a78bfa;">{{ number_format($stats['avg_iterations'] ?? 0, 1) }}</div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex gap-3 mb-6" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 0.75rem 1rem;">
                <select wire:model.live="statusFilter" class="la-filter-select">
                    <option value="">Svi statusi</option>
                    <option value="pending">Na cekanju</option>
                    <option value="running">U tijeku</option>
                    <option value="completed">Zavrseno</option>
                    <option value="failed">Neuspjelo</option>
                </select>
                <select wire:model.live="profileFilter" class="la-filter-select">
                    <option value="">Svi profili</option>
                    @foreach($profiles as $profile)
                        <option value="{{ $profile->key }}">{{ $profile->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Run List --}}
            <div class="space-y-3">
                @forelse($runs as $run)
                    @php
                        $status = strtolower($run->status ?? 'pending');
                        $statusLabel = $status === 'pending' ? 'Na cekanju' : ucfirst($status);
                    @endphp
                    <a href="{{ route('legal-artillery.run', $run->id) }}" class="la-run-card block" style="text-decoration: none;">
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-sm" style="color: var(--accent, #38bdf8);">{{ $run->document_type }}</span>
                                    <span class="la-badge la-badge-{{ $status }}">{{ $statusLabel }}</span>
                                </div>
                                <span class="text-xs" style="color: var(--muted, #94a3b8);">{{ $run->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="flex items-center gap-6 text-sm" style="color: var(--muted, #94a3b8);">
                                <div class="flex items-center gap-2">
                                    <span>Ocjena:</span>
                                    @if($run->final_score)
                                        <span style="color: var(--accent, #38bdf8); font-weight: 600;">{{ number_format((float) $run->final_score, 1) }}</span>
                                        <div class="la-score-bar">
                                            <div class="la-score-fill" style="width: {{ min(100, (float) $run->final_score) }}%; background: var(--accent, #38bdf8);"></div>
                                        </div>
                                    @else
                                        <span>-</span>
                                    @endif
                                </div>
                                <div>
                                    <span>Iteracija:</span>
                                    <span style="font-weight: 500; color: var(--fg, #e5e7eb);">{{ $run->total_iterations ?? '-' }}</span>
                                </div>
                                @if($run->status === 'failed' && $run->error_message)
                                    <div class="mt-2 p-2 rounded text-xs" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;">
                                        {{ Str::limit($run->error_message, 150) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 3rem; text-align: center;">
                        <p style="color: var(--muted, #94a3b8); font-size: 0.9rem;">Nema generacija. Zapocni novu paljbu!</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $runs->links() }}
            </div>
        </div>
    </div>
</div>
