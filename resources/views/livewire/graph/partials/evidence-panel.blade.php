{{-- resources/views/livewire/graph/partials/evidence-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Evidence</h3>

    @if(empty($evidence))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>Select a decision node to view evidence</p>
        </div>
    @else
        {{-- Documentary Evidence --}}
        @if(!empty($evidence['documentary']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-lime-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Documentary Evidence ({{ count($evidence['documentary']) }})
                </h4>
                @foreach($evidence['documentary'] as $item)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm border-l-2 border-lime-400">
                        <div class="text-gray-300">{{ $item['description'] }}</div>
                        @if(!empty($item['source']))
                            <div class="text-xs text-gray-500 mt-1">Source: {{ $item['source'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Testimonial Evidence --}}
        @if(!empty($evidence['testimonial']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-cyan-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Testimonial Evidence ({{ count($evidence['testimonial']) }})
                </h4>
                @foreach($evidence['testimonial'] as $item)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm border-l-2 border-cyan-400">
                        <div class="text-gray-300">{{ $item['description'] }}</div>
                        @if(!empty($item['source']))
                            <div class="text-xs text-gray-500 mt-1">Source: {{ $item['source'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Expert Evidence --}}
        @if(!empty($evidence['expert']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-amber-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    Expert Evidence ({{ count($evidence['expert']) }})
                </h4>
                @foreach($evidence['expert'] as $item)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm border-l-2 border-amber-400">
                        <div class="text-gray-300">{{ $item['description'] }}</div>
                        @if(!empty($item['source']))
                            <div class="text-xs text-gray-500 mt-1">Source: {{ $item['source'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Physical Evidence --}}
        @if(!empty($evidence['physical']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-rose-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Physical Evidence ({{ count($evidence['physical']) }})
                </h4>
                @foreach($evidence['physical'] as $item)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm border-l-2 border-rose-400">
                        <div class="text-gray-300">{{ $item['description'] }}</div>
                        @if(!empty($item['source']))
                            <div class="text-xs text-gray-500 mt-1">Source: {{ $item['source'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
