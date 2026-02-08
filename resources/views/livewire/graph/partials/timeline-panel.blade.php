{{-- resources/views/livewire/graph/partials/timeline-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Timeline</h3>

    @if(empty($timeline))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>Select a decision node to view timeline</p>
        </div>
    @else
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-700"></div>

            <div class="space-y-4">
                @foreach($timeline as $event)
                    <div class="relative pl-10">
                        {{-- Dot --}}
                        <div class="absolute left-2.5 w-3 h-3 rounded-full
                            @switch($event['event_type'] ?? 'other')
                                @case('filing') bg-blue-500 @break
                                @case('hearing') bg-amber-500 @break
                                @case('judgment') bg-green-500 @break
                                @case('appeal') bg-purple-500 @break
                                @default bg-gray-500
                            @endswitch
                        "></div>

                        {{-- Event card --}}
                        <div class="bg-gray-800 rounded-lg p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-medium uppercase tracking-wider
                                        @switch($event['event_type'] ?? 'other')
                                            @case('filing') text-blue-400 @break
                                            @case('hearing') text-amber-400 @break
                                            @case('judgment') text-green-400 @break
                                            @case('appeal') text-purple-400 @break
                                            @default text-gray-400
                                        @endswitch
                                    ">
                                        {{ $event['event_type'] ?? 'Event' }}
                                    </span>
                                    <div class="text-sm text-gray-300 mt-1">
                                        {{ $event['description'] }}
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 whitespace-nowrap ml-2">
                                    {{ \Carbon\Carbon::parse($event['date'])->format('M j, Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
