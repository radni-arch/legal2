{{-- resources/views/livewire/graph/alert-panel.blade.php --}}
<div class="fixed right-4 top-20 w-72 z-30" x-data="{ open: @entangle('isOpen') }">
    {{-- Toggle button --}}
    <button
        @click="open = !open"
        class="absolute -left-10 top-0 bg-gray-800 border border-r-0 border-gray-700 text-gray-400 hover:text-white p-2 rounded-l-lg transition-colors"
        title="Weakness Finder"
    >
        <svg x-show="!open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <svg x-show="open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    {{-- Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="bg-gray-900 rounded-lg shadow-2xl border border-gray-700"
    >
        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-700 flex justify-between items-center">
            <h3 class="font-semibold text-sm text-gray-200">
                Weakness Finder ({{ count($alerts) }})
            </h3>
        </div>

        {{-- Alerts list --}}
        <div class="max-h-80 overflow-y-auto">
            @forelse($alerts as $alert)
                <div class="px-4 py-3 border-b border-gray-800 last:border-b-0">
                    {{-- Severity badge --}}
                    <div class="flex items-center gap-2 mb-1">
                        @switch($alert['type'])
                            @case('overruled_precedent')
                                <span class="px-2 py-0.5 text-xs font-bold bg-red-900/50 text-red-300 border border-red-700/50 rounded">OVERRULED</span>
                                @break
                            @case('distinguished_precedent')
                                <span class="px-2 py-0.5 text-xs font-bold bg-yellow-900/50 text-yellow-300 border border-yellow-700/50 rounded">DISTINGUISHED</span>
                                @break
                            @case('weak_citation_chain')
                                <span class="px-2 py-0.5 text-xs font-bold bg-orange-900/50 text-orange-300 border border-orange-700/50 rounded">WEAKENED</span>
                                @break
                            @case('direct_contradiction')
                                <span class="px-2 py-0.5 text-xs font-bold bg-red-900/50 text-red-300 border border-red-700/50 rounded">CONTRADICTION</span>
                                @break
                            @case('superseded_law')
                                <span class="px-2 py-0.5 text-xs font-bold bg-orange-900/50 text-orange-300 border border-orange-700/50 rounded">SUPERSEDED</span>
                                @break
                            @case('outdated_citation')
                                <span class="px-2 py-0.5 text-xs font-bold bg-orange-900/50 text-orange-300 border border-orange-700/50 rounded">OUTDATED</span>
                                @break
                            @default
                                @if($alert['severity'] === 'critical')
                                    <span class="px-2 py-0.5 text-xs font-bold bg-red-900/50 text-red-300 border border-red-700/50 rounded">CRITICAL</span>
                                @elseif($alert['severity'] === 'warning')
                                    <span class="px-2 py-0.5 text-xs font-bold bg-yellow-900/50 text-yellow-300 border border-yellow-700/50 rounded">WARNING</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-bold bg-orange-900/50 text-orange-300 border border-orange-700/50 rounded">CAUTION</span>
                                @endif
                        @endswitch
                    </div>

                    {{-- Message --}}
                    <p class="text-sm text-gray-300 mb-2">
                        {{ $alert['message'] }}
                    </p>

                    {{-- Actions --}}
                    <div class="flex gap-2">
                        <button
                            wire:click="dismissAlert('{{ $alert['id'] }}')"
                            class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500">
                    <p class="text-sm">No alerts</p>
                    <p class="text-xs mt-1">Pin nodes to scan for issues</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="px-4 py-3 border-t border-gray-700">
            <button
                wire:click="scanAllNodes"
                class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
            >
                Scan All Pinned Nodes
            </button>
        </div>
    </div>
</div>
