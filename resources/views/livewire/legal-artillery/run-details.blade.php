<div @if($isActive) wire:poll.3s @endif>
    <style>
        .la-details { min-height: 100vh; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); padding: 1.5rem 0; }
        .la-info-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 1.25rem; }
        .la-info-label { font-size: 0.75rem; color: var(--muted, #94a3b8); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; }
        .la-info-value { font-size: 1rem; font-weight: 600; margin-top: 0.15rem; }
        .la-badge { display: inline-flex; align-items: center; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .la-badge-completed { background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.35); }
        .la-badge-running { background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.35); animation: pulse 2s infinite; }
        .la-badge-pending { background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35); animation: pulse 2s infinite; }
        .la-badge-failed { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.35); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        /* Timeline */
        .la-timeline { position: relative; padding-left: 2.5rem; }
        .la-timeline::before { content: ''; position: absolute; left: 0.9rem; top: 0; bottom: 0; width: 2px; background: var(--border, #1f2937); }
        .la-timeline-item { position: relative; margin-bottom: 1.5rem; }
        .la-timeline-dot { position: absolute; left: -1.6rem; top: 0.35rem; width: 12px; height: 12px; border-radius: 50%; border: 2px solid; }
        .la-timeline-dot-worker { background: var(--accent, #38bdf8); border-color: var(--accent, #38bdf8); }
        .la-timeline-dot-critic { background: #a78bfa; border-color: #a78bfa; }
        .la-timeline-dot-active { animation: pulse 1.5s infinite; box-shadow: 0 0 8px rgba(56,189,248,.4); }

        /* Iteration card */
        .la-iter-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; overflow: hidden; }
        .la-iter-header { padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: between; gap: 0.75rem; border-bottom: 1px solid var(--border, #1f2937); cursor: pointer; }
        .la-iter-header:hover { background: rgba(56,189,248,0.03); }

        /* Score bars */
        .la-score-row { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .la-score-label { width: 120px; font-size: 0.75rem; color: var(--muted, #94a3b8); text-align: right; }
        .la-score-bar-bg { flex: 1; height: 8px; background: var(--bg, #0b1220); border-radius: 4px; overflow: hidden; }
        .la-score-bar-fill { height: 100%; border-radius: 4px; transition: width .5s ease; }
        .la-score-number { width: 36px; font-size: 0.8rem; font-weight: 600; text-align: right; }

        /* Feedback sections */
        .la-feedback-item { font-size: 0.8rem; padding: 0.25rem 0; padding-left: 1rem; position: relative; }
        .la-feedback-item::before { content: ''; position: absolute; left: 0; top: 0.55rem; width: 6px; height: 6px; border-radius: 50%; }
        .la-feedback-strength::before { background: var(--success, #22c55e); }
        .la-feedback-weakness::before { background: #ef4444; }
        .la-feedback-improvement::before { background: var(--accent, #38bdf8); }

        /* Document preview */
        .la-doc-preview { background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); border-radius: 0.5rem; padding: 1rem; font-family: 'Georgia', serif; font-size: 0.85rem; line-height: 1.7; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }

        /* Review pack */
        .la-review-card { border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; background: var(--card, #111827); }
        .la-review-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border, #1f2937); cursor: pointer; }
        .la-review-header:hover { background: rgba(56,189,248,0.04); }
        .la-review-body { padding: 1rem; border-top: 1px solid var(--border, #1f2937); }
        .la-review-title { font-size: 0.9rem; font-weight: 600; color: var(--fg, #e5e7eb); }
        .la-review-subtitle { font-size: 0.75rem; color: var(--muted, #94a3b8); }
        .la-review-chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.55rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: rgba(56,189,248,0.12); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35); }
        .la-review-ref { background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); border-radius: 0.5rem; padding: 0.65rem 0.75rem; }
        .la-review-ref-title { font-size: 0.75rem; font-weight: 600; color: var(--fg, #e5e7eb); }
        .la-review-ref-meta { font-size: 0.7rem; color: var(--muted, #94a3b8); }
        .la-review-ref-snippet { margin-top: 0.35rem; font-size: 0.75rem; color: var(--fg, #e5e7eb); white-space: pre-wrap; }

        /* Back button */
        .la-btn-back { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: 0.85rem; border: 1px solid var(--border, #1f2937); border-radius: 0.5rem; text-decoration: none; transition: .2s; }
        .la-btn-back:hover { background: #131b2e; border-color: #2d3748; color: var(--fg, #e5e7eb); }

        /* Delta arrow */
        .la-delta-positive { color: var(--success, #22c55e); }
        .la-delta-negative { color: #ef4444; }
        .la-delta-neutral { color: var(--muted, #94a3b8); }

        /* Collapsible */
        .la-collapse-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .la-collapse-content.open { max-height: 2000px; transition: max-height 0.5s ease-in; }

        /* Pending state */
        .la-pending-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 3rem; text-align: center; }
    </style>

    <div class="la-details">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Back --}}
            <div class="mb-4">
                <a href="{{ route('legal-artillery.dashboard') }}" class="la-btn-back">&larr; Dashboard</a>
            </div>

            @if($run)
                @php
                    $status = strtolower($run->status ?? 'pending');
                    $statusLabel = $status === 'pending' ? 'Na cekanju' : ucfirst($status);
                @endphp
                {{-- Run Info Header --}}
                <div class="la-info-card mb-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h1 class="text-xl font-bold" style="color: var(--fg, #e5e7eb);">{{ $run->document_type }}</h1>
                            <p class="text-sm mt-1" style="color: var(--muted, #94a3b8);">
                                Run ID: {{ substr($run->id, 0, 8) }}...
                                &middot; {{ $run->created_at->format('d.m.Y H:i') }}
                            </p>
                        </div>
                        <span class="la-badge la-badge-{{ $status }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="la-info-label">Konacna ocjena</div>
                            <div class="la-info-value" style="color: var(--accent, #38bdf8);">
                                {{ $run->final_score ? number_format((float) $run->final_score, 1) : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="la-info-label">Iteracije</div>
                            <div class="la-info-value" style="color: #a78bfa;">{{ $run->total_iterations ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="la-info-label">Razlog zaustavljanja</div>
                            <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $run->stopped_reason ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="la-info-label">Model</div>
                            <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $run->model_config['llm_model'] ?? '-' }}</div>
                        </div>
                    </div>

                    @php
                        $modelConfig = $run->model_config ?? [];
                        $contextPayload = [];
                        if (!empty($run->context?->assembled_context)) {
                            $contextPayload = json_decode($run->context->assembled_context, true) ?? [];
                        }

                        $caseWarnings = $modelConfig['case_warnings']
                            ?? ($contextPayload['case_warnings'] ?? ($contextPayload['enhanced_context']['case_warnings'] ?? []));

                        if (is_string($caseWarnings)) {
                            $caseWarnings = array_filter(array_map('trim', explode(';', $caseWarnings)));
                        }
                    @endphp

                    <div class="mt-4 border-t border-gray-800 pt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--muted, #94a3b8);">Model konfiguracija</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <div class="la-info-label">Provider</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['provider'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Model</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['llm_model'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Max iteracija</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['max_iterations'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Max tokena</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['max_tokens'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Konvergencija</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['convergence_threshold'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Rekurzija</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $modelConfig['recursion_depth'] ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-gray-800 pt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--muted, #94a3b8);">Prompt verzija</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <div class="la-info-label">Verzija</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $promptVersion['version'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Git hash</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $promptVersion['git_hash'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Agent</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $promptVersion['agent_name'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="la-info-label">Spremljeno</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">{{ $promptVersion['saved_at'] ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    @if(!empty($caseWarnings))
                        <div class="mt-4 border-t border-gray-800 pt-4">
                            <div class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--muted, #94a3b8);">Upozorenja predmeta</div>
                            <ul class="list-disc list-inside text-sm" style="color: var(--muted, #94a3b8);">
                                @foreach($caseWarnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @php
                    $escalationState = $run->getEscalationState();
                    $hasEscalationState = !empty($escalationState['last_action'] ?? null)
                        || !empty($escalationState['last_response'] ?? null)
                        || !empty($escalationState['next_rung'] ?? null)
                        || !empty($escalationState['next_rung_override'] ?? null);
                    $nextRung = $escalationState['next_rung'] ?? null;
                    $overrideRung = $escalationState['next_rung_override'] ?? null;
                    $confirmedRung = (bool) ($escalationState['next_rung_confirmed'] ?? false);
                    $selectedRung = $overrideRung ?: $nextRung;
                @endphp

                @if($hasEscalationState)
                    <div class="la-info-card mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium" style="color: var(--fg, #e5e7eb);">Next Escalation Suggestion</h3>
                            @if($confirmedRung)
                                <span class="la-badge la-badge-completed">Potvrđeno</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="la-info-label">Zadnja akcija</div>
                                <div class="la-info-value" style="color: var(--fg, #e5e7eb); font-size: 0.85rem;">
                                    {{ $escalationState['last_action'] ?? '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="la-info-label">Odgovor</div>
                                <div class="la-info-value" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">
                                    {{ $escalationState['last_response'] ?? '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="la-info-label">Predlozena razina</div>
                                <div class="la-info-value" style="color: #a78bfa; font-size: 0.85rem;">
                                    {{ $nextRung ?? '-' }}
                                </div>
                                @if($overrideRung)
                                    <div class="text-xs mt-1" style="color: var(--muted, #94a3b8);">Override: {{ $overrideRung }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 border-t border-gray-800 pt-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="text-sm" style="color: var(--muted, #94a3b8);">
                                    Odabrana razina: <span style="color: var(--fg, #e5e7eb); font-weight: 600;">{{ $selectedRung ?? '-' }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="confirmEscalationSuggestion"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
                                            style="background: var(--success, #22c55e); color: #0b1220; border: 1px solid rgba(34,197,94,0.4);">
                                        Potvrdi prijedlog
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                                <input type="text"
                                       wire:model="escalationOverrideRung"
                                       class="w-full rounded-md border shadow-sm text-sm"
                                       style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                       placeholder="Unesite novu razinu eskalacije">
                                <button wire:click="overrideEscalationSuggestion"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border"
                                        style="background: rgba(56,189,248,0.12); color: #7dd3fc; border-color: rgba(56,189,248,0.4);">
                                    Override
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Flash Messages --}}
                @if(session()->has('success'))
                    <div class="mb-6 rounded-lg p-4 border" style="background: rgba(34,197,94,0.12); border-color: rgba(34,197,94,0.35); color: #86efac;">
                        <div class="flex">
                            <svg class="h-5 w-5" style="color: #86efac;" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            <p class="ml-3 text-sm" style="color: #86efac;">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif
                @if(session()->has('error'))
                    <div class="mb-6 rounded-lg p-4 border" style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35); color: #fca5a5;">
                        <div class="flex">
                            <svg class="h-5 w-5" style="color: #fca5a5;" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
                            <p class="ml-3 text-sm" style="color: #fca5a5;">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

            {{-- Review Pack --}}
            <div class="la-info-card mb-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Review Pack</h3>
                        <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);">Pregled svih 7 nacrta i njihovih source map referenci.</p>
                    </div>
                    <div class="la-review-chip">
                        Drafts {{ $reviewPackAvailableCount }}/7
                    </div>
                </div>

                @if(!$reviewPackHasData)
                    <div class="text-sm" style="color: var(--muted, #94a3b8);">
                        Review pack jos nije generiran za ovaj run.
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach($reviewPackDrafts as $draft)
                        @php
                            $draftTitle = $draft['title'] ?? ('Draft ' . $loop->iteration);
                            $draftContent = $draft['content'] ?? null;
                            $sourceRefs = $draft['source_refs'] ?? [];
                        @endphp
                        <div class="la-review-card" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                            <div class="la-review-header" @click="open = !open">
                                <div>
                                    <div class="la-review-title">{{ $draftTitle }}</div>
                                    <div class="la-review-subtitle">Draft {{ $draft['index'] ?? $loop->iteration }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if(!empty($draft['missing']))
                                        <span class="la-badge la-badge-failed">Nedostaje</span>
                                    @else
                                        <span class="la-badge la-badge-completed">Spremno</span>
                                    @endif
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         style="color: var(--muted, #94a3b8); transition: transform .2s;"
                                         :style="open ? 'transform: rotate(180deg)' : ''">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="la-review-body" x-show="open" x-transition>
                                @if(!empty($draft['missing']))
                                    <p class="text-sm" style="color: var(--muted, #94a3b8);">Nacrt nije dostupan u review packu.</p>
                                @else
                                    @if($draftContent)
                                        <div class="la-doc-preview" style="max-height: 260px;">{{ $draftContent }}</div>
                                    @else
                                        <p class="text-sm" style="color: var(--muted, #94a3b8);">Sadrzaj nacrta nije dostupan.</p>
                                    @endif
                                @endif

                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">Source map reference</div>
                                    @if(!empty($sourceRefs))
                                        <div class="space-y-2">
                                            @foreach($sourceRefs as $ref)
                                                @php
                                                    $refLabel = is_array($ref)
                                                        ? ($ref['label'] ?? $ref['source'] ?? $ref['title'] ?? $ref['id'] ?? $ref['reference'] ?? 'Reference')
                                                        : 'Reference';
                                                    $refSnippet = is_array($ref)
                                                        ? ($ref['snippet'] ?? $ref['excerpt'] ?? $ref['content'] ?? null)
                                                        : null;
                                                    $refMetaParts = [];
                                                    if (is_array($ref)) {
                                                        foreach (['type', 'source_id', 'evidence_id', 'decision_id', 'law_id', 'page', 'section', 'case_id'] as $key) {
                                                            if (!empty($ref[$key])) {
                                                                $refMetaParts[] = $key . ': ' . $ref[$key];
                                                            }
                                                        }
                                                    } elseif (!empty($ref)) {
                                                        $refMetaParts[] = (string) $ref;
                                                    }
                                                @endphp
                                                <div class="la-review-ref">
                                                    <div class="la-review-ref-title">{{ $refLabel }}</div>
                                                    @if(!empty($refMetaParts))
                                                        <div class="la-review-ref-meta">{{ implode(' • ', $refMetaParts) }}</div>
                                                    @endif
                                                    @if($refSnippet)
                                                        <div class="la-review-ref-snippet">{{ $refSnippet }}</div>
                                                    @elseif(is_array($ref) && empty($refMetaParts))
                                                        <div class="la-review-ref-snippet">{{ json_encode($ref, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm" style="color: var(--muted, #94a3b8);">Nema dostupnih source map referenci.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Approval & Dispatch Section --}}
            @if($run->status === 'completed')
                <div class="la-info-card mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium" style="color: var(--fg, #e5e7eb);">Odobrenje i slanje</h3>
                            <div class="flex items-center gap-2">
                                @if($run->approved_at)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" style="background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.35);">
                                        Odobreno {{ $run->approved_at->format('d.m.Y H:i') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" style="background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.35);">
                                        Ceka odobrenje
                                    </span>
                                @endif
                                @if($run->docx_verified)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35);">
                                        DOCX verificiran
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Metadata row --}}
                        <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
                            <div>
                                <span style="color: var(--muted, #94a3b8);">Profil:</span>
                                <span class="font-medium" style="color: var(--fg, #e5e7eb);">{{ $run->document_type }}</span>
                            </div>
                            <div>
                                <span style="color: var(--muted, #94a3b8);">Model:</span>
                                <span class="font-medium" style="color: var(--fg, #e5e7eb);">{{ $run->model_config['llm_model'] ?? $run->model_config['ai_model'] ?? '-' }}</span>
                            </div>
                            <div>
                                <span style="color: var(--muted, #94a3b8);">DOCX:</span>
                                <span class="font-medium" style="color: var(--fg, #e5e7eb);">{{ isset($run->model_config['docx_path']) ? basename($run->model_config['docx_path']) : '-' }}</span>
                            </div>
                        </div>

                        {{-- Approval notes display --}}
                        @if($run->approval_notes)
                            <div class="mb-4 p-3 rounded text-sm" style="background: rgba(34,197,94,0.12); color: #86efac; border: 1px solid rgba(34,197,94,0.35);">
                                <strong>Biljeske odobrenja:</strong> {{ $run->approval_notes }}
                            </div>
                        @endif

                        {{-- Action buttons --}}
                        <div class="flex gap-3">
                            @if(!$run->approved_at)
                                <button wire:click="toggleApprovalForm"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2"
                                        style="background: var(--success, #22c55e); color: #0b1220; border: 1px solid rgba(34,197,94,0.4);">
                                    Odobri dokument
                                </button>
                            @else
                                <button wire:click="toggleDispatchForm"
                                        @if(!$run->isReadyForDispatch()) disabled @endif
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 {{ $run->isReadyForDispatch() ? '' : 'cursor-not-allowed' }}"
                                        style="{{ $run->isReadyForDispatch()
                                            ? 'background: #4f46e5; color: #e2e8f0; border: 1px solid rgba(99,102,241,0.5);'
                                            : 'background: var(--border, #1f2937); color: var(--muted, #94a3b8); border: 1px solid var(--border, #1f2937);' }}">
                                    Posalji dokument
                                </button>
                            @endif
                        </div>

                        {{-- Approval Form --}}
                        @if($showApprovalForm)
                            <div class="mt-4 p-4 rounded-lg border" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                <h4 class="text-sm font-medium mb-3" style="color: var(--fg, #e5e7eb);">Odobravanje dokumenta</h4>
                                <div class="mb-3">
                                    <label for="approval-notes" class="block text-sm mb-1" style="color: var(--muted, #94a3b8);">Biljeske (opcijsko):</label>
                                    <textarea wire:model="approvalNotes" id="approval-notes" rows="3"
                                              class="w-full rounded-md border shadow-sm text-sm"
                                              style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                              placeholder="Npr. Pregledano, citati tocni, tone odgovarajuci..."></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="approveRun" wire:loading.attr="disabled"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
                                            style="background: var(--success, #22c55e); color: #0b1220; border: 1px solid rgba(34,197,94,0.4);">
                                        <span wire:loading.remove wire:target="approveRun">Potvrdi odobrenje</span>
                                        <span wire:loading wire:target="approveRun">Odobravam...</span>
                                    </button>
                                    <button wire:click="toggleApprovalForm"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border"
                                            style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);">
                                        Odustani
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Dispatch Form --}}
                        @if($showDispatchForm)
                            <div class="mt-4 p-4 rounded-lg border" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                <h4 class="text-sm font-medium mb-3" style="color: var(--fg, #e5e7eb);">Slanje dokumenta</h4>
                                @php
                                    $digitalSignature = $run->model_config['digital_signature'] ?? null;
                                    $dispatchSignature = $run->model_config['dispatch_signature'] ?? null;
                                    $hasDigitalSignature = is_array($digitalSignature)
                                        && (!empty($digitalSignature['signed_pdf_path']) || !empty($digitalSignature['signature']) || !empty($digitalSignature['hash']));
                                    $previewInfo = $run->model_config['dispatch_preview'] ?? null;
                                    $previewedChannels = is_array($previewInfo) ? ($previewInfo['channels'] ?? []) : [];
                                    $hasPreview = is_array($previewInfo) && !empty($previewInfo['previewed_at']);
                                    $currentChannels = [];

                                    if ($dispatchSendEmail) {
                                        $currentChannels[] = 'email';
                                    }
                                    if ($dispatchSubmitEkom) {
                                        $currentChannels[] = 'ekom';
                                    }

                                    $previewNeedsRefresh = false;
                                    foreach ($currentChannels as $channel) {
                                        if (!in_array($channel, $previewedChannels, true)) {
                                            $previewNeedsRefresh = true;
                                            break;
                                        }
                                    }

                                    $signatureReady = ($signatureChoice === 'digital' && $hasDigitalSignature)
                                        || ($signatureChoice === 'typed' && trim($typedSignature) !== '');
                                    $canDispatch = $hasPreview && !$previewNeedsRefresh && $signatureReady && !empty($currentChannels);
                                @endphp

                                <ol class="text-xs mb-4 space-y-1" style="color: var(--muted, #94a3b8);">
                                    <li>1. Potpisite dokument (digitalno ili tipkani potpis).</li>
                                    <li>2. Napravite pregled slanja.</li>
                                    <li>3. Potvrdite slanje.</li>
                                </ol>
                                <div class="space-y-3">
                                    <div class="rounded-lg border p-3" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                        <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">1. Potpis prije slanja</div>
                                        @if($hasDigitalSignature)
                                            <label class="flex items-center gap-2 text-sm mb-2" style="color: var(--fg, #e5e7eb);">
                                                <input type="radio" wire:model="signatureChoice" value="digital" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                                Koristi digitalni potpis
                                            </label>
                                        @endif
                                        <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                            <input type="radio" wire:model="signatureChoice" value="typed" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                            Unesite potpis (ime i prezime)
                                        </label>
                                        @if($signatureChoice === 'typed')
                                            <div class="mt-2">
                                                <input type="text" wire:model="typedSignature"
                                                       class="w-full rounded-md border shadow-sm text-sm"
                                                       style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                                       placeholder="Npr. Ivana Horvat">
                                            </div>
                                        @endif
                                        @if(!$hasDigitalSignature && $signatureChoice === 'digital')
                                            <p class="mt-2 text-xs" style="color: #fca5a5;">Digitalni potpis nije dostupan za ovaj dokument.</p>
                                        @endif
                                    </div>
                                    <div class="rounded-lg border p-3" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                        <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">2. Odabir kanala</div>
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                                <input type="checkbox" wire:model="dispatchSendEmail" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                                Posalji email
                                            </label>
                                            <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                                <input type="checkbox" wire:model="dispatchAsDraft" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                                Spremi kao draft
                                            </label>
                                            <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                                <input type="checkbox" wire:model="dispatchSubmitEkom" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                                e-komunikacija
                                            </label>
                                        </div>
                                        @if($dispatchSendEmail)
                                            <div class="mt-3">
                                                <label class="block text-sm mb-1" style="color: var(--muted, #94a3b8);">Email primatelja (opcijsko, koristi profil ako prazno):</label>
                                                <input type="email" wire:model="dispatchEmail"
                                                       class="w-full rounded-md border shadow-sm text-sm"
                                                       style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                                       placeholder="primatelj@sud.hr">
                                            </div>
                                        @endif
                                    </div>
                                    <div class="rounded-lg border p-3" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--muted, #94a3b8);">3. Pregled slanja</div>
                                                <div class="text-xs" style="color: var(--muted, #94a3b8);">
                                                    @if($hasPreview && !$previewNeedsRefresh)
                                                        Pregled napravljen: {{ $previewInfo['previewed_at'] ?? '' }}
                                                    @else
                                                        Pregled slanja je obavezan prije slanja.
                                                    @endif
                                                </div>
                                            </div>
                                            <button wire:click="previewDispatch" wire:loading.attr="disabled"
                                                    @if(empty($currentChannels) || !$signatureReady) disabled @endif
                                                    class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-md border"
                                                    style="{{ empty($currentChannels) || !$signatureReady
                                                        ? 'background: var(--border, #1f2937); color: var(--muted, #94a3b8); border-color: var(--border, #1f2937);'
                                                        : 'background: rgba(79,70,229,0.12); color: #a5b4fc; border-color: rgba(99,102,241,0.4);' }}">
                                                <span wire:loading.remove wire:target="previewDispatch">Pregled slanja</span>
                                                <span wire:loading wire:target="previewDispatch">Ucitavam...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <button wire:click="dispatchRun" wire:loading.attr="disabled"
                                            @if(!$canDispatch) disabled @endif
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
                                            style="{{ $canDispatch
                                                ? 'background: #4f46e5; color: #e2e8f0; border: 1px solid rgba(99,102,241,0.5);'
                                                : 'background: var(--border, #1f2937); color: var(--muted, #94a3b8); border: 1px solid var(--border, #1f2937);' }}">
                                        <span wire:loading.remove wire:target="dispatchRun">Posalji</span>
                                        <span wire:loading wire:target="dispatchRun">Saljem...</span>
                                    </button>
                                    <button wire:click="toggleDispatchForm"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border"
                                            style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);">
                                        Odustani
                                    </button>
                                </div>

                                @if(empty($currentChannels))
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Odaberite barem jedan kanal slanja.</div>
                                @elseif($previewNeedsRefresh)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Promijenili ste kanale slanja. Napravite novi pregled prije slanja.</div>
                                @elseif(!$signatureReady)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Potpis je obavezan prije slanja.</div>
                                @elseif(!$hasPreview)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Pregled slanja je obavezan prije slanja.</div>
                                @endif

                                @if($dispatchPreviewError)
                                    <div class="mt-4 rounded-md border p-3 text-sm" style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35); color: #fca5a5;">
                                        {{ $dispatchPreviewError }}
                                    </div>
                                @endif

                                @if($dispatchPreview)
                                    <div class="mt-4 space-y-4">
                                        <h5 class="text-sm font-semibold" style="color: var(--fg, #e5e7eb);">Pregled slanja</h5>

                                        @if(isset($dispatchPreview['email']))
                                            <div class="rounded-lg border p-4" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                                <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">Email</div>
                                                <div class="grid grid-cols-2 gap-3 text-sm" style="color: var(--fg, #e5e7eb);">
                                                    <div><span style="color: var(--muted, #94a3b8);">To:</span> {{ $dispatchPreview['email']['to'] ?: '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">From:</span> {{ $dispatchPreview['email']['from'] ?: '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Subject:</span> {{ $dispatchPreview['email']['subject'] }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">CC:</span> {{ $dispatchPreview['email']['cc'] ?: '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Draft:</span> {{ $dispatchPreview['email']['as_draft'] ? 'Da' : 'Ne' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Attachment:</span> {{ $dispatchPreview['email']['attachment'] ?? '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Attachment OK:</span> {{ $dispatchPreview['email']['attachment_exists'] ? 'Da' : 'Ne' }}</div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--muted, #94a3b8);">Body</div>
                                                    <pre class="text-xs p-3 rounded border whitespace-pre-wrap" style="background: var(--bg, #0b1220); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);">{{ $dispatchPreview['email']['body'] }}</pre>
                                                </div>
                                            </div>
                                        @endif

                                        @if(isset($dispatchPreview['ekom']))
                                            <div class="rounded-lg border p-4" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                                <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">e-Komunikacija</div>
                                                <div class="grid grid-cols-2 gap-3 text-sm" style="color: var(--fg, #e5e7eb);">
                                                    <div><span style="color: var(--muted, #94a3b8);">Court:</span> {{ $dispatchPreview['ekom']['court_id'] ?? '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Doc type:</span> {{ $dispatchPreview['ekom']['document_type'] ?? '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Attachment:</span> {{ $dispatchPreview['ekom']['attachment'] ?? '-' }}</div>
                                                    <div><span style="color: var(--muted, #94a3b8);">Attachment OK:</span> {{ $dispatchPreview['ekom']['attachment_exists'] ? 'Da' : 'Ne' }}</div>
                                                </div>

                                                @if(isset($dispatchPreview['ekom']['payload']))
                                                    <div class="mt-3 text-sm" style="color: var(--fg, #e5e7eb);">
                                                        <div><span style="color: var(--muted, #94a3b8);">Case number:</span> {{ $dispatchPreview['ekom']['payload']['case_number'] ?? '-' }}</div>
                                                        <div><span style="color: var(--muted, #94a3b8);">Court ID:</span> {{ $dispatchPreview['ekom']['payload']['court_id'] ?? '-' }}</div>
                                                        <div><span style="color: var(--muted, #94a3b8);">Document type:</span> {{ $dispatchPreview['ekom']['payload']['document_type'] ?? '-' }}</div>
                                                    </div>
                                                @endif

                                                @if(!empty($dispatchPreview['ekom']['payload']['attachments']))
                                                    <div class="mt-3">
                                                        <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--muted, #94a3b8);">Attachments</div>
                                                        <ul class="space-y-1 text-sm" style="color: var(--fg, #e5e7eb);">
                                                            @foreach($dispatchPreview['ekom']['payload']['attachments'] as $attachment)
                                                                <li class="flex items-center justify-between gap-2">
                                                                    <span>{{ $attachment['filename'] ?? 'attachment' }} ({{ $attachment['mime_type'] ?? 'unknown' }})</span>
                                                                    <span class="text-xs" style="color: var(--muted, #94a3b8);">content length: {{ number_format($attachment['content_length'] ?? 0) }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Dispatch result --}}
                        @if(isset($run->model_config['dispatch_result']))
                            <div class="mt-4 p-3 rounded text-sm" style="background: rgba(56,189,248,0.12); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35);">
                                <strong>Rezultat slanja:</strong>
                                @foreach($run->model_config['dispatch_result'] as $channel => $result)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-1" style="background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.35);">
                                        {{ $channel }}: {{ $result['status'] ?? 'unknown' }}
                                    </span>
                                @endforeach
                                @if(isset($run->model_config['dispatched_at']))
                                    <span class="text-xs ml-2" style="color: #7dd3fc;">({{ $run->model_config['dispatched_at'] }})</span>
                                @endif
                            </div>
                        @endif

                        @php
                            $dispatchErrors = $run->model_config['dispatch_errors'] ?? [];
                        @endphp

                        @if(!empty($dispatchErrors))
                            <div class="mt-4 rounded-lg border p-4 text-sm" style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35); color: #fca5a5;">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <strong>Zadnje greske slanja:</strong>
                                    </div>
                                    <button wire:click="toggleRetryDispatchForm"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border"
                                            style="background: rgba(239,68,68,0.18); border-color: rgba(239,68,68,0.4); color: #fecaca;">
                                        Ponovi slanje
                                    </button>
                                </div>
                                <ul class="mt-2 space-y-1 text-xs" style="color: #fecaca;">
                                    @foreach($dispatchErrors as $channel => $message)
                                        <li>
                                            <span class="font-semibold uppercase">{{ $channel }}:</span>
                                            <span class="break-words">{{ $message }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($showRetryDispatchForm)
                            <div class="mt-4 rounded-lg border p-4" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                <h4 class="text-sm font-medium mb-3" style="color: var(--fg, #e5e7eb);">Ponovni pokušaj slanja</h4>
                                @php
                                    $digitalSignature = $run->model_config['digital_signature'] ?? null;
                                    $dispatchSignature = $run->model_config['dispatch_signature'] ?? null;
                                    $hasDigitalSignature = is_array($digitalSignature)
                                        && (!empty($digitalSignature['signed_pdf_path']) || !empty($digitalSignature['signature']) || !empty($digitalSignature['hash']));
                                    $previewInfo = $run->model_config['dispatch_preview'] ?? null;
                                    $previewedChannels = is_array($previewInfo) ? ($previewInfo['channels'] ?? []) : [];
                                    $hasPreview = is_array($previewInfo) && !empty($previewInfo['previewed_at']);
                                    $currentChannels = [];

                                    if ($dispatchSendEmail) {
                                        $currentChannels[] = 'email';
                                    }
                                    if ($dispatchSubmitEkom) {
                                        $currentChannels[] = 'ekom';
                                    }

                                    $previewNeedsRefresh = false;
                                    foreach ($currentChannels as $channel) {
                                        if (!in_array($channel, $previewedChannels, true)) {
                                            $previewNeedsRefresh = true;
                                            break;
                                        }
                                    }

                                    $signatureReady = ($signatureChoice === 'digital' && $hasDigitalSignature)
                                        || ($signatureChoice === 'typed' && trim($typedSignature) !== '');
                                    $canDispatch = $hasPreview && !$previewNeedsRefresh && $signatureReady && !empty($currentChannels);
                                @endphp
                                <div class="space-y-3">
                                    <div class="rounded-lg border p-3" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                        <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--muted, #94a3b8);">1. Potpis prije slanja</div>
                                        @if($hasDigitalSignature)
                                            <label class="flex items-center gap-2 text-sm mb-2" style="color: var(--fg, #e5e7eb);">
                                                <input type="radio" wire:model="signatureChoice" value="digital" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                                Koristi digitalni potpis
                                            </label>
                                        @endif
                                        <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                            <input type="radio" wire:model="signatureChoice" value="typed" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                            Unesite potpis (ime i prezime)
                                        </label>
                                        @if($signatureChoice === 'typed')
                                            <div class="mt-2">
                                                <input type="text" wire:model="typedSignature"
                                                       class="w-full rounded-md border shadow-sm text-sm"
                                                       style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                                       placeholder="Npr. Ivana Horvat">
                                            </div>
                                        @endif
                                        @if(!$hasDigitalSignature && $signatureChoice === 'digital')
                                            <p class="mt-2 text-xs" style="color: #fca5a5;">Digitalni potpis nije dostupan za ovaj dokument.</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                            <input type="checkbox" wire:model="dispatchSendEmail" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                            Posalji email
                                        </label>
                                        <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                            <input type="checkbox" wire:model="dispatchAsDraft" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                            Spremi kao draft
                                        </label>
                                        <label class="flex items-center gap-2 text-sm" style="color: var(--fg, #e5e7eb);">
                                            <input type="checkbox" wire:model="dispatchSubmitEkom" class="rounded" style="border-color: var(--border, #1f2937); color: #4f46e5;">
                                            e-komunikacija
                                        </label>
                                    </div>
                                    @if($dispatchSendEmail)
                                        <div>
                                            <label class="block text-sm mb-1" style="color: var(--muted, #94a3b8);">Email primatelja (opcijsko, koristi profil ako prazno):</label>
                                            <input type="email" wire:model="dispatchEmail"
                                                   class="w-full rounded-md border shadow-sm text-sm"
                                                   style="background: var(--card, #111827); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);"
                                                   placeholder="primatelj@sud.hr">
                                        </div>
                                    @endif
                                    <div class="rounded-lg border p-3" style="background: var(--card, #111827); border-color: var(--border, #1f2937);">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: var(--muted, #94a3b8);">2. Pregled slanja</div>
                                                <div class="text-xs" style="color: var(--muted, #94a3b8);">
                                                    @if($hasPreview && !$previewNeedsRefresh)
                                                        Pregled napravljen: {{ $previewInfo['previewed_at'] ?? '' }}
                                                    @else
                                                        Pregled slanja je obavezan prije slanja.
                                                    @endif
                                                </div>
                                            </div>
                                            <button wire:click="previewDispatch" wire:loading.attr="disabled"
                                                    @if(empty($currentChannels) || !$signatureReady) disabled @endif
                                                    class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-md border"
                                                    style="{{ empty($currentChannels) || !$signatureReady
                                                        ? 'background: var(--border, #1f2937); color: var(--muted, #94a3b8); border-color: var(--border, #1f2937);'
                                                        : 'background: rgba(79,70,229,0.12); color: #a5b4fc; border-color: rgba(99,102,241,0.4);' }}">
                                                <span wire:loading.remove wire:target="previewDispatch">Pregled slanja</span>
                                                <span wire:loading wire:target="previewDispatch">Ucitavam...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <button wire:click="retryDispatch" wire:loading.attr="disabled"
                                            @if(!$canDispatch) disabled @endif
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
                                            style="{{ $canDispatch
                                                ? 'background: #ef4444; color: #0b1220; border: 1px solid rgba(239,68,68,0.45);'
                                                : 'background: var(--border, #1f2937); color: var(--muted, #94a3b8); border: 1px solid var(--border, #1f2937);' }}">
                                        <span wire:loading.remove wire:target="retryDispatch">Ponovi slanje</span>
                                        <span wire:loading wire:target="retryDispatch">Saljem...</span>
                                    </button>
                                    <button wire:click="toggleRetryDispatchForm"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border"
                                            style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);">
                                        Odustani
                                    </button>
                                </div>
                                @if(empty($currentChannels))
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Odaberite barem jedan kanal slanja.</div>
                                @elseif($previewNeedsRefresh)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Promijenili ste kanale slanja. Napravite novi pregled prije slanja.</div>
                                @elseif(!$signatureReady)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Potpis je obavezan prije slanja.</div>
                                @elseif(!$hasPreview)
                                    <div class="mt-3 text-xs" style="color: #fca5a5;">Pregled slanja je obavezan prije slanja.</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

@if($run->status === 'failed' && $run->error_message)
                        <div class="mt-4 p-4 rounded-lg border" style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35);">
                            <h4 class="text-sm font-semibold mb-2" style="color: #fca5a5;">Detalji greske</h4>
                            <pre class="text-xs whitespace-pre-wrap break-words font-mono p-3 rounded border" style="color: #fecaca; background: rgba(239,68,68,0.16); border-color: rgba(239,68,68,0.4);">{{ $run->error_message }}</pre>
                            <div class="mt-2 flex gap-4 text-xs" style="color: #fca5a5;">
                                <span>Run ID: {{ $run->id }}</span>
                                <span>Model: {{ $run->model_config['llm_model'] ?? $run->model_config['worker_model'] ?? '-' }}</span>
                                @if($run->updated_at)
                                    <span>Neuspjelo: {{ $run->updated_at->format('d.m.Y H:i:s') }}</span>
                                @endif
                            </div>
                        </div>
                    @endif

            {{-- Generation Monitor (if running) --}}
            @if($run->status === 'running')
                <div class="mb-6">
                    @livewire('legal-artillery.generation-monitor', ['runId' => $run->id])
                </div>
            @endif

            {{-- Pending/Running State --}}
            @if(in_array($run->status, ['pending', 'running']))
                @if($run->status === 'pending')
                    <div class="la-pending-card mb-6">
                        <svg class="animate-spin h-10 w-10 mx-auto mb-3" style="color: var(--accent, #38bdf8);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p style="color: var(--accent, #38bdf8); font-weight: 600;">Na cekanju u redu...</p>
                        <p class="mt-1" style="color: var(--muted, #94a3b8); font-size: 0.8rem;">Job je poslan u red cekanja. Generacija ce poceti uskoro.</p>
                    </div>
                @elseif($run->status === 'running' && count($iterationPairs) === 0)
                    <div class="la-pending-card mb-6">
                        <svg class="animate-spin h-10 w-10 mx-auto mb-3" style="color: var(--warn, #eab308);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p style="color: var(--warn, #eab308); font-weight: 600;">Generacija u tijeku...</p>
                        <p class="mt-1" style="color: var(--muted, #94a3b8); font-size: 0.8rem;">Radim na prvoj iteraciji. Rezultati ce se pojaviti uskoro.</p>
                    </div>
                @endif
            @endif

                {{-- Iteration Timeline --}}
                @if(count($iterationPairs) > 0)
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--fg, #e5e7eb);">Iteracije</h2>

                    <div class="la-timeline" x-data="{ openIteration: {{ count($iterationPairs) }} }">
                        @foreach($iterationPairs as $pair)
                            <div class="la-timeline-item">
                                <div class="la-timeline-dot la-timeline-dot-worker {{ $loop->last && $isActive ? 'la-timeline-dot-active' : '' }}"></div>

                                <div class="la-iter-card">
                                    {{-- Iteration Header --}}
                                    <div class="la-iter-header" @click="openIteration = openIteration === {{ $pair['number'] }} ? 0 : {{ $pair['number'] }}">
                                        <div class="flex items-center gap-3 flex-1">
                                            <span class="font-bold text-sm" style="color: var(--accent, #38bdf8);">
                                                #{{ $pair['number'] }}
                                            </span>

                                            @if($pair['critic'])
                                                <span class="text-sm font-semibold" style="color: var(--fg, #e5e7eb);">
                                                    Ocjena: {{ number_format((float) $pair['critic']->weighted_score, 1) }}
                                                </span>

                                                @if($pair['critic']->improvement_delta !== null)
                                                    @php $delta = (float) $pair['critic']->improvement_delta; @endphp
                                                    <span class="text-xs font-medium {{ $delta > 0 ? 'la-delta-positive' : ($delta < 0 ? 'la-delta-negative' : 'la-delta-neutral') }}">
                                                        {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }}%
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-sm" style="color: var(--warn, #eab308);">U tijeku...</span>
                                            @endif
                                        </div>

                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                             style="color: var(--muted, #94a3b8); transition: transform .2s;"
                                             :style="openIteration === {{ $pair['number'] }} ? 'transform: rotate(180deg)' : ''">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>

                                    {{-- Collapsible Content --}}
                                    <div class="la-collapse-content" :class="openIteration === {{ $pair['number'] }} ? 'open' : ''">
                                        <div class="p-4 space-y-4">
                                            {{-- Score Breakdown --}}
                                            @if($pair['critic'] && $pair['critic']->scores)
                                                <div>
                                                    <h4 class="text-xs font-semibold mb-2" style="color: var(--muted, #94a3b8); text-transform: uppercase;">Ocjene po dimenzijama</h4>
                                                    @php
                                                        $scoreColors = [
                                                            'legal_rigor' => '#38bdf8',
                                                            'persuasiveness' => '#a78bfa',
                                                            'clarity' => '#22c55e',
                                                            'evidence_integration' => '#eab308',
                                                            'formatting' => '#f472b6',
                                                        ];
                                                        $scoreLabels = [
                                                            'legal_rigor' => 'Pravna preciznost',
                                                            'persuasiveness' => 'Uvjerljivost',
                                                            'clarity' => 'Jasnoća',
                                                            'evidence_integration' => 'Integ. dokaza',
                                                            'formatting' => 'Formatiranje',
                                                        ];
                                                    @endphp
                                                    @foreach($pair['critic']->scores as $dim => $score)
                                                        <div class="la-score-row">
                                                            <div class="la-score-label">{{ $scoreLabels[$dim] ?? $dim }}</div>
                                                            <div class="la-score-bar-bg">
                                                                <div class="la-score-bar-fill" style="width: {{ min(100, (float) $score) }}%; background: {{ $scoreColors[$dim] ?? '#38bdf8' }};"></div>
                                                            </div>
                                                            <div class="la-score-number" style="color: {{ $scoreColors[$dim] ?? '#38bdf8' }};">{{ number_format((float) $score, 0) }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Critic Feedback --}}
                                            @if($pair['critic'] && $pair['critic']->critic_feedback)
                                                @php $feedback = $pair['critic']->critic_feedback['feedback'] ?? $pair['critic']->critic_feedback; @endphp

                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    {{-- Strengths --}}
                                                    @if(!empty($feedback['strengths']))
                                                        <div>
                                                            <h5 class="text-xs font-semibold mb-1" style="color: var(--success, #22c55e); text-transform: uppercase;">Snage</h5>
                                                            @foreach($feedback['strengths'] as $item)
                                                                <div class="la-feedback-item la-feedback-strength" style="color: var(--fg, #e5e7eb);">{{ $item }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    {{-- Weaknesses --}}
                                                    @if(!empty($feedback['weaknesses']))
                                                        <div>
                                                            <h5 class="text-xs font-semibold mb-1" style="color: #ef4444; text-transform: uppercase;">Slabosti</h5>
                                                            @foreach($feedback['weaknesses'] as $item)
                                                                <div class="la-feedback-item la-feedback-weakness" style="color: var(--fg, #e5e7eb);">{{ $item }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    {{-- Improvements --}}
                                                    @if(!empty($feedback['specific_improvements']))
                                                        <div>
                                                            <h5 class="text-xs font-semibold mb-1" style="color: var(--accent, #38bdf8); text-transform: uppercase;">Prijedlozi</h5>
                                                            @foreach($feedback['specific_improvements'] as $item)
                                                                <div class="la-feedback-item la-feedback-improvement" style="color: var(--fg, #e5e7eb);">{{ $item }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Document Preview --}}
                                            @if($pair['worker'] && $pair['worker']->document_version)
                                                <div x-data="{ showDoc: false }">
                                                    <button @click="showDoc = !showDoc" class="text-xs font-medium" style="color: var(--accent, #38bdf8); cursor: pointer; background: none; border: none;">
                                                        <span x-show="!showDoc">Prikazi dokument &darr;</span>
                                                        <span x-show="showDoc">Sakrij dokument &uarr;</span>
                                                    </button>
                                                    <div x-show="showDoc" x-transition class="mt-2">
                                                        <div class="la-doc-preview">{{ $pair['worker']->document_version }}</div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Final Document --}}
                @if($run->status === 'completed' && $run->final_document)
                    <div class="mt-8">
                        <h2 class="text-lg font-semibold mb-3" style="color: var(--fg, #e5e7eb);">Konacni dokument</h2>
                        <div class="la-info-card">
                            <div class="la-doc-preview" style="max-height: 600px;">{{ $run->final_document }}</div>
                        </div>
                    </div>
                @endif

            {{-- Completeness Check --}}
            @if($completenessCheck)
                <div class="la-info-card mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center gap-2 mb-3">
                            @if($completenessCheck['complete'])
                                <span class="h-2.5 w-2.5 rounded-full" style="background: var(--success, #22c55e);"></span>
                                <h3 class="text-lg font-medium" style="color: var(--fg, #e5e7eb);">Strukturalna provjera: Kompletno</h3>
                            @else
                                <span class="h-2.5 w-2.5 rounded-full" style="background: var(--warn, #eab308);"></span>
                                <h3 class="text-lg font-medium" style="color: var(--fg, #e5e7eb);">Strukturalna provjera: Nepotpuno</h3>
                            @endif
                        </div>

                        @if(!empty($completenessCheck['missing_sections']))
                            <div class="mb-3">
                                <p class="text-sm font-medium mb-1" style="color: #fca5a5;">Nedostajuce sekcije:</p>
                                <ul class="list-disc list-inside text-sm" style="color: #fecaca;">
                                    @foreach($completenessCheck['missing_sections'] as $section)
                                        <li>{{ $section }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($completenessCheck['missing_evidence']))
                            <div class="mb-3">
                                <p class="text-sm font-medium mb-1" style="color: #fca5a5;">Nedostajuci dokazi:</p>
                                <ul class="list-disc list-inside text-sm" style="color: #fecaca;">
                                    @foreach($completenessCheck['missing_evidence'] as $evidence)
                                        <li>{{ $evidence }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($completenessCheck['warnings']))
                            <div>
                                <p class="text-sm font-medium mb-1" style="color: #fde047;">Upozorenja:</p>
                                <ul class="list-disc list-inside text-sm" style="color: #fef08a;">
                                    @foreach($completenessCheck['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Final Document --}}
            @if($run->final_document)
                <div class="la-info-card">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium" style="color: var(--fg, #e5e7eb);">Konacni dokument</h3>
                            <div class="flex items-center gap-2">
                                @if($docxPath)
                                    <a href="{{ route('legal-artillery.download', ['runId' => $run->id, 'format' => 'docx']) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded"
                                       style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937);">
                                        DOCX
                                    </a>
                                    <a href="{{ route('legal-artillery.download', ['runId' => $run->id, 'format' => 'pdf']) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded"
                                       style="background: rgba(239,68,68,0.12); color: #fecaca; border: 1px solid rgba(239,68,68,0.4);">
                                        PDF
                                    </a>
                                @endif
                                @if(!$isEditing)
                                    <button wire:click="startEditing"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded"
                                            style="background: rgba(79,70,229,0.12); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.4);">
                                        Uredi
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($saveMessage)
                            <div class="mb-4 p-3 rounded text-sm border"
                                 style="{{ str_contains($saveMessage, 'odobren')
                                    ? 'background: rgba(34,197,94,0.12); color: #86efac; border-color: rgba(34,197,94,0.35);'
                                    : 'background: rgba(56,189,248,0.12); color: #7dd3fc; border-color: rgba(56,189,248,0.35);' }}">
                                {{ $saveMessage }}
                            </div>
                        @endif

                        @if($isEditing)
                            {{-- Editing mode --}}
                            <textarea wire:model="editableDocument"
                                      rows="30"
                                      class="w-full font-mono text-sm rounded-lg p-4 border"
                                      style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);"></textarea>
                            <div class="mt-3 flex justify-end gap-2">
                                <button wire:click="cancelEditing"
                                        class="px-4 py-2 text-sm font-medium rounded-md border"
                                        style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);">
                                    Odustani
                                </button>
                                <button wire:click="saveDocument"
                                        class="px-4 py-2 text-sm font-medium rounded-md"
                                        style="background: #4f46e5; color: #e2e8f0; border: 1px solid rgba(99,102,241,0.5);">
                                    Spremi izmjene
                                </button>
                            </div>
                        @else
                            {{-- Read-only display --}}
                            <div class="prose max-w-none p-4 rounded border"
                                 style="background: var(--bg, #0b1220); border-color: var(--border, #1f2937); color: var(--fg, #e5e7eb);">
                                {!! nl2br(e($run->final_document)) !!}
                            </div>
                        @endif

                        {{-- Approval Gate --}}
                        @if($run->status === 'completed' && !$isEditing)
                            <div class="mt-6 pt-4 border-t" style="border-color: var(--border, #1f2937);">
                                @if($isApproved)
                                    <div class="flex items-center gap-2" style="color: #86efac;">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-medium">Dokument odobren i poslan</span>
                                    </div>
                                @elseif($hasPendingDispatch)
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm" style="color: var(--muted, #94a3b8);">
                                            Dokument ceka odobrenje odvjetnika prije slanja. Za slanje koristite
                                            sekciju "Odobrenje i slanje" i napravite obavezni pregled slanja.
                                        </p>
                                    </div>
                                @else
                                    <p class="text-sm" style="color: var(--muted, #94a3b8);">
                                        Dokument generiran. Pregledajte, uredite po potrebi, i podnesite rucno.
                                    </p>
                                @endif
                            </div>
                        @endif
                @endif
                {{-- Error State --}}
                @if($run->status === 'failed')
                    <div class="la-info-card mt-6" style="border-color: rgba(239,68,68,0.35);">
                        <div class="flex items-center gap-2 mb-2">
                            <span style="color: #ef4444; font-weight: 600;">Generacija neuspjela</span>
                        </div>
                        <p class="text-sm" style="color: var(--muted, #94a3b8);">{{ $run->stopped_reason ?? 'Nepoznata greska' }}</p>
                    </div>
                @endif
            @else
                <div class="la-pending-card">
                    <p style="color: var(--muted, #94a3b8);">Run nije pronadjen.</p>
                </div>
            @endif
        </div>
    </div>
</div>
