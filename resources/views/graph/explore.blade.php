{{-- resources/views/graph/explore.blade.php --}}
<x-app-layout>
    <x-page-header
        title="Graph Explorer"
        subtitle="Interactive force-directed graph visualization"
        route-name="graph.explore"
    >
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150" style="background: var(--surface, #1e293b); border: 1px solid var(--border, #334155); color: var(--fg, #e5e7eb);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:graph.force-graph-controller
                :root-node-id="$nodeId"
                :panel="$panel ?? 'arguments'"
            />
        </div>
    </div>
</x-app-layout>
