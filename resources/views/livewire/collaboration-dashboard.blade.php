<div class="collaboration-dashboard min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="collaboration-dashboard">
    <x-page-header
        title="Collaborations"
        subtitle="Multi-agent collaboration overview and performance tracking"
        route-name="collaborations.dashboard"
    >
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155); color: var(--fg, #e5e7eb);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" dusk="stats-cards">
        <div class="card rounded-lg p-6" dusk="stat-total">
            <h3 class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Total Collaborations</h3>
            <p class="text-3xl font-bold mt-2" style="color: var(--fg, #e5e7eb);" dusk="total-count">{{ $stats['total'] }}</p>
        </div>

        <div class="card rounded-lg p-6" dusk="stat-completed">
            <h3 class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Completed</h3>
            <p class="text-3xl font-bold mt-2" style="color: var(--success, #22c55e);" dusk="completed-count">{{ $stats['completed'] }}</p>
            <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);" dusk="status-details">
                {{ $stats['in_progress'] }} in progress, {{ $stats['failed'] }} failed
            </p>
        </div>

        <div class="card rounded-lg p-6" dusk="stat-duration">
            <h3 class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Avg Duration</h3>
            <p class="text-3xl font-bold mt-2" style="color: var(--accent, #38bdf8);" dusk="avg-duration">
                {{ $stats['avg_duration'] ? round($stats['avg_duration']) : 0 }}s
            </p>
        </div>

        <div class="card rounded-lg p-6" dusk="stat-cost">
            <h3 class="text-sm font-medium" style="color: var(--muted, #94a3b8);">Total Cost</h3>
            <p class="text-3xl font-bold mt-2" style="color: #c084fc;" dusk="total-cost">
                ${{ number_format($stats['total_cost'], 2) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);" dusk="total-tokens">
                {{ number_format($stats['total_tokens']) }} tokens
            </p>
        </div>
    </div>

    {{-- Collaborations Table --}}
    <div class="card rounded-lg" dusk="collaborations-table">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--border, #1f2937);">
            <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);" dusk="table-heading">Recent Collaborations</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full" dusk="table">
                <thead style="background: var(--surface, #0f172a);">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Problem
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Agents
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Duration
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Cost
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Started
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--muted, #94a3b8);">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody dusk="table-body">
                    @forelse($collaborations as $collaboration)
                        <tr style="border-bottom: 1px solid var(--border, #1f2937);" class="transition-colors hover:bg-slate-800/50" dusk="collaboration-row-{{ $loop->index }}">
                            <td class="px-6 py-4">
                                <div class="text-sm max-w-md truncate" style="color: var(--fg, #e5e7eb);" title="{{ $collaboration->problem_statement }}" dusk="problem-{{ $loop->index }}">
                                    {{ Str::limit($collaboration->problem_statement, 80) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="badge badge-info" dusk="type-{{ $loop->index }}">
                                    {{ $collaboration->problem_type ?? 'general' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($collaboration->status === 'completed')
                                    <span class="badge badge-success" dusk="status-{{ $loop->index }}">
                                        Completed
                                    </span>
                                @elseif($collaboration->status === 'in_progress')
                                    <span class="badge badge-warn" dusk="status-{{ $loop->index }}">
                                        In Progress
                                    </span>
                                @else
                                    <span class="badge badge-error" dusk="status-{{ $loop->index }}">
                                        Failed
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm" style="color: var(--fg, #e5e7eb);" dusk="agents-{{ $loop->index }}">
                                    {{ $collaboration->executions->count() }} agents
                                </div>
                                <div class="text-xs" style="color: var(--muted, #94a3b8);" dusk="steps-{{ $loop->index }}">
                                    {{ $collaboration->completed_steps }}/{{ $collaboration->total_steps }} steps
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);" dusk="duration-{{ $loop->index }}">
                                {{ $collaboration->duration_seconds ? $collaboration->duration_seconds . 's' : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);" dusk="cost-{{ $loop->index }}">
                                ${{ number_format($collaboration->cost_spent, 4) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--muted, #94a3b8);" dusk="started-{{ $loop->index }}">
                                {{ $collaboration->started_at ? $collaboration->started_at->format('M d, H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button
                                    wire:click="viewDetails('{{ $collaboration->id }}')"
                                    style="color: var(--accent, #38bdf8);"
                                    class="hover:underline"
                                    dusk="view-details-{{ $loop->index }}"
                                >
                                    View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center" style="color: var(--muted, #94a3b8);" dusk="empty-state">
                                No collaborations found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4" style="border-top: 1px solid var(--border, #1f2937);">
            {{ $collaborations->links() }}
        </div>
    </div>

    {{-- Details Modal --}}
    @if($showDetails && $selectedCollaboration)
        <div class="fixed inset-0 flex items-center justify-center z-50" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" dusk="details-modal">
            <div class="card rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto" dusk="modal-content">
                <div class="px-6 py-4 flex justify-between items-center" style="border-bottom: 1px solid var(--border, #1f2937);" dusk="modal-header">
                    <h3 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);" dusk="modal-title">Collaboration Details</h3>
                    <button wire:click="closeDetails" style="color: var(--muted, #94a3b8);" class="hover:opacity-75" dusk="close-modal">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4" dusk="modal-body">
                    {{-- Problem Statement --}}
                    <div class="mb-6" dusk="problem-section">
                        <h4 class="text-sm font-medium mb-2" style="color: var(--muted, #94a3b8);">Problem Statement</h4>
                        <p style="color: var(--fg, #e5e7eb);" dusk="problem-text">{{ $selectedCollaboration->problem_statement }}</p>
                    </div>

                    {{-- Synthesis --}}
                    @if($selectedCollaboration->synthesis)
                        <div class="mb-6 p-4 rounded-lg" style="background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.25);" dusk="synthesis-section">
                            <h4 class="text-sm font-medium mb-2" style="color: #7dd3fc;">Executive Summary</h4>
                            <div class="text-sm whitespace-pre-wrap" style="color: #bae6fd;" dusk="synthesis-text">{{ $selectedCollaboration->synthesis }}</div>
                        </div>
                    @endif

                    {{-- Agent Executions --}}
                    <div class="mb-6" dusk="executions-section">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--muted, #94a3b8);">Agent Executions</h4>
                        <div class="space-y-3" dusk="executions-list">
                            @foreach($selectedCollaboration->executions as $execution)
                                <div class="rounded-lg p-4" style="border: 1px solid var(--border, #1f2937); background: var(--surface, #0f172a);" dusk="execution-{{ $loop->index }}">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h5 class="font-medium" style="color: var(--fg, #e5e7eb);" dusk="execution-name-{{ $loop->index }}">
                                                {{ $execution->agent_role ?? $execution->agent_name }}
                                            </h5>
                                            <p class="text-sm" style="color: var(--muted, #94a3b8);" dusk="execution-task-{{ $loop->index }}">{{ $execution->task_description }}</p>
                                        </div>
                                        <span class="badge
                                            {{ $execution->status === 'completed' ? 'badge-success' :
                                               ($execution->status === 'failed' ? 'badge-error' : 'badge-warn') }}"
                                            dusk="execution-status-{{ $loop->index }}">
                                            {{ ucfirst($execution->status) }}
                                        </span>
                                    </div>

                                    @if($execution->output && isset($execution->output['summary']))
                                        <div class="mt-2 text-sm p-3 rounded" style="color: var(--muted, #94a3b8); background: var(--bg, #0b1220);" dusk="execution-output-{{ $loop->index }}">
                                            {{ $execution->output['summary'] }}
                                        </div>
                                    @endif

                                    <div class="mt-2 flex gap-4 text-xs" style="color: var(--muted, #94a3b8);" dusk="execution-metrics-{{ $loop->index }}">
                                        <span dusk="execution-duration-{{ $loop->index }}">Duration: {{ $execution->duration_ms ? round($execution->duration_ms / 1000, 1) . 's' : '-' }}</span>
                                        <span dusk="execution-tokens-{{ $loop->index }}">Tokens: {{ number_format($execution->tokens_used) }}</span>
                                        <span dusk="execution-cost-{{ $loop->index }}">Cost: ${{ number_format($execution->cost_spent, 4) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Metadata --}}
                    <div class="grid grid-cols-2 gap-4 text-sm" dusk="metadata-section">
                        <div>
                            <span class="font-medium" style="color: var(--muted, #94a3b8);">Session ID:</span>
                            <span style="color: var(--fg, #e5e7eb);" dusk="session-id">{{ $selectedCollaboration->session_id }}</span>
                        </div>
                        <div>
                            <span class="font-medium" style="color: var(--muted, #94a3b8);">Total Duration:</span>
                            <span style="color: var(--fg, #e5e7eb);" dusk="total-duration">{{ $selectedCollaboration->duration_seconds }}s</span>
                        </div>
                        <div>
                            <span class="font-medium" style="color: var(--muted, #94a3b8);">Total Tokens:</span>
                            <span style="color: var(--fg, #e5e7eb);" dusk="metadata-tokens">{{ number_format($selectedCollaboration->tokens_used) }}</span>
                        </div>
                        <div>
                            <span class="font-medium" style="color: var(--muted, #94a3b8);">Total Cost:</span>
                            <span style="color: var(--fg, #e5e7eb);" dusk="metadata-cost">${{ number_format($selectedCollaboration->cost_spent, 4) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>
