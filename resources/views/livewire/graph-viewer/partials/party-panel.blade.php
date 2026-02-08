{{--
    Party Panel Component
    Displays plaintiff, defendant, and case outcome information for court decisions
--}}

@php
    $partyData = $this->getPartyData();
    $plaintiff = $partyData['plaintiff'] ?? null;
    $defendant = $partyData['defendant'] ?? null;
    $outcome = $partyData['outcome'] ?? null;

    // Determine outcome color based on result
    $outcomeColors = [
        'plaintiff_won' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'border' => 'rgba(34, 197, 94, 0.35)', 'text' => '#22c55e'],
        'defendant_won' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'border' => 'rgba(239, 68, 68, 0.35)', 'text' => '#ef4444'],
        'settled' => ['bg' => 'rgba(59, 130, 246, 0.15)', 'border' => 'rgba(59, 130, 246, 0.35)', 'text' => '#3b82f6'],
        'partial' => ['bg' => 'rgba(234, 179, 8, 0.15)', 'border' => 'rgba(234, 179, 8, 0.35)', 'text' => '#eab308'],
        'dismissed' => ['bg' => 'rgba(107, 114, 128, 0.15)', 'border' => 'rgba(107, 114, 128, 0.35)', 'text' => '#6b7280'],
    ];

    $outcomeStyle = $outcomeColors[$outcome] ?? ['bg' => 'rgba(139, 92, 246, 0.15)', 'border' => 'rgba(139, 92, 246, 0.35)', 'text' => 'var(--accent)'];

    // Format outcome text
    $outcomeText = $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Unknown';
@endphp

<div dusk="party-panel" class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold" style="color: var(--accent);">Parties</h3>
        @if($selectedNode && isset($selectedNode['case_number']))
            <span class="badge-info">Case: {{ $selectedNode['case_number'] }}</span>
        @endif
    </div>

    <!-- Party Information Cards -->
    <div class="grid md:grid-cols-2 gap-4">
        <!-- Plaintiff Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6" style="color: var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold mb-1" style="color: var(--muted);">Plaintiff</div>
                        <div dusk="plaintiff-name" class="text-lg font-medium truncate" style="color: var(--fg);" title="{{ $plaintiff ?? 'Not specified' }}">
                            @if($plaintiff)
                                {{ $plaintiff }}
                            @else
                                <span class="text-sm italic" style="color: var(--muted);">Not specified</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Defendant Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6" style="color: var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold mb-1" style="color: var(--muted);">Defendant</div>
                        <div dusk="defendant-name" class="text-lg font-medium truncate" style="color: var(--fg);" title="{{ $defendant ?? 'Not specified' }}">
                            @if($defendant)
                                {{ $defendant }}
                            @else
                                <span class="text-sm italic" style="color: var(--muted);">Not specified</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Outcome Card -->
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6" style="color: var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold mb-1" style="color: var(--muted);">Case Outcome</div>
                        <div dusk="case-outcome" class="inline-block px-3 py-1 rounded-lg font-semibold text-sm"
                             style="background: {{ $outcomeStyle['bg'] }}; border: 1px solid {{ $outcomeStyle['border'] }}; color: {{ $outcomeStyle['text'] }};"
                             role="status"
                             aria-label="Case outcome: {{ $outcomeText }}">
                            {{ $outcomeText }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!$plaintiff && !$defendant && !$outcome)
        <!-- No Party Data Available -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center gap-3 text-sm" style="color: var(--muted);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Party information not available for this decision.</span>
                </div>
            </div>
        </div>
    @endif
</div>
