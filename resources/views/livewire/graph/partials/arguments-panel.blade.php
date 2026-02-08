{{-- resources/views/livewire/graph/partials/arguments-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Legal Arguments</h3>

    @if(empty($arguments))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p>Select a decision node to view arguments</p>
        </div>
    @else
        {{-- Plaintiff Arguments --}}
        @if(!empty($arguments['plaintiff']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-blue-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                    Plaintiff Arguments ({{ count($arguments['plaintiff']) }})
                </h4>
                @foreach($arguments['plaintiff'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-blue-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Defendant Arguments --}}
        @if(!empty($arguments['defendant']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-red-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-red-400 rounded-full"></span>
                    Defendant Arguments ({{ count($arguments['defendant']) }})
                </h4>
                @foreach($arguments['defendant'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-red-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Court Reasoning --}}
        @if(!empty($arguments['court']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-purple-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-400 rounded-full"></span>
                    Court Reasoning ({{ count($arguments['court']) }})
                </h4>
                @foreach($arguments['court'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-purple-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
