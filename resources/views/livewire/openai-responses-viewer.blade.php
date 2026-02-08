<div class="space-y-6" dusk="responses-viewer-container">
    <!-- Filter Panel -->
    <div class="rounded-xl p-6" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);" dusk="filter-panel">
        <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
            <div dusk="date-from-field">
                <label class="block text-sm font-medium" style="color: var(--muted, #94a3b8);" dusk="date-from-label">From</label>
                <input type="date" wire:model.debounce.500ms="from" class="mt-1 block w-full dt-input" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: .5rem; padding: .5rem .75rem;" dusk="date-from-input" />
            </div>
            <div dusk="date-to-field">
                <label class="block text-sm font-medium" style="color: var(--muted, #94a3b8);" dusk="date-to-label">To</label>
                <input type="date" wire:model.debounce.500ms="to" class="mt-1 block w-full dt-input" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: .5rem; padding: .5rem .75rem;" dusk="date-to-input" />
            </div>
            <div class="flex-1" dusk="search-field">
                <label class="block text-sm font-medium" style="color: var(--muted, #94a3b8);" dusk="search-label">Search</label>
                <input type="text" placeholder="filter by text/model/id" wire:model.debounce.400ms="search" class="mt-1 block w-full dt-input" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: .5rem; padding: .5rem .75rem;" dusk="search-input" />
            </div>
            <div dusk="limit-field">
                <label class="block text-sm font-medium" style="color: var(--muted, #94a3b8);" dusk="limit-label">Limit</label>
                <input type="number" min="1" max="100" wire:model.debounce.300ms="limit" class="mt-1 w-24 dt-input" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: .5rem; padding: .5rem .75rem;" dusk="limit-input" />
            </div>
            <div dusk="order-field">
                <label class="block text-sm font-medium" style="color: var(--muted, #94a3b8);" dusk="order-label">Order</label>
                <select wire:model="order" class="mt-1 w-28 dt-input" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: .5rem; padding: .5rem .75rem;" dusk="order-select">
                    <option value="desc" dusk="order-option-desc">Newest</option>
                    <option value="asc" dusk="order-option-asc">Oldest</option>
                </select>
            </div>
            <div class="flex items-center gap-2" dusk="credentials-field">
                {{-- Credentials button --}}
                <button
                    type="button"
                    wire:click="openCredentialsModal"
                    class="inline-flex items-center px-3 py-2 border shadow-sm text-sm leading-4 font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    style="background: var(--card, #111827); color: var(--fg, #e5e7eb); border-color: var(--border, #1f2937);"
                    title="Configure OpenAI Session Credentials"
                    dusk="credentials-btn"
                >
                    <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    Credentials
                </button>

                {{-- Show indicator if using session auth --}}
                @if(session('openai_session_token'))
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" dusk="session-auth-badge">
                        Session Auth Active
                    </span>
                    <button
                        type="button"
                        wire:click="clearCredentials"
                        wire:confirm="Are you sure you want to clear the stored credentials?"
                        class="ml-1 text-xs hover:text-red-400"
                        style="color: var(--muted, #94a3b8);"
                        title="Clear stored credentials"
                        dusk="clear-credentials-btn"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
            <div class="md:ml-auto" dusk="refresh-field">
                <button wire:click="refreshNow"
                        wire:loading.attr="disabled"
                        wire:target="refreshNow"
                        class="btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background: linear-gradient(180deg, var(--accent, #38bdf8), var(--accent-hover, #0ea5e9)); color: #fff;"
                        dusk="refresh-responses-btn">
                    <span wire:loading.remove wire:target="refreshNow" class="inline-flex items-center gap-2" dusk="refresh-button-idle">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" dusk="refresh-icon">
                            <path fill-rule="evenodd" d="M4 4a1 1 0 011-1h3a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V9a1 1 0 11-2 0V5a1 1 0 011-1zm12 12a1 1 0 01-1 1h-3a1 1 0 110-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L14 13.586V11a1 1 0 112 0v4z" clip-rule="evenodd"/>
                        </svg>
                        Refresh
                    </span>
                    <span wire:loading wire:target="refreshNow" class="inline-flex items-center gap-2" dusk="refresh-button-loading">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" dusk="refresh-spinner">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Refreshing...
                    </span>
                </button>
            </div>
        </div>
        @if($error)
            <div class="mt-3 text-sm flex items-center gap-2" style="color: #fca5a5;" dusk="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" dusk="error-icon">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $error }}</span>
                @if($needsCredentials)
                    <button
                        type="button"
                        wire:click="openCredentialsModal"
                        class="ml-2 text-sm font-medium underline hover:no-underline"
                        style="color: #fca5a5;"
                        dusk="enter-credentials-link"
                    >
                        Enter credentials →
                    </button>
                @endif
            </div>
        @endif

        <!-- Stats Bar -->
        <div class="mt-4 pt-4 flex items-center justify-between text-sm" style="border-top: 1px solid var(--border, #1f2937);" dusk="stats-bar">
            <div style="color: var(--muted, #94a3b8);" dusk="responses-count">
                <span class="font-semibold" style="color: var(--fg, #e5e7eb);">{{ count($items) }}</span>
                <span>{{ count($items) === 1 ? 'response' : 'responses' }}</span>
            </div>
            <div class="text-xs" style="color: var(--muted, #94a3b8);" dusk="last-updated">
                Last updated: <span dusk="last-updated-time">{{ now()->format('H:i:s') }}</span>
            </div>
        </div>
    </div>

    <!-- Responses Timeline with Loading Overlay -->
    <div class="relative" dusk="responses-timeline-wrapper">
        <!-- Loading Overlay -->
        <div wire:loading wire:target="refreshNow"
             class="absolute inset-0 backdrop-blur-sm z-20 flex items-center justify-center rounded-xl"
             style="background: rgba(11,18,32,0.85);"
             dusk="responses-loading-overlay">
            <div class="text-center rounded-xl p-8 shadow-2xl" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);" dusk="loading-overlay-content">
                <svg class="animate-spin h-12 w-12 mx-auto mb-4" style="color: var(--accent, #38bdf8);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" dusk="overlay-spinner">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="font-semibold text-lg" style="color: var(--fg, #e5e7eb);" dusk="loading-overlay-text">Loading responses...</p>
                <p class="text-sm mt-2" style="color: var(--muted, #94a3b8);" dusk="loading-overlay-subtext">Please wait while we fetch the data</p>
            </div>
        </div>

        <div class="relative" dusk="responses-timeline">
            <div class="absolute left-4 top-0 bottom-0 w-px bg-gradient-to-b from-sky-500 via-sky-300 to-transparent" dusk="timeline-line"></div>
            <div class="space-y-6" dusk="responses-list">
                @forelse($items as $i)
                    <div class="relative pl-12" dusk="response-item-{{ $i['id'] }}">
                        <div class="absolute left-0 top-2 h-3 w-3 rounded-full bg-gradient-to-br from-sky-400 to-sky-600 shadow-lg ring-2 ring-sky-900 animate-pulse" style="border: 2px solid var(--surface, #0f172a);" dusk="timeline-dot-{{ $i['id'] }}"></div>

                        <!-- Enhanced Response Card -->
                        <div class="rounded-xl shadow-md hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 overflow-hidden"
                             style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);"
                             dusk="response-card-{{ $i['id'] }}">

                            <!-- Card Header -->
                            <div class="flex items-center justify-between p-4"
                                 style="background: var(--surface, #0f172a); border-bottom: 1px solid var(--border, #1f2937);"
                                 dusk="response-header-{{ $i['id'] }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-2 text-sm" style="color: var(--muted, #94a3b8);" dusk="response-created-at-{{ $i['id'] }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="color: var(--muted, #94a3b8);" viewBox="0 0 20 20" fill="currentColor" dusk="calendar-icon-{{ $i['id'] }}">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ $i['created_at'] ?? '—' }}</span>
                                    </div>
                                    @if(!empty($i['model']))
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full shadow-sm"
                                              style="background: rgba(56,189,248,0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.3);"
                                              dusk="response-model-badge-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13 7H7v6h6V7z"/>
                                                <path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ $i['model'] }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2" dusk="response-actions-{{ $i['id'] }}">
                                    <!-- Copy Button -->
                                    <button wire:click="copyResponse({{ $i['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="copyResponse"
                                            class="p-2 rounded-lg transition-all duration-200 hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                                            style="color: var(--muted, #94a3b8);"
                                            title="Copy to clipboard"
                                            dusk="copy-response-{{ $i['id'] }}">
                                        <span wire:loading.remove wire:target="copyResponse" dusk="copy-icon-idle-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/>
                                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/>
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="copyResponse" dusk="copy-icon-loading-{{ $i['id'] }}">
                                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>

                                    <!-- View Full Response Button -->
                                    <button wire:click="viewFullResponse({{ $i['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="viewFullResponse"
                                            class="p-2 rounded-lg transition-all duration-200 hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                                            style="color: var(--muted, #94a3b8);"
                                            title="View full response"
                                            dusk="view-response-{{ $i['id'] }}">
                                        <span wire:loading.remove wire:target="viewFullResponse" dusk="view-icon-idle-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="viewFullResponse" dusk="view-icon-loading-{{ $i['id'] }}">
                                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button wire:click="deleteResponse({{ $i['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteResponse"
                                            wire:confirm="Are you sure you want to delete this response? This action cannot be undone."
                                            class="p-2 rounded-lg transition-all duration-200 hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                                            style="color: var(--muted, #94a3b8);"
                                            title="Delete response"
                                            dusk="delete-response-{{ $i['id'] }}">
                                        <span wire:loading.remove wire:target="deleteResponse" dusk="delete-icon-idle-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="deleteResponse" dusk="delete-icon-loading-{{ $i['id'] }}">
                                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-4" dusk="response-body-{{ $i['id'] }}">
                                <!-- User Input Section -->
                                <div class="mb-4" dusk="response-input-section-{{ $i['id'] }}">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="color: #6ee7b7;" viewBox="0 0 20 20" fill="currentColor" dusk="user-icon-{{ $i['id'] }}">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-xs font-bold uppercase tracking-wide" style="color: #6ee7b7;" dusk="input-label-{{ $i['id'] }}">User Input</span>
                                    </div>
                                    <div class="pl-6 whitespace-pre-wrap text-sm leading-relaxed rounded-lg p-3"
                                         style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.25); color: #a7f3d0;"
                                         dusk="response-input-text-{{ $i['id'] }}">{{ $i['input_text'] ?? '—' }}</div>
                                </div>

                                <!-- Assistant Output Section -->
                                <div dusk="response-output-section-{{ $i['id'] }}">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="color: #c4b5fd;" viewBox="0 0 20 20" fill="currentColor" dusk="assistant-icon-{{ $i['id'] }}">
                                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                                        </svg>
                                        <span class="text-xs font-bold uppercase tracking-wide" style="color: #c4b5fd;" dusk="output-label-{{ $i['id'] }}">AI Response</span>
                                    </div>
                                    <div class="pl-6 whitespace-pre-wrap text-sm leading-relaxed rounded-lg p-3 line-clamp-5 hover:line-clamp-none transition-all"
                                         style="background: rgba(168,85,247,0.08); border: 1px solid rgba(168,85,247,0.25); color: #d8b4fe;"
                                         dusk="response-output-text-{{ $i['id'] }}">{{ $i['output_text'] ?? '—' }}</div>
                                </div>

                                <!-- Images Section -->
                                @if(!empty($i['images']))
                                    <div class="mt-4 pt-4" style="border-top: 1px solid var(--border, #1f2937);" dusk="response-images-section-{{ $i['id'] }}">
                                        <div class="flex items-center gap-2 mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="color: #fbbf24;" viewBox="0 0 20 20" fill="currentColor" dusk="images-icon-{{ $i['id'] }}">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-xs font-bold uppercase tracking-wide" style="color: #fbbf24;" dusk="images-label-{{ $i['id'] }}">Images ({{ count($i['images']) }})</span>
                                        </div>
                                        <div class="flex flex-wrap gap-2" dusk="response-images-{{ $i['id'] }}">
                                            @foreach($i['images'] as $imgIndex => $url)
                                                <a href="{{ $url }}" target="_blank"
                                                   class="block group relative overflow-hidden rounded-lg transition-all duration-200 shadow-sm hover:shadow-lg"
                                                   style="border: 2px solid var(--border, #1f2937);"
                                                   dusk="response-image-link-{{ $i['id'] }}-{{ $imgIndex }}">
                                                    <img src="{{ $url }}"
                                                         class="h-24 w-24 object-cover group-hover:scale-110 transition-transform duration-300"
                                                         alt="Response image {{ $imgIndex + 1 }}"
                                                         loading="lazy"
                                                         dusk="response-image-{{ $i['id'] }}-{{ $imgIndex }}"/>
                                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-200"></div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Card Footer -->
                            <div class="px-4 py-3 flex items-center justify-between text-xs"
                                 style="background: var(--surface, #0f172a); border-top: 1px solid var(--border, #1f2937);"
                                 dusk="response-footer-{{ $i['id'] }}">
                                <div class="flex items-center gap-4">
                                    <!-- Response ID -->
                                    <div class="flex items-center gap-1" style="color: var(--muted, #94a3b8);" dusk="response-id-display-{{ $i['id'] }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" dusk="id-icon-{{ $i['id'] }}">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm3 1h6v4H7V5zm6 6H7v2h6v-2z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="font-mono">ID: {{ $i['id'] }}</span>
                                    </div>

                                    @if(!empty($i['tokens']))
                                        <div class="flex items-center gap-1" style="color: var(--muted, #94a3b8);" dusk="response-tokens-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" dusk="tokens-icon-{{ $i['id'] }}">
                                                <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
                                                <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
                                                <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
                                            </svg>
                                            <span dusk="response-tokens-value-{{ $i['id'] }}">{{ number_format($i['tokens']) }}</span>
                                            <span style="color: var(--muted, #94a3b8);">tokens</span>
                                        </div>
                                    @endif

                                    @if(!empty($i['cost']))
                                        <div class="flex items-center gap-1" style="color: var(--muted, #94a3b8);" dusk="response-cost-{{ $i['id'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" dusk="cost-icon-{{ $i['id'] }}">
                                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="font-semibold" style="color: #6ee7b7;" dusk="response-cost-value-{{ $i['id'] }}">${{ number_format($i['cost'], 4) }}</span>
                                        </div>
                                    @endif
                                </div>

                                <div style="color: var(--muted, #94a3b8);" dusk="response-status-{{ $i['id'] }}">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium" style="background: rgba(34,197,94,0.15); color: #86efac;" dusk="response-status-badge-{{ $i['id'] }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Complete
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16" dusk="no-responses">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4" style="background: var(--surface, #0f172a); border: 1px solid var(--border, #1f2937);" dusk="empty-state-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" style="color: var(--muted, #94a3b8);" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <p class="font-semibold text-lg mb-2" style="color: var(--fg, #e5e7eb);" dusk="no-responses-title">No responses found</p>
                        <p class="text-sm" style="color: var(--muted, #94a3b8);" dusk="no-responses-message">No responses in the selected range. Try adjusting your filters.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Global Loading Indicator (Toast) -->
    <div wire:loading wire:target="refreshNow,copyResponse,viewFullResponse,deleteResponse"
         class="fixed bottom-4 right-4 text-white text-sm px-4 py-3 rounded-lg shadow-2xl flex items-center gap-3 z-50"
         style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937);"
         dusk="loading-toast">
        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" dusk="toast-spinner">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span dusk="toast-message">Processing...</span>
    </div>

    {{-- Session Credentials Modal --}}
    @include('livewire.partials.openai-credentials-modal')
</div>
