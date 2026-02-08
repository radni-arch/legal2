<x-layouts.app title="Honeypot Dashboard">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="dash-header">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold" style="color: var(--fg, #e5e7eb);">Honeypot Security Dashboard</h1>
                        <p class="mt-1" style="color: var(--muted, #94a3b8);">Monitoring unauthorized access attempts</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/dashboard" class="btn-secondary">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <!-- Statistics Cards -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 mb-8">
                <div class="card rounded-lg p-6" style="border-left: 4px solid #ef4444 !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Total Attempts</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['total_attempts']) }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(239,68,68,0.15);">
                            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #f97316 !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Unique IPs</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['unique_ips']) }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(249,115,22,0.15);">
                            <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #eab308 !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Last 24 Hours</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['attempts_last_24h']) }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(234,179,8,0.15);">
                            <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #a855f7 !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Last 7 Days</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['attempts_last_7days']) }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(168,85,247,0.15);">
                            <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #3b82f6 !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Blocked IPs</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($statistics['blocked_ips']) }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(59,130,246,0.15);">
                            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card rounded-lg p-6" style="border-left: 4px solid #22c55e !important;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Avg per IP</p>
                            <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ $statistics['avg_attempts_per_ip'] }}</p>
                        </div>
                        <div class="p-3 rounded-full" style="background: rgba(34,197,94,0.15);">
                            <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2 mb-8">
                <!-- Top Targeted Paths -->
                <div class="card rounded-lg">
                    <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                        <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Most Targeted Endpoints</h2>
                        <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">Paths that attackers are trying to access</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @forelse($topPaths as $pathData)
                                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--surface, #0f172a);">
                                    <div class="flex-1">
                                        <code class="text-sm font-mono" style="color: #fca5a5;">{{ $pathData['path'] }}</code>
                                    </div>
                                    <div class="ml-4">
                                        <span class="badge badge-error">
                                            {{ number_format($pathData['count']) }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center py-4" style="color: var(--muted, #94a3b8);">No attacks detected yet</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Top Attacking IPs -->
                <div class="card rounded-lg">
                    <div class="p-6" style="border-bottom: 1px solid var(--border, #1f2937);">
                        <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Most Active Attackers</h2>
                        <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">IP addresses with most attempts</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @forelse($topIPs as $ipData)
                                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--surface, #0f172a);">
                                    <div class="flex-1">
                                        <a href="/honeypot/ip/{{ $ipData['ip_address'] }}" class="text-sm font-mono hover:underline" style="color: var(--accent, #38bdf8);">
                                            {{ $ipData['ip_address'] }}
                                        </a>
                                    </div>
                                    <div class="ml-4 flex items-center gap-2">
                                        <span class="badge badge-warn">
                                            {{ number_format($ipData['count']) }}
                                        </span>
                                        <form method="POST" action="/honeypot/block/{{ $ipData['ip_address'] }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 rounded text-xs font-medium transition" style="background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35);">
                                                Block
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center py-4" style="color: var(--muted, #94a3b8);">No attacks detected yet</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card rounded-lg">
                <div class="p-6 flex items-center justify-between" style="border-bottom: 1px solid var(--border, #1f2937);">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Recent Activity (Last 24 Hours)</h2>
                        <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">Live honeypot triggers</p>
                    </div>
                    <a href="/honeypot/export?hours=24" class="btn-primary text-sm">
                        Export JSON
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead style="background: var(--surface, #0f172a); border-bottom: 1px solid var(--border, #1f2937);">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Path</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivity as $log)
                                <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="transition-colors" onmouseover="this.style.background='var(--surface, #0f172a)'" onmouseout="this.style.background='transparent'">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--fg, #e5e7eb);">
                                        {{ \Carbon\Carbon::parse($log['created_at'])->format('M d, H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/honeypot/ip/{{ $log['ip_address'] }}" class="text-sm font-mono hover:underline" style="color: var(--accent, #38bdf8);">
                                            {{ $log['ip_address'] }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded"
                                            style="@if($log['method'] === 'GET') background: rgba(34,197,94,0.15); color: #86efac;
                                            @elseif($log['method'] === 'POST') background: rgba(59,130,246,0.15); color: #93c5fd;
                                            @else background: var(--chip, #334155); color: #d1d5db; @endif">
                                            {{ $log['method'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <code style="color: #fca5a5;">{{ $log['path'] }}</code>
                                    </td>
                                    <td class="px-6 py-4 text-sm max-w-xs truncate" style="color: var(--muted, #94a3b8);">
                                        {{ $log['user_agent'] ?? 'N/A' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center" style="color: var(--muted, #94a3b8);">
                                        No recent activity detected
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
