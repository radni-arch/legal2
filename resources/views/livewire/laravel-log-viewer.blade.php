<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
    {{-- Custom scrollbar styling for dark theme --}}
    <style>
        .log-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .log-scroll::-webkit-scrollbar-track { background: rgb(30 41 59 / 0.5); border-radius: 3px; }
        .log-scroll::-webkit-scrollbar-thumb { background: rgb(71 85 105); border-radius: 3px; }
        .log-scroll::-webkit-scrollbar-thumb:hover { background: rgb(100 116 139); }
        .log-scroll { scrollbar-width: thin; scrollbar-color: rgb(71 85 105) rgb(30 41 59 / 0.5); }
        /* Prevent horizontal overflow on expanded log entries */
        [x-cloak] { display: none !important; }
    </style>
    <div class="container mx-auto max-w-7xl p-6">
        {{-- Unified Header --}}
        <x-page-header
            title="Laravel Log Viewer"
            subtitle="Monitor and analyze application logs from storage/logs"
            route-name="logs.viewer"
        />

        <div class="grid grid-cols-12 gap-6">
            {{-- Sidebar - Log Files List --}}
            <div class="col-span-12 lg:col-span-3">
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-lg shadow-xl">
                    <div class="p-4 border-b border-slate-700">
                        <h2 class="text-lg font-semibold text-white">Log Files</h2>
                        <p class="text-xs text-slate-400 mt-1" dusk="log-files-count">{{ count($logFiles) }} file(s)</p>
                    </div>
                    <div class="p-2 max-h-[600px] overflow-y-auto log-scroll" dusk="log-files-list">
                        @forelse($logFiles as $filename => $file)
                            <div class="mb-2">
                                <button
                                    type="button"
                                    wire:click="selectFile('{{ $filename }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="selectFile('{{ $filename }}')"
                                    dusk="select-log-{{ $filename }}"
                                    class="w-full text-left px-3 py-2 rounded transition-all {{ $selectedFile === $filename ? 'bg-blue-600 text-white' : 'bg-slate-700/50 text-slate-300 hover:bg-slate-700' }}"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0" wire:loading.remove wire:target="selectFile('{{ $filename }}')">
                                            <div class="text-sm font-medium truncate" title="{{ $filename }}">
                                                {{ $filename }}
                                            </div>
                                            <div class="text-xs opacity-75 mt-1">
                                                {{ $this->logService->formatSize($file['size']) }}
                                            </div>
                                            <div class="text-xs opacity-60">
                                                {{ date('Y-m-d H:i', $file['modified']) }}
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0 flex items-center gap-2" wire:loading wire:target="selectFile('{{ $filename }}')">
                                            <svg class="animate-spin h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-sm">Loading...</span>
                                        </div>
                                        @if($selectedFile === $filename)
                                            <svg class="w-5 h-5 ml-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" wire:loading.remove wire:target="selectFile('{{ $filename }}')">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </div>
                                </button>
                                @if($selectedFile === $filename)
                                    <div class="flex gap-1 mt-1 px-1">
                                        <button
                                            wire:click="downloadFile('{{ $filename }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="downloadFile('{{ $filename }}')"
                                            dusk="download-log-{{ $filename }}"
                                            class="flex-1 text-xs px-2 py-1 bg-green-600/20 text-green-400 border border-green-600/30 rounded hover:bg-green-600/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span wire:loading.remove wire:target="downloadFile('{{ $filename }}')">
                                                <svg class="inline h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                Download
                                            </span>
                                            <span wire:loading wire:target="downloadFile('{{ $filename }}')" class="flex items-center justify-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Downloading...
                                            </span>
                                        </button>
                                        <button
                                            wire:click="deleteFile('{{ $filename }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteFile('{{ $filename }}')"
                                            wire:confirm="Are you sure you want to delete {{ $filename }}?"
                                            dusk="delete-log-{{ $filename }}"
                                            class="flex-1 text-xs px-2 py-1 bg-red-600/20 text-red-400 border border-red-600/30 rounded hover:bg-red-600/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span wire:loading.remove wire:target="deleteFile('{{ $filename }}')">
                                                <svg class="inline h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </span>
                                            <span wire:loading wire:target="deleteFile('{{ $filename }}')" class="flex items-center justify-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Deleting...
                                            </span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-8 text-slate-500" dusk="no-log-files">
                                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm">No log files found</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Main Content - Log Viewer --}}
            <div class="col-span-12 lg:col-span-9">
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-lg shadow-xl">
                    {{-- Filters --}}
                    <div class="p-4 border-b border-slate-700" dusk="filters-toolbar">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-300">Per Page:</label>
                                <select class="bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm text-white focus:ring-2 focus:ring-blue-500" wire:model.live="perPage" dusk="per-page-select">
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-300">Level:</label>
                                <select class="bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm text-white focus:ring-2 focus:ring-blue-500" wire:model.live="levelFilter" dusk="level-filter-select">
                                    <option value="all">All</option>
                                    <option value="debug">Debug</option>
                                    <option value="info">Info</option>
                                    <option value="notice">Notice</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                    <option value="critical">Critical</option>
                                    <option value="alert">Alert</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                                <label class="text-sm text-slate-300">Search:</label>
                                <input
                                    type="text"
                                    class="flex-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500"
                                    placeholder="Search in logs..."
                                    wire:model.debounce.500ms="search"
                                    dusk="search-input"
                                />
                            </div>

                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                    <input type="checkbox" wire:model="autoRefresh" class="rounded bg-slate-700 border-slate-600 text-blue-600 focus:ring-2 focus:ring-blue-500" dusk="auto-refresh-checkbox" />
                                    Auto refresh
                                </label>
                            </div>

                            <button
                                type="button"
                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                wire:click="refreshNow"
                                wire:loading.attr="disabled"
                                wire:target="refreshNow"
                                dusk="refresh-logs-btn"
                            >
                                <span wire:loading.remove wire:target="refreshNow" class="flex items-center gap-1">
                                    <svg class="inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Refresh
                                </span>
                                <span wire:loading wire:target="refreshNow" class="flex items-center gap-1">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Refreshing...
                                </span>
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                wire:click="clearFilters"
                                wire:loading.attr="disabled"
                                wire:target="clearFilters"
                                dusk="clear-filters-btn"
                            >
                                <span wire:loading.remove wire:target="clearFilters" class="flex items-center gap-1">
                                    <svg class="inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear
                                </span>
                                <span wire:loading wire:target="clearFilters" class="flex items-center gap-1">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Clearing...
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Pagination Controls --}}
                    @if($selectedFile && !empty($entries))
                        <div class="flex items-center justify-between px-4 py-2 border-b border-slate-700" dusk="pagination-controls">
                            <div class="flex items-center gap-1" wire:loading.class="opacity-50 pointer-events-none" wire:target="gotoPage, nextPage, previousPage">
                                <button wire:click="previousPage" @disabled($currentPage <= 1)
                                    class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                    Previous
                                </button>

                                @php
                                    $start = max(1, $currentPage - 2);
                                    $end = min($totalPages, $currentPage + 2);
                                @endphp
                                @for($p = $start; $p <= $end; $p++)
                                    <button wire:click="gotoPage({{ $p }})"
                                        class="px-3 py-1.5 text-sm rounded transition-colors {{ $p === $currentPage ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                                        {{ $p }}
                                    </button>
                                @endfor

                                <button wire:click="nextPage" @disabled($currentPage >= $totalPages)
                                    class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                    Next
                                </button>
                            </div>
                            <span class="text-sm text-slate-400">
                                Page {{ $currentPage }} of {{ $totalPages }} ({{ number_format($totalLines) }} lines total)
                                <div wire:loading wire:target="gotoPage, nextPage, previousPage" class="inline-flex items-center ml-2">
                                    <svg class="animate-spin h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </span>
                        </div>
                    @endif

                    {{-- Log Entries --}}
                    <div class="p-4 relative" @if($autoRefresh) wire:poll.5s="refreshNow" @endif dusk="log-content">
                        {{-- Loading Overlay --}}
                        <div wire:loading wire:target="selectFile, gotoPage, nextPage, previousPage, refreshNow, perPage, levelFilter" class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg">
                            <div class="text-center">
                                <svg class="animate-spin h-12 w-12 text-blue-400 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-gray-300 font-medium text-lg">Loading log file...</p>
                                <p class="text-gray-400 text-sm mt-2">Please wait while we fetch the log entries</p>
                            </div>
                        </div>

                        @if(!$selectedFile)
                            <div class="text-center py-12 text-slate-400" dusk="no-file-selected">
                                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-lg">Select a log file to view</p>
                            </div>
                        @elseif(empty($entries))
                            <div class="text-center py-12 text-slate-400" dusk="no-entries-found">
                                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="text-lg">No log entries found</p>
                                <p class="text-sm mt-2">Try adjusting your filters or the log file is empty</p>
                            </div>
                        @else
                            @php
                                $levelColors = [
                                    'debug' => 'bg-slate-600 text-slate-200',
                                    'info' => 'bg-blue-600 text-blue-100',
                                    'notice' => 'bg-cyan-600 text-cyan-100',
                                    'warning' => 'bg-yellow-600 text-yellow-100',
                                    'error' => 'bg-red-600 text-red-100',
                                    'critical' => 'bg-red-700 text-red-100',
                                    'alert' => 'bg-orange-600 text-orange-100',
                                    'emergency' => 'bg-purple-600 text-purple-100',
                                ];
                            @endphp
                            <div class="flex gap-2 mb-2" x-data>
                                <button @click="$dispatch('expand-all')" class="text-xs text-slate-400 hover:text-white transition-colors">
                                    Expand All
                                </button>
                                <span class="text-slate-600">|</span>
                                <button @click="$dispatch('collapse-all')" class="text-xs text-slate-400 hover:text-white transition-colors">
                                    Collapse All
                                </button>
                            </div>
                            <div class="space-y-1 max-h-[calc(100vh-320px)] overflow-y-auto log-scroll" dusk="log-entries-list">
                                @foreach($entries as $i => $entry)
                                    @php
                                        $level = strtolower($entry['level']);
                                        $badgeClass = $levelColors[$level] ?? 'bg-slate-600 text-slate-200';
                                        $hasStackTrace = !empty($entry['stack_trace']);
                                    @endphp
                                    <div x-data="{ expanded: false }"
                                         @expand-all.window="expanded = true"
                                         @collapse-all.window="expanded = false"
                                         class="border border-slate-600/30 rounded bg-slate-700/30 hover:border-slate-500/50 transition-colors"
                                         dusk="log-entry-{{ $i }}"
                                         data-log-level="{{ $entry['level'] }}">

                                        {{-- Collapsed Header (always visible, clickable) --}}
                                        <div @click="expanded = !expanded"
                                             class="flex items-center gap-2 px-3 py-1.5 cursor-pointer select-none">

                                            {{-- Expand/Collapse Icon --}}
                                            <svg :class="{ 'rotate-90': expanded }"
                                                 class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 flex-shrink-0"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>

                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold leading-none {{ $badgeClass }} shrink-0" dusk="log-level-badge">{{ strtoupper($entry['level']) }}</span>

                                            @if($entry['parsed'])
                                                @if(isset($entry['parsed']['datetime']))
                                                    <span class="text-[11px] text-slate-500 shrink-0 font-mono" dusk="log-datetime">{{ is_array($entry['parsed']['datetime']) ? ($entry['parsed']['datetime']['date'] ?? '') : $entry['parsed']['datetime'] }}</span>
                                                @endif
                                                @if(isset($entry['parsed']['environment']))
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-600/40 text-slate-400 shrink-0" dusk="log-environment">{{ $entry['parsed']['environment'] }}</span>
                                                @endif
                                                <span class="text-xs text-slate-300 font-mono truncate min-w-0" dusk="log-message">{{ Str::limit($entry['parsed']['message'] ?? '', 120) }}</span>
                                            @else
                                                <span class="text-xs text-slate-300 font-mono truncate min-w-0">{{ Str::limit($entry['raw'], 120) }}</span>
                                            @endif
                                        </div>

                                        {{-- Expanded Content --}}
                                        <div x-show="expanded"
                                             x-collapse
                                             x-cloak
                                             class="px-3 pb-3 pt-0 border-t border-slate-600/20">

                                            {{-- Full Message --}}
                                            <div class="bg-slate-900/70 rounded p-3 mt-2 overflow-x-auto">
                                                <pre class="text-sm text-slate-200 whitespace-pre-wrap break-words font-mono">{{ $entry['parsed']['message'] ?? $entry['raw'] }}</pre>
                                            </div>

                                            {{-- Stack Trace (if present) --}}
                                            @if($hasStackTrace)
                                            <details class="mt-2 group" dusk="log-stack-{{ $i }}">
                                                <summary class="text-[10px] text-slate-500 cursor-pointer hover:text-slate-400 list-none [&::-webkit-details-marker]:hidden flex items-center gap-1">
                                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                    Stack Trace
                                                </summary>
                                                <div class="bg-slate-950/70 rounded p-3 mt-1 overflow-x-auto max-h-64 overflow-y-auto log-scroll">
                                                    <pre class="text-xs text-red-300/80 whitespace-pre-wrap break-words font-mono">{{ $entry['stack_trace'] }}</pre>
                                                </div>
                                            </details>
                                            @endif

                                            {{-- Raw Entry (collapsible) --}}
                                            @if($entry['truncated'] || $entry['raw'] !== ($entry['parsed']['message'] ?? ''))
                                            <details class="mt-2 group">
                                                <summary class="text-[10px] text-slate-500 cursor-pointer hover:text-slate-400 list-none [&::-webkit-details-marker]:hidden flex items-center gap-1">
                                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                    Raw Entry @if($entry['truncated'])(truncated)@endif
                                                </summary>
                                                <div class="bg-slate-950/70 rounded p-3 mt-1 overflow-x-auto max-h-48 overflow-y-auto log-scroll">
                                                    <pre class="text-xs text-slate-400 whitespace-pre-wrap break-words font-mono">{{ $entry['raw'] }}</pre>
                                                </div>
                                            </details>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 text-center text-xs text-slate-500" dusk="entries-count">
                                Showing {{ count($entries) }} entries — Page {{ $currentPage }} of {{ $totalPages }}
                            </div>
                        @endif
                    </div>

                    {{-- Bottom Pagination Controls --}}
                    @if($selectedFile && !empty($entries))
                        <div class="flex items-center justify-between px-4 py-2 border-t border-slate-700" dusk="pagination-controls-bottom">
                            <div class="flex items-center gap-1" wire:loading.class="opacity-50 pointer-events-none" wire:target="gotoPage, nextPage, previousPage">
                                <button wire:click="previousPage" @disabled($currentPage <= 1)
                                    class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                    Previous
                                </button>

                                @php
                                    $start = max(1, $currentPage - 2);
                                    $end = min($totalPages, $currentPage + 2);
                                @endphp
                                @for($p = $start; $p <= $end; $p++)
                                    <button wire:click="gotoPage({{ $p }})"
                                        class="px-3 py-1.5 text-sm rounded transition-colors {{ $p === $currentPage ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                                        {{ $p }}
                                    </button>
                                @endfor

                                <button wire:click="nextPage" @disabled($currentPage >= $totalPages)
                                    class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                    Next
                                </button>
                            </div>
                            <span class="text-sm text-slate-400">
                                Page {{ $currentPage }} of {{ $totalPages }} ({{ number_format($totalLines) }} lines total)
                                <div wire:loading wire:target="gotoPage, nextPage, previousPage" class="inline-flex items-center ml-2">
                                    <svg class="animate-spin h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
