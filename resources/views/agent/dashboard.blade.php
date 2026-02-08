<!-- resources/views/agent/dashboard.blade.php -->
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autonomous Agent Dashboard</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full dark-theme ui-compact" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
@include('components.dark-theme')
<style>
    .badge { padding: 2px 8px; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
    .badge-running { background: rgba(59,130,246,0.15); color: #93c5fd; }
    .badge-completed { background: rgba(34,197,94,0.15); color: #86efac; }
    .badge-failed { background: rgba(239,68,68,0.15); color: #fca5a5; }
</style>

<x-page-header
    title="Autonomous Agent Dashboard"
    subtitle="Monitor and manage autonomous legal research runs"
    route-name="agent.dashboard"
    :show-nav="true"
/>

<main class="max-w-7xl mx-auto px-4 py-8">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm" style="color: var(--muted, #94a3b8);">Total Runs</p>
                    <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ $stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(168,85,247,0.15);">
                    <svg class="w-6 h-6 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm" style="color: var(--muted, #94a3b8);">Running</p>
                    <p class="text-3xl font-bold mt-1" style="color: var(--accent, #38bdf8);">{{ $stats['running'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(56,189,248,0.15);">
                    <svg class="w-6 h-6 text-sky-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm" style="color: var(--muted, #94a3b8);">Completed</p>
                    <p class="text-3xl font-bold mt-1" style="color: var(--success, #22c55e);">{{ $stats['completed'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(34,197,94,0.15);">
                    <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm" style="color: var(--muted, #94a3b8);">Avg Score</p>
                    <p class="text-3xl font-bold mt-1" style="color: var(--fg, #e5e7eb);">{{ number_format($stats['avg_score'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(129,140,248,0.15);">
                    <svg class="w-6 h-6 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Runs Table -->
    <div class="card rounded-xl overflow-hidden">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--border, #1f2937); background: var(--surface, #0f172a);">
            <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Recent Research Runs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" style="border-collapse: separate;">
                <thead style="background: var(--surface, #0f172a);">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Objective</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Iterations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Started</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">Actions</th>
                    </tr>
                </thead>
                <tbody style="color: var(--fg, #e5e7eb);">
                    @forelse($runs as $run)
                    <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="transition-colors" onmouseover="this.style.background='var(--surface, #0f172a)'" onmouseout="this.style.background='transparent'">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            #{{ $run->id }}
                        </td>
                        <td class="px-6 py-4 text-sm max-w-md truncate" style="color: var(--muted, #94a3b8);">
                            {{ Str::limit($run->objective, 80) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="badge badge-{{ $run->status }}">
                                {{ ucfirst($run->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($run->score)
                                <span class="font-semibold" style="color: {{ $run->score >= 0.75 ? 'var(--success, #22c55e)' : 'var(--warn, #eab308)' }};">
                                    {{ number_format($run->score, 2) }}
                                </span>
                            @else
                                <span style="color: var(--muted, #94a3b8);">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);">
                            {{ $run->current_iteration }} / {{ $run->max_iterations }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);">
                            @if($run->elapsed_seconds)
                                {{ gmdate('i:s', $run->elapsed_seconds) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);">
                            {{ $run->started_at?->format('M d, H:i') ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('agent.run', $run->id) }}" style="color: var(--accent, #38bdf8);" class="font-medium hover:underline">
                                View Details →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center" style="color: var(--muted, #94a3b8);">
                            <svg class="mx-auto h-12 w-12" style="color: var(--muted, #94a3b8); opacity: .5;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="mt-2">No research runs found</p>
                            <p class="text-sm mt-1">Start a new research run via the API</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Info -->
    <div class="mt-8 card rounded-xl p-6" style="border-color: rgba(56,189,248,0.2) !important;">
        <h3 class="text-lg font-semibold mb-3" style="color: var(--accent, #38bdf8);">API Endpoints</h3>
        <div class="space-y-2 text-sm" style="color: var(--muted, #94a3b8);">
            <div><code style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--accent, #38bdf8);" class="px-2 py-1 rounded">POST /api/agent/research/start</code> - Start a new research run</div>
            <div><code style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--accent, #38bdf8);" class="px-2 py-1 rounded">GET /api/agent/research</code> - List all research runs</div>
            <div><code style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--accent, #38bdf8);" class="px-2 py-1 rounded">GET /api/agent/research/{id}</code> - Get run details</div>
            <div><code style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937); color: var(--accent, #38bdf8);" class="px-2 py-1 rounded">GET /api/agent/research/{id}/evaluation</code> - Get evaluation report</div>
        </div>
    </div>
</main>
@livewireScripts
</body>
</html>
