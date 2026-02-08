<div class="container mx-auto max-w-5xl p-4" dusk="transcript-previewer-container">
    {{-- Unified Header --}}
    <x-page-header
        title="Transcript Previewer"
        subtitle="Preview and analyze transcript documents"
        route-name="transcript"
    />

    <!-- Main Controls Section -->
    <div class="controls bg-gradient-to-br from-slate-800 to-slate-900" dusk="main-controls">
        <div class="ctrl" dusk="file-path-control">
            <label dusk="file-path-label">Transcript path</label>
            <div class="relative">
                <input type="text" class="in" placeholder="storage/iznedjenaIzjava.txt"
                       dusk="file-path-input" wire:model.lazy="filePath"
                       wire:loading.attr="disabled"
                       wire:target="filePath" />
                <div wire:loading wire:target="filePath" class="absolute right-2 top-1/2 transform -translate-y-1/2" dusk="file-path-loading">
                    <svg class="animate-spin h-4 w-4 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="ctrl" dusk="search-control">
            <label dusk="search-label">Search</label>
            <div class="relative">
                <input type="text" class="in small" placeholder="speaker, phrase…"
                       dusk="search-input" wire:model.debounce.400ms="search" />
                <div wire:loading wire:target="search" class="absolute right-2 top-1/2 transform -translate-y-1/2" dusk="search-loading">
                    <svg class="animate-spin h-3 w-3 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <span class="switch" title="Show/hide timestamps" dusk="timestamps-toggle-container">
            <input type="checkbox" id="tsToggle" dusk="timestamps-toggle" wire:model="showTimestamps" />
            <label for="tsToggle" dusk="timestamps-toggle-label">Timestamps</label>
        </span>
        <span class="switch" title="Auto refresh every 5s" dusk="auto-refresh-toggle-container">
            <input type="checkbox" id="arToggle" dusk="auto-refresh-toggle" wire:model="autoRefresh" />
            <label for="arToggle" dusk="auto-refresh-toggle-label">Auto refresh</label>
        </span>

        <button type="button" class="btn hover:scale-105 transition-transform duration-200"
                dusk="refresh-button"
                wire:click="refreshNow"
                wire:loading.attr="disabled"
                wire:target="refreshNow">
            <span wire:loading.remove wire:target="refreshNow">Refresh</span>
            <span wire:loading wire:target="refreshNow" class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Refreshing...
            </span>
        </button>
        <button type="button" class="btn hover:scale-105 transition-transform duration-200"
                dusk="clear-search-button"
                wire:click="$set('search','')"
                wire:loading.attr="disabled"
                wire:target="$set('search','')">
            <span wire:loading.remove wire:target="$set('search','')">Clear</span>
            <span wire:loading wire:target="$set('search','')" class="inline-flex items-center">Clearing...</span>
        </button>

        <button type="button" class="btn bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 hover:scale-105 transition-all duration-200"
                dusk="export-button"
                wire:click="exportTranscript"
                wire:loading.attr="disabled"
                wire:target="exportTranscript">
            <span wire:loading.remove wire:target="exportTranscript" class="inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export
            </span>
            <span wire:loading wire:target="exportTranscript" class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Exporting...
            </span>
        </button>

        <span class="chip" title="Base start datetime" dusk="base-start-chip">🕒 Base: {{ $baseStart }}</span>
        <span class="chip" title="Timezone" dusk="timezone-chip">🌍 {{ $timezone }}</span>
        <span class="chip" title="Source path" dusk="file-path-chip">📄 {{ \Illuminate\Support\Str::limit($filePath, 48) }}</span>
    </div>

    <!-- Lingua/Forensic Analysis Controls -->
    <div class="controls bg-gradient-to-br from-slate-800 to-slate-900" style="margin-top:6px" dusk="lingua-controls">
        <div class="ctrl" dusk="lingua-path-control">
            <label dusk="lingua-path-label">Lingua analysis path</label>
            <div class="relative">
                <input type="text" class="in small" placeholder="storage/lingua.txt"
                       dusk="lingua-path-input" wire:model.lazy="linguaPath"
                       wire:loading.attr="disabled"
                       wire:target="linguaPath" />
                <div wire:loading wire:target="linguaPath" class="absolute right-2 top-1/2 transform -translate-y-1/2" dusk="lingua-path-loading">
                    <svg class="animate-spin h-3 w-3 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <span class="switch" title="Show/hide forensics panel" dusk="lingua-toggle-container">
            <input type="checkbox" id="lgToggle" dusk="lingua-toggle" wire:model="showLingua" />
            <label for="lgToggle" dusk="lingua-toggle-label">Forensics</label>
        </span>
        @if(!empty($linguaEvents))
            <span class="chip bg-gradient-to-r from-purple-600 to-purple-700" title="Detected events" dusk="lingua-events-count">🔎 {{ count($linguaEvents) }} events</span>
        @endif
    </div>

    <!-- Forensic Analysis Panel -->
    @if($showLingua)
        <div class="seg bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-sm relative" style="margin:10px 0 14px;" dusk="forensic-panel">
            <!-- Loading Overlay for Forensic Panel -->
            <div wire:loading wire:target="loadLingua,linguaPath,refreshNow" class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm rounded-lg flex items-center justify-center z-10" dusk="forensic-loading-overlay">
                <div class="flex flex-col items-center space-y-3">
                    <svg class="animate-spin h-8 w-8 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sky-400 text-sm font-medium">Loading forensic analysis...</span>
                </div>
            </div>

            <div class="head" style="margin-bottom:10px" dusk="lingua-panel-header">
                <span class="chip bg-gradient-to-r from-purple-600 to-purple-700" dusk="forensic-summary-chip">🧠 Forensic summary</span>
                @if(!empty($durationSec))
                    <span class="chip bg-gradient-to-r from-blue-600 to-blue-700" title="Transcript duration" dusk="duration-chip">⏱️ {{ gmdate('H:i:s', max(0,$durationSec)) }}</span>
                @endif
            </div>
            @if(!empty($linguaSummary))
                <div class="txt" style="margin-bottom:10px; white-space:pre-line;" dusk="lingua-summary-text">{{ $linguaSummary }}</div>
            @endif

            @if(!empty($linguaEvents))
                <div class="txt" style="margin:6px 0 8px; font-size:13px; color:#cbd5e1" dusk="timeline-label">
                    <span class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Timeline Visualization
                    </span>
                </div>
                <div class="timeline-scrubber" style="position:relative; height:32px; background:linear-gradient(to right, #0b1220, #1e293b, #0b1220); border:2px solid var(--border); border-radius:999px; display:flex; align-items:center; padding:0 12px; overflow:hidden; box-shadow: inset 0 2px 8px rgba(0,0,0,0.5)"
                     dusk="timeline-scrubber">
                    <div style="position:absolute; left:0; right:0; height:3px; background:linear-gradient(to right, #334155, #475569, #334155); top:50%; transform:translateY(-50%); box-shadow: 0 0 8px rgba(59, 130, 246, 0.3)"></div>
                    @php $dur = max(1, $durationSec); @endphp
                    @foreach($linguaEvents as $index => $ev)
                        @php $pct = min(100, max(0, round(($ev['seconds'] / $dur) * 100, 2))); @endphp
                        @php
                            $segId = method_exists($this, 'segmentIdForSeconds') ? $this->segmentIdForSeconds((int)$ev['seconds']) : ('seg-' . str_pad((string)$ev['seconds'], 6, '0', STR_PAD_LEFT));
                        @endphp
                        <a href="#{{ $segId }}" title="{{ $ev['time'] }} • {{ $ev['title'] }}"
                           dusk="timeline-event-{{ $index }}"
                           class="timeline-marker group"
                           style="position:absolute; left:{{ $pct }}%; transform:translateX(-50%); text-decoration:none; z-index:2;">
                            <span class="chip transition-all duration-200 hover:scale-125 hover:shadow-lg hover:shadow-sky-500/50 cursor-pointer"
                                  style="padding:3px 8px; font-size:10px; background:linear-gradient(135deg, #0ea5e9, #06b6d4); color:#fff; border:2px solid #0284c7; font-weight:600; box-shadow: 0 2px 4px rgba(0,0,0,0.3)">
                                {{ $ev['time'] }}
                            </span>
                        </a>
                    @endforeach
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px" dusk="lingua-events-list">
                    @foreach($linguaEvents as $index => $ev)
                        @php
                            $segId = method_exists($this, 'segmentIdForSeconds') ? $this->segmentIdForSeconds((int)$ev['seconds']) : ('seg-' . str_pad((string)$ev['seconds'], 6, '0', STR_PAD_LEFT));
                        @endphp
                        <a href="#{{ $segId }}"
                           class="chip transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-purple-500/30 cursor-pointer bg-gradient-to-r from-slate-700 to-slate-800 hover:from-purple-600 hover:to-purple-700"
                           style="text-decoration:none"
                           dusk="lingua-event-card-{{ $index }}"
                           title="Jump to {{ $ev['time'] }}">
                            <strong style="margin-right:6px">{{ $ev['time'] }}</strong>
                            <span>{{ $ev['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="chip" dusk="no-events-message">No timestamped events found in lingua.txt</div>
            @endif
        </div>
    @endif

    <!-- Speaker Filter Controls -->
    @if(!empty($speakers))
        <div class="controls bg-gradient-to-br from-slate-800 to-slate-900" style="margin-top: 6px" dusk="speakers-controls">
            <div class="ctrl" style="gap:8px" dusk="speakers-control">
                <label dusk="speakers-label">Speakers</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px" dusk="speakers-list">
                    @foreach(array_keys($speakers) as $sp)
                        <label class="chip transition-all duration-200 hover:scale-105 hover:bg-slate-600 cursor-pointer" dusk="speaker-{{ $sp }}-checkbox-label">
                            <input type="checkbox" class="accent-sky-500" dusk="speaker-{{ $sp }}-checkbox"
                                   wire:model.live="speakers.{{ $sp }}" />
                            <span class="font-medium" dusk="speaker-{{ $sp }}-name">{{ $sp }}</span>
                        </label>
                    @endforeach
                    <button type="button" class="btn hover:scale-105 transition-transform duration-200"
                            dusk="show-all-speakers-button"
                            wire:click="allSpeakers(true)"
                            wire:loading.attr="disabled"
                            wire:target="allSpeakers">
                        <span wire:loading.remove wire:target="allSpeakers(true)">All</span>
                        <span wire:loading wire:target="allSpeakers(true)">Loading...</span>
                    </button>
                    <button type="button" class="btn hover:scale-105 transition-transform duration-200"
                            dusk="hide-all-speakers-button"
                            wire:click="allSpeakers(false)"
                            wire:loading.attr="disabled"
                            wire:target="allSpeakers">
                        <span wire:loading.remove wire:target="allSpeakers(false)">None</span>
                        <span wire:loading wire:target="allSpeakers(false)">Loading...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Transcript Display Area -->
    <div @if($autoRefresh) wire:poll.5s="refreshNow" @endif class="relative" dusk="transcript-display">
        <!-- Loading Overlay for Segment List -->
        <div wire:loading wire:target="refreshNow,filePath,search,speakers,allSpeakers" class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm rounded-lg flex items-center justify-center z-20 min-h-[200px]" dusk="segments-loading-overlay">
            <div class="flex flex-col items-center space-y-3">
                <svg class="animate-spin h-10 w-10 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sky-400 text-sm font-medium">Loading transcript segments...</span>
            </div>
        </div>

        @php $items = $this->filtered; @endphp
        @if(empty($items))
            <div class="chip bg-gradient-to-r from-amber-600 to-amber-700" dusk="no-segments-message">No transcript segments to display. Adjust the path, or check that the file exists.</div>
        @else
            <ul class="seg-list" dusk="segment-list">
                @foreach($items as $index => $seg)
                    <li class="seg bg-gradient-to-br from-slate-800/30 to-slate-900/30 backdrop-blur-sm hover:from-slate-700/40 hover:to-slate-800/40 transition-all duration-300 hover:shadow-lg hover:shadow-sky-500/10 scroll-mt-20"
                        id="{{ $seg['id'] ?? '' }}"
                        dusk="segment-{{ $index }}">
                        <div class="head" dusk="segment-{{ $index }}-header">
                            @if($showTimestamps)
                                <span class="chip bg-gradient-to-r from-blue-600 to-blue-700" title="Segment timecode" dusk="segment-{{ $index }}-timecode">⏱️ {{ $seg['time'] }}</span>
                            @endif
                            <span class="chip bg-gradient-to-r from-emerald-600 to-emerald-700" title="Speaker" dusk="segment-{{ $index }}-speaker">🎤 {{ $seg['speaker'] }}</span>
                            @if(($seg['has_time'] ?? false) && !empty($seg['abs']))
                                <span class="chip bg-gradient-to-r from-violet-600 to-violet-700" title="Absolute date-time based on base start" dusk="segment-{{ $index }}-datetime">🗓️ {{ $seg['abs'] }}</span>
                            @endif
                        </div>
                        <div class="txt" dusk="segment-{{ $index }}-text">
                            @php
                                $text = $seg['text'] ?? '';
                                $html = e($text);
                                $q = trim($this->search);
                                if ($q !== '') {
                                    $pattern = '/(' . preg_quote($q, '/') . ')/iu';
                                    $replaced = @preg_replace($pattern, '<mark>$1</mark>', $html);
                                    if ($replaced !== null) { $html = $replaced; }
                                }
                            @endphp
                            {!! clean(nl2br($html)) !!}
                        </div>

                        @if($showLingua && ($seg['has_time'] ?? false))
                            @php $near = $this->eventsNear((int)($seg['seconds'] ?? 0)); @endphp
                            @if(!empty($near))
                                <div class="head" style="margin-top:8px" dusk="segment-{{ $index }}-near-events">
                                    @foreach($near as $evIdx => $ev)
                                        <a href="#{{ $seg['id'] ?? '' }}"
                                           class="chip bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 transition-all duration-200 hover:scale-105 cursor-pointer"
                                           title="{{ $ev['time'] }}: {{ $ev['title'] }}"
                                           dusk="segment-{{ $index }}-near-event-{{ $evIdx }}"
                                           style="text-decoration:none">
                                            <span style="background:var(--warn); width:8px; height:8px; border-radius:999px; display:inline-block"></span>
                                            <strong>{{ $ev['time'] }}</strong>
                                            <span>{{ $ev['title'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                                <details class="txt transition-all duration-200" style="font-size:13px; color:#cbd5e1" dusk="segment-{{ $index }}-details">
                                    <summary class="cursor-pointer hover:text-sky-400 transition-colors duration-200" style="color:#e2e8f0; font-weight:500" dusk="segment-{{ $index }}-summary">
                                        Detalji ({{ count($near) }})
                                    </summary>
                                    <div style="margin-top:6px" dusk="segment-{{ $index }}-details-content">
                                        @foreach($near as $evIdx => $ev)
                                            <div style="margin-top:4px" dusk="segment-{{ $index }}-detail-{{ $evIdx }}"><em>{{ $ev['title'] }}:</em> {{ $ev['excerpt'] }}</div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
