<div>
    <style>
        .la-new { min-height: 100vh; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); padding: 2rem 0; }
        .la-form-card { background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem; padding: 2rem; max-width: 640px; margin: 0 auto; }
        .la-form-label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--muted, #94a3b8); margin-bottom: 0.5rem; }
        .la-form-input { width: 100%; padding: 0.6rem 0.8rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: 0.6rem; font-size: 0.9rem; }
        .la-form-input:focus { outline: none; border-color: var(--accent, #38bdf8); box-shadow: 0 0 0 3px rgba(56,189,248,.12); }
        .la-form-input::placeholder { color: #475569; }
        .la-profile-card { background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); border-radius: 0.6rem; padding: 0.75rem; cursor: pointer; transition: all .15s; }
        .la-profile-card:hover { border-color: var(--accent, #38bdf8); }
        .la-profile-card.selected { border-color: var(--accent, #38bdf8); background: rgba(56,189,248,0.05); box-shadow: 0 0 0 2px rgba(56,189,248,.15); }
        .la-btn-fire { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem 1.5rem; background: linear-gradient(180deg, #ef4444, #dc2626); color: #fff; font-weight: 600; font-size: 0.9rem; border-radius: 0.5rem; border: 1px solid #b91c1c; cursor: pointer; transition: .2s; }
        .la-btn-fire:hover { filter: brightness(1.1); box-shadow: 0 6px 16px rgba(239,68,68,.3); }
        .la-btn-fire:disabled { opacity: 0.5; cursor: not-allowed; filter: none; }
        .la-btn-back { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: 0.85rem; border: 1px solid var(--border, #1f2937); border-radius: 0.5rem; cursor: pointer; transition: .2s; text-decoration: none; }
        .la-btn-back:hover { background: #131b2e; border-color: #2d3748; color: var(--fg, #e5e7eb); }
        .la-error-text { color: #fca5a5; font-size: 0.8rem; margin-top: 0.25rem; }
        .la-alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .la-checkbox-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--fg, #e5e7eb); cursor: pointer; }
        .la-checkbox { width: 1rem; height: 1rem; border-radius: 0.25rem; accent-color: var(--accent, #38bdf8); }
        .la-range-track { width: 100%; accent-color: var(--accent, #38bdf8); }
        .la-divider { border-top: 1px solid var(--border, #1f2937); margin: 1.5rem 0; }
        .la-scenario-card { background: rgba(15, 23, 42, 0.7); border: 1px solid var(--border, #1f2937); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; }
        .la-scenario-kicker { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted, #94a3b8); margin-bottom: 0.25rem; }
        .la-scenario-title { font-size: 1.1rem; font-weight: 600; color: var(--fg, #e5e7eb); margin-bottom: 0.35rem; }
        .la-scenario-summary { font-size: 0.85rem; color: var(--muted, #94a3b8); margin-bottom: 1rem; }
        .la-scenario-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .la-scenario-section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent, #38bdf8); margin-bottom: 0.5rem; }
        .la-scenario-item { background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); border-radius: 0.6rem; padding: 0.75rem; }
        .la-scenario-meta { font-size: 0.7rem; color: var(--muted, #94a3b8); }
        .la-scenario-text { font-size: 0.8rem; color: var(--fg, #e5e7eb); margin-top: 0.25rem; }
    </style>

    <div class="la-new">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Back Link --}}
            <div class="mb-4">
                <a href="{{ route('legal-artillery.dashboard') }}" class="la-btn-back">
                    &larr; Natrag na dashboard
                </a>
            </div>

            <div class="la-form-card">
                <h2 class="text-xl font-bold mb-1" style="color: var(--fg, #e5e7eb);">Nova Paljba</h2>
                <p class="mb-6" style="color: var(--muted, #94a3b8); font-size: 0.85rem;">Odaberi profil dokumenta i pokreni rekurzivno generiranje</p>

                @if(session('error'))
                    <div class="la-alert-error">{{ session('error') }}</div>
                @endif

                @if($scenarioData)
                    <div class="la-scenario-card">
                        <div class="la-scenario-kicker">Scenario</div>
                        <div class="la-scenario-title">{{ $scenarioData['title'] ?? 'Scenario' }}</div>
                        @if(!empty($scenarioData['summary']))
                            <div class="la-scenario-summary">{{ $scenarioData['summary'] }}</div>
                        @endif

                        <div class="la-scenario-grid">
                            <div>
                                <div class="la-scenario-section-title">Timeline</div>
                                <ol class="space-y-3">
                                    @forelse($scenarioData['timeline'] ?? [] as $event)
                                        <li class="la-scenario-item">
                                            <div class="la-scenario-meta">{{ $event['date'] ?? '' }}</div>
                                            <div class="la-scenario-text font-semibold">{{ $event['title'] ?? '' }}</div>
                                            @if(!empty($event['detail']))
                                                <div class="la-scenario-text">{{ $event['detail'] }}</div>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="la-scenario-item">
                                            <div class="la-scenario-text">Nema timeline zapisa.</div>
                                        </li>
                                    @endforelse
                                </ol>
                            </div>
                            <div>
                                <div class="la-scenario-section-title">Cinjenice</div>
                                <ul class="space-y-3">
                                    @forelse($scenarioData['facts'] ?? [] as $fact)
                                        <li class="la-scenario-item">
                                            <div class="la-scenario-text font-semibold">{{ $fact['label'] ?? '' }}</div>
                                            @if(!empty($fact['detail']))
                                                <div class="la-scenario-text">{{ $fact['detail'] }}</div>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="la-scenario-item">
                                            <div class="la-scenario-text">Nema dostupnih cinjenica.</div>
                                        </li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <form wire:submit="startGeneration" class="space-y-6">
                    @php
                        $requiresEscalationConfirmation = in_array($selectedProfile, $escalationProfiles, true);
                    @endphp
                    {{-- Profile Selection --}}
                    <div>
                        <label class="la-form-label">Profil dokumenta</label>
                        <div class="space-y-2">
                            @foreach($profiles as $profile)
                                <label class="la-profile-card block {{ $selectedProfile === $profile->key ? 'selected' : '' }}">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" wire:model.live="selectedProfile" value="{{ $profile->key }}"
                                               class="mt-1" style="accent-color: var(--accent, #38bdf8);">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm" style="color: var(--fg, #e5e7eb);">{{ $profile->name }}</div>
                                            <div class="text-xs mt-0.5" style="color: var(--muted, #94a3b8);">
                                                {{ $profile->recipient['institution'] ?? '' }}
                                                @if(!empty($profile->metadata['priority']))
                                                    <span style="color: var(--accent, #38bdf8);"> &middot; {{ $profile->metadata['priority'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedProfile') <span class="la-error-text">{{ $message }}</span> @enderror
                    </div>

                    {{-- Case Context --}}
                    <div>
                        <label class="la-form-label">Predmet (opcionalno)</label>
                        <select wire:model.live="caseId" class="la-form-input">
                            <option value="">-- Odaberi predmet --</option>
                            @foreach($cases as $case)
                                <option value="{{ $case->id }}">
                                    {{ $case->case_number ?? $case->id }} @if($case->title) - {{ $case->title }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('caseId') <span class="la-error-text">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="la-form-label">ID-evi dokaza (opcionalno)</label>
                        <input type="text" wire:model.live="evidenceIds" placeholder="npr. EV-123, EV-456"
                               class="la-form-input">
                        <p class="text-xs mt-1" style="color: var(--muted, #94a3b8);">Unesite vise ID-eva razdvojenih zarezom ili razmakom.</p>
                        @error('evidenceIds') <span class="la-error-text">{{ $message }}</span> @enderror
                    </div>

                    {{-- Max Iterations --}}
                    <div>
                        <label class="la-form-label">Max iteracija: <strong style="color: var(--accent, #38bdf8);">{{ $maxIterations }}</strong></label>
                        <input type="range" wire:model.live="maxIterations" min="1" max="10" step="1" class="la-range-track">
                        <div class="flex justify-between text-xs mt-1" style="color: var(--muted, #94a3b8);">
                            <span>1 (brzo)</span>
                            <span>5</span>
                            <span>10 (temeljito)</span>
                        </div>
                    </div>

                    @if($requiresEscalationConfirmation)
                        <div style="background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.3); padding: 0.85rem 1rem; border-radius: 0.6rem;">
                            <p class="text-sm" style="color: #fecaca; margin-bottom: 0.6rem;">
                                Odabrani profil spada u eskalacijske dopise. Generiranje je moguce samo uz eksplicitnu potvrdu.
                            </p>
                            <label class="la-checkbox-label">
                                <input type="checkbox" wire:model.live="confirmEscalation" class="la-checkbox">
                                Potvrdujem da zelim generirati eskalacijski dokument.
                            </label>
                            @error('confirmEscalation') <span class="la-error-text">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="la-divider"></div>

                    {{-- Email Options --}}
                    <div>
                        <p class="la-form-label" style="margin-bottom: 0.75rem;">Email opcije</p>

                        <label class="la-checkbox-label">
                            <input type="checkbox" wire:model.live="sendEmail" class="la-checkbox">
                            Posalji emailom nakon generacije
                        </label>

                        @if($sendEmail)
                            <div class="mt-4 ml-6 space-y-3" style="padding-left: 0.5rem; border-left: 2px solid var(--border, #1f2937);">
                                <label class="la-checkbox-label">
                                    <input type="checkbox" wire:model="asDraft" class="la-checkbox">
                                    Spremi kao draft (ne salji odmah)
                                </label>

                                <div>
                                    <label class="la-form-label">Alternativna email adresa</label>
                                    <input type="email" wire:model="toEmail" placeholder="Ostavi prazno za default"
                                           class="la-form-input">
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="la-divider"></div>

                    {{-- Submit --}}
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('legal-artillery.dashboard') }}" class="la-btn-back">Odustani</a>
                        <button type="submit" wire:loading.attr="disabled" class="la-btn-fire"
                                @disabled($requiresEscalationConfirmation && ! $confirmEscalation)>
                            <span wire:loading.remove>Pali!</span>
                            <span wire:loading>
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Generiram...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
