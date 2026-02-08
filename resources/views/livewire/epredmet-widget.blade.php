<div class="w-full epredmet-widget" dusk="epredmet-widget" x-data="{ widgetOpen: false }" wire:init="initFetch">
    <div class="card" style="overflow:hidden;">
        <button type="button"
                class="w-full px-3 py-2"
                style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:.5rem; cursor:pointer; transition: background-color 0.2s;"
                @click="widgetOpen = !widgetOpen"
                :aria-expanded="widgetOpen.toString()"
                aria-controls="epredmet-widget-content"
                dusk="epredmet-widget-toggle"
                @mouseenter="$el.style.background = 'var(--hover, rgba(255,255,255,0.03))'"
                @mouseleave="$el.style.background = 'transparent'">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <div class="rounded-xl" style="background: rgba(20,184,166,.12); color:#2dd4bf; padding:.3rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zM4 11h10v2H4zM4 16h16v2H4z"/></svg>
                </div>
                <div style="text-align: left;">
                    <div class="font-semibold" style="font-size:.9rem; color: var(--fg)" dusk="widget-title">e&#x2011;Predmet &#x2013; GraphQL</div>
                    <div class="text-xs muted">Lookup court case and render GraphQL response</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:.75rem;">
                @if ($tookMs)
                    <div class="text-xs muted" dusk="request-duration">{{ $tookMs }} ms</div>
                @endif
                <svg class="h-5 w-5 transition-transform duration-300"
                     :class="widgetOpen ? 'rotate-180' : ''"
                     viewBox="0 0 20 20"
                     fill="currentColor"
                     style="color: var(--muted)"
                     aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            </div>
        </button>

        <div id="epredmet-widget-content"
             x-show="widgetOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             x-cloak>
            <div class="px-3 py-2">
                {{-- Tab Navigation --}}
                <div class="flex gap-1 mb-3 border-b border-[var(--border)]" role="tablist">
                    <button type="button"
                            wire:click="$set('activeTab', 'lookup')"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'lookup' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'lookup' ? 'true' : 'false' }}"
                            dusk="tab-lookup">
                        Case Lookup
                    </button>
                    <button type="button"
                            wire:click="switchToSyncStatus"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'sync-status' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'sync-status' ? 'true' : 'false' }}"
                            dusk="tab-sync-status">
                        Sync Status
                        @if(collect($syncLogs)->where('status', 'running')->count() > 0)
                            <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        @endif
                    </button>
                    <button type="button"
                            wire:click="switchToBatchFetch"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'batch-fetch' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'batch-fetch' ? 'true' : 'false' }}"
                            dusk="tab-batch-fetch">
                        Batch Fetch
                    </button>
                    <button type="button"
                            wire:click="switchToAnalytics"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'analytics' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'analytics' ? 'true' : 'false' }}"
                            dusk="tab-analytics">
                        Analytics
                    </button>
                </div>

                {{-- Tab: Case Lookup --}}
                @if($activeTab === 'lookup')
                    <div x-data>
                        @include('livewire.partials.epredmet-lookup')
                    </div>
                @endif

                {{-- Tab: Sync Status --}}
                @if($activeTab === 'sync-status')
                    <div x-data>
                        @include('livewire.partials.epredmet-sync-status')
                    </div>
                @endif

                {{-- Tab: Batch Fetch --}}
                @if($activeTab === 'batch-fetch')
                    <div x-data>
                        @include('livewire.partials.epredmet-batch-fetch')
                    </div>
                @endif

                {{-- Tab: Analytics --}}
                @if($activeTab === 'analytics')
                    <div x-data>
                        @include('livewire.partials.epredmet-analytics')
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .epredmet-widget .epw-input { padding:.4rem .65rem; font-size:.85rem; border-radius:.45rem; }
        .epredmet-widget .epw-btn { padding:.45rem .7rem; font-size:.8rem; display:inline-flex; align-items:center; gap:.25rem; transition: all 0.15s ease; }
        .epredmet-widget .epw-btn:disabled { opacity:.55; cursor:not-allowed; }
        .epredmet-widget .epw-btn:not(:disabled):active { transform: scale(0.97); }
        .epredmet-widget .epw-card { padding:.55rem; }
        .epredmet-widget .epw-title { font-size:.92rem; color: var(--fg); }
        .epredmet-widget .epw-text { font-size:.85rem; color: var(--fg); }
        .epredmet-widget .epw-table th, .epredmet-widget .epw-table td { font-size:.85rem; }
        .epredmet-widget .epw-spinner { display:inline-block; width:1rem; height:1rem; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; animation:epw-spin .6s linear infinite; }
        @keyframes epw-spin { to { transform: rotate(360deg); } }
        .epredmet-widget .epw-progress { height:.375rem; border-radius:9999px; background:rgba(255,255,255,0.08); overflow:hidden; }
        .epredmet-widget .epw-progress-bar { height:100%; border-radius:9999px; transition: width 0.5s ease; }
        .epredmet-widget .epw-highlight-info { background:rgba(59,130,246,0.1); border-left:3px solid #3b82f6; }
        .epredmet-widget .epw-highlight-warning { background:rgba(234,179,8,0.1); border-left:3px solid #eab308; }
        .epredmet-widget .epw-highlight-danger { background:rgba(239,68,68,0.1); border-left:3px solid #ef4444; }
        .epredmet-widget .epw-badge { display:inline-flex; align-items:center; padding:.1rem .4rem; border-radius:.25rem; font-size:.7rem; font-weight:600; }
        .epredmet-widget .epw-row-highlight { background:rgba(234,179,8,0.08); }
    </style>
</div>
