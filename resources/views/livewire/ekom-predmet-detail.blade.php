<div class="min-h-screen p-8" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);">
    <div class="max-w-7xl mx-auto">
        {{-- Breadcrumb Navigation --}}
        <x-breadcrumbs :items="breadcrumbs('ekom.predmeti.show')" />

        {{-- Header with Title and Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $predmet->oznaka }}</h1>
                @if($this->courtName)
                    <p style="color: var(--muted, #9ca3af);">{{ $this->courtName }}</p>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap gap-3">
                <button
                    wire:click="toggleDnd"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg font-medium transition-colors"
                    style="background: {{ $predmet->do_not_disturb ? 'var(--warning, #f59e0b)' : 'var(--surface, #1f2937)' }}; color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);"
                >
                    <span wire:loading.remove wire:target="toggleDnd">
                        @if($predmet->do_not_disturb)
                            DND: ON (Click to Disable)
                        @else
                            DND: OFF (Click to Enable)
                        @endif
                    </span>
                    <span wire:loading wire:target="toggleDnd">
                        Updating...
                    </span>
                </button>

                <button
                    wire:click="refreshFromApi"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg font-medium transition-colors"
                    style="background: var(--surface, #1f2937); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #374151);"
                >
                    <span wire:loading.remove wire:target="refreshFromApi">
                        Refresh from API
                    </span>
                    <span wire:loading wire:target="refreshFromApi">
                        Refreshing...
                    </span>
                </button>

                <button
                    wire:click="downloadDocuments"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg font-medium transition-colors"
                    style="background: var(--accent, #60a5fa); color: white;"
                >
                    <span wire:loading.remove wire:target="downloadDocuments">
                        Download Documents
                    </span>
                    <span wire:loading wire:target="downloadDocuments">
                        Downloading...
                    </span>
                </button>
            </div>
        </div>

        {{-- Info Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {{-- Status Card --}}
            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <h3 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Status</h3>
                <p class="text-lg font-semibold">{{ $predmet->status }}</p>
            </div>

            {{-- Court Card --}}
            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <h3 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Court</h3>
                <p class="text-lg font-semibold">{{ $this->courtName ?? 'N/A' }}</p>
            </div>

            {{-- DND Status Card --}}
            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <h3 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Do Not Disturb</h3>
                <p class="text-lg font-semibold">
                    @if($predmet->do_not_disturb)
                        <span style="color: var(--warning, #f59e0b);">Enabled</span>
                    @else
                        <span style="color: var(--success, #10b981);">Disabled</span>
                    @endif
                </p>
            </div>

            {{-- Last Synced Card --}}
            <div class="p-4 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <h3 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Last Synced</h3>
                <p class="text-lg font-semibold">
                    @if($predmet->last_synced_at)
                        {{ $predmet->last_synced_at->diffForHumans() }}
                    @else
                        Never
                    @endif
                </p>
            </div>
        </div>

        {{-- Additional Details Section --}}
        @if($predmet->data)
            <div class="mb-8 p-6 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
                <h2 class="text-xl font-bold mb-4">Case Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if(isset($predmet->data['stranka_tuzitelj']) || isset($predmet->data['stranke']['tuzitelj']))
                        <div>
                            <h4 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Plaintiff</h4>
                            <p>{{ $predmet->data['stranka_tuzitelj'] ?? $predmet->data['stranke']['tuzitelj'] ?? 'N/A' }}</p>
                        </div>
                    @endif

                    @if(isset($predmet->data['stranka_tuzenik']) || isset($predmet->data['stranke']['tuzenik']))
                        <div>
                            <h4 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Defendant</h4>
                            <p>{{ $predmet->data['stranka_tuzenik'] ?? $predmet->data['stranke']['tuzenik'] ?? 'N/A' }}</p>
                        </div>
                    @endif

                    @if(isset($predmet->data['sudac']))
                        <div>
                            <h4 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Judge</h4>
                            <p>{{ $predmet->data['sudac'] }}</p>
                        </div>
                    @endif

                    @if(isset($predmet->data['datum_otvaranja']))
                        <div>
                            <h4 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Date Opened</h4>
                            <p>{{ $predmet->data['datum_otvaranja'] }}</p>
                        </div>
                    @endif

                    @if(isset($predmet->data['vrijednost_spora']))
                        <div>
                            <h4 class="text-sm font-medium mb-1" style="color: var(--muted, #9ca3af);">Dispute Value</h4>
                            <p>{{ number_format($predmet->data['vrijednost_spora'], 2) }} EUR</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Raw JSON Data Section --}}
        <div class="p-6 rounded-lg" style="background: var(--surface, #1f2937); border: 1px solid var(--border, #374151);">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Raw API Data</h2>
                <span class="text-xs px-2 py-1 rounded" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">
                    Remote ID: {{ $predmet->remote_id }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <pre class="p-4 rounded-lg text-sm overflow-auto max-h-96" style="background: var(--bg, #0b1220); color: var(--muted, #9ca3af);">{{ $this->formattedJson }}</pre>
            </div>
        </div>

        {{-- Back Link --}}
        <div class="mt-8">
            <a href="{{ route('ekom.predmeti') }}" class="inline-flex items-center gap-2 hover:underline" style="color: var(--accent, #60a5fa);">
                &larr; Back to Cases
            </a>
        </div>
    </div>
</div>
