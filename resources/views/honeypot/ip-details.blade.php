<x-layouts.app title="IP Details - {{ $ip }}">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="dash-header">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold" style="color: var(--fg, #e5e7eb);">IP Details: <span class="font-mono">{{ $ip }}</span></h1>
                        <p class="mt-1" style="color: var(--muted, #94a3b8);">Honeypot activity from this IP address</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($statistics['is_blocked'])
                            <span class="px-3 py-1 rounded text-sm font-medium" style="background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35);">Blocked</span>
                        @else
                            <form method="POST" action="/honeypot/block/{{ $ip }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1 rounded text-sm font-medium transition" style="background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35);">
                                    Block IP
                                </button>
                            </form>
                        @endif
                        <a href="/honeypot" class="btn-secondary">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <!-- Statistics Cards -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="card rounded-lg p-6" style="border-left: 4px solid #ef4444 !important;">
                    <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Total Attempts</p>
                    <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['total_attempts']) }}</p>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #f97316 !important;">
                    <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">First Seen</p>
                    <p class="text-lg font-bold mt-1" style="color: var(--fg, #e5e7eb);">
                        {{ $statistics['first_seen'] ? \Carbon\Carbon::parse($statistics['first_seen'])->format('M d, Y H:i') : 'N/A' }}
                    </p>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #eab308 !important;">
                    <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Last Seen</p>
                    <p class="text-lg font-bold mt-1" style="color: var(--fg, #e5e7eb);">
                        {{ $statistics['last_seen'] ? \Carbon\Carbon::parse($statistics['last_seen'])->format('M d, Y H:i') : 'N/A' }}
                    </p>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid {{ $statistics['is_blocked'] ? '#ef4444' : '#22c55e' }} !important;">
                    <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Status</p>
                    <p class="text-lg font-bold mt-1" style="color: {{ $statistics['is_blocked'] ? '#fca5a5' : '#86efac' }};">
                        {{ $statistics['is_blocked'] ? 'Blocked' : 'Active' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2 mb-8">
                <!-- Paths Attempted -->
                <div class="card rounded-lg">
                    <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                        <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Paths Attempted</h2>
                        <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">Endpoints this IP tried to access</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-2">
                            @forelse($statistics['paths_attempted'] as $path)
                                <div class="p-3 rounded-lg" style="background: var(--surface, #0f172a);">
                                    <code class="text-sm font-mono" style="color: #fca5a5;">{{ $path }}</code>
                                </div>
                            @empty
                                <p class="text-center py-4" style="color: var(--muted, #94a3b8);">No paths recorded</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Methods & User Agents -->
                <div class="space-y-6">
                    <div class="card rounded-lg">
                        <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                            <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">HTTP Methods Used</h2>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-wrap gap-2">
                                @forelse($statistics['methods_used'] as $method)
                                    <span class="px-3 py-1 text-sm font-semibold rounded"
                                        style="@if($method === 'GET') background: rgba(34,197,94,0.15); color: #86efac;
                                        @elseif($method === 'POST') background: rgba(59,130,246,0.15); color: #93c5fd;
                                        @else background: var(--chip, #334155); color: #d1d5db; @endif">
                                        {{ $method }}
                                    </span>
                                @empty
                                    <p style="color: var(--muted, #94a3b8);">No methods recorded</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-lg">
                        <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                            <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">User Agents</h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                @forelse($statistics['user_agents'] as $ua)
                                    <div class="p-3 rounded-lg" style="background: var(--surface, #0f172a);">
                                        <span class="text-sm" style="color: var(--muted, #94a3b8);">{{ $ua ?? 'N/A' }}</span>
                                    </div>
                                @empty
                                    <p class="text-center py-4" style="color: var(--muted, #94a3b8);">No user agents recorded</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="card rounded-lg">
                <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                    <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Activity Log (Last 100)</h2>
                    <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">All recorded honeypot triggers from {{ $ip }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead style="background: var(--surface, #0f172a); border-bottom: 1px solid var(--border, #1f2937);">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Path</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">User Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="transition-colors" onmouseover="this.style.background='var(--surface, #0f172a)'" onmouseout="this.style.background='transparent'">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--fg, #e5e7eb);">
                                        {{ $log->created_at->format('M d, H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded"
                                            style="@if($log->method === 'GET') background: rgba(34,197,94,0.15); color: #86efac;
                                            @elseif($log->method === 'POST') background: rgba(59,130,246,0.15); color: #93c5fd;
                                            @else background: var(--chip, #334155); color: #d1d5db; @endif">
                                            {{ $log->method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <code style="color: #fca5a5;">{{ $log->path }}</code>
                                    </td>
                                    <td class="px-6 py-4 text-sm max-w-xs truncate" style="color: var(--muted, #94a3b8);">
                                        {{ $log->user_agent ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded"
                                            style="@if($log->severity === 'high') background: rgba(239,68,68,0.15); color: #fca5a5;
                                            @elseif($log->severity === 'medium') background: rgba(249,115,22,0.15); color: #fdba74;
                                            @else background: rgba(234,179,8,0.15); color: #fde047; @endif">
                                            {{ $log->severity ?? 'low' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center" style="color: var(--muted, #94a3b8);">
                                        No activity recorded for this IP
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</x-layouts.app>
