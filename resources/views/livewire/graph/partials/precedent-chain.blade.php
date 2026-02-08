{{-- Precedent Chain Timeline Visualization Component --}}
@props(['precedentChain' => null, 'currentDecisionId' => null])

<div x-data="{ expanded: {} }" class="precedent-chain-container">
    @if(empty($precedentChain))
        {{-- Empty State --}}
        <div class="empty-state" style="padding: 2rem; text-align: center; background: rgba(17, 24, 39, 0.5); border: 1px dashed #374151; border-radius: 0.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="#6b7280">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 style="color: #9ca3af; font-size: 1.125rem; font-weight: 600;">No Precedents Found</h3>
            <p style="color: #6b7280; margin-top: 0.5rem; font-size: 0.875rem;">
                This decision does not follow or overrule any precedents in the database.
            </p>
        </div>
    @else
        {{-- Precedent Chain Timeline --}}
        <div class="timeline-container" style="position: relative;">
            @foreach($precedentChain as $index => $chain)
                <div class="chain-item" style="position: relative; margin-bottom: 2rem;">
                    {{-- Chain Header --}}
                    <div class="chain-header" style="margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            @if($chain['type'] === 'FOLLOWS')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                                <span style="color: #10b981; font-weight: 600;">Follows Precedent</span>
                            @elseif($chain['type'] === 'OVERRULES')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span style="color: #ef4444; font-weight: 600;">Overrules</span>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color: #6b7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span style="color: #6b7280; font-weight: 600;">Related</span>
                            @endif
                            <span style="color: #6b7280; font-size: 0.875rem;">
                                (Depth: {{ $chain['depth'] }})
                            </span>
                        </div>
                    </div>

                    {{-- Timeline Nodes --}}
                    <div class="timeline-nodes" style="position: relative; padding-left: 2rem;">
                        {{-- Vertical Line --}}
                        @if(count($chain['nodes']) > 1)
                            <div style="position: absolute; left: 0.5rem; top: 1.5rem; bottom: 1.5rem; width: 2px; background: {{ $chain['type'] === 'FOLLOWS' ? '#10b981' : ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#6b7280') }}; {{ $chain['type'] === 'OVERRULES' ? 'border-left: 2px dashed ' . ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#6b7280') . '; background: transparent;' : '' }}"></div>
                        @endif

                        @foreach($chain['nodes'] as $nodeIndex => $node)
                            <div class="timeline-node"
                                 style="position: relative; margin-bottom: {{ $nodeIndex < count($chain['nodes']) - 1 ? '1.5rem' : '0' }};"
                                 x-data="{ expanded: false }">
                                {{-- Node Dot --}}
                                <div style="position: absolute; left: -1.5rem; top: 0.5rem; width: 1rem; height: 1rem; border-radius: 50%; background: {{ $chain['type'] === 'FOLLOWS' ? '#10b981' : ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#6b7280') }}; border: 3px solid #0b1220; z-index: 10;"></div>

                                {{-- Node Card --}}
                                <div class="node-card"
                                     style="background: rgba(17, 24, 39, 0.8); border: 1px solid {{ $chain['type'] === 'FOLLOWS' ? '#10b981' : ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#374151') }}; border-radius: 0.5rem; padding: 1rem; transition: all 0.2s ease; cursor: pointer;"
                                     :style="expanded ? 'border-color: #3b82f6;' : ''"
                                     @click="expanded = !expanded; $wire.call('loadNodeGraph', 'CourtDecisionDocument', '{{ $node['id'] ?? '' }}')">
                                    <div class="node-header" style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #e5e7eb; font-size: 0.95rem; margin-bottom: 0.25rem;">
                                                {{ $node['case_number'] ?? 'N/A' }}
                                            </div>
                                            <div style="color: #9ca3af; font-size: 0.875rem;">
                                                {{ $node['court'] ?? 'Unknown Court' }}
                                            </div>
                                        </div>
                                        <div style="flex-shrink: 0;">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                 class="h-5 w-5 transition-transform duration-200"
                                                 :class="expanded ? 'rotate-180' : ''"
                                                 style="color: #6b7280;"
                                                 fill="none"
                                                 viewBox="0 0 24 24"
                                                 stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>

                                    {{-- Expanded Details --}}
                                    <div x-show="expanded"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #374151;">
                                        <div class="node-details" style="font-size: 0.875rem;">
                                            @if(isset($node['decision_date']))
                                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                    <span style="color: #6b7280;">Date:</span>
                                                    <span style="color: #e5e7eb;">{{ $node['decision_date'] }}</span>
                                                </div>
                                            @endif
                                            @if(isset($node['ecli']))
                                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                    <span style="color: #6b7280;">ECLI:</span>
                                                    <span style="color: #e5e7eb; font-family: monospace; font-size: 0.8rem;">{{ $node['ecli'] }}</span>
                                                </div>
                                            @endif
                                            @if(isset($node['decision_type']))
                                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                    <span style="color: #6b7280;">Type:</span>
                                                    <span style="color: #e5e7eb;">{{ $node['decision_type'] }}</span>
                                                </div>
                                            @endif
                                            @if(isset($node['id']))
                                                <div style="margin-top: 0.75rem;">
                                                    <button
                                                        wire:click="loadNodeGraph('CourtDecisionDocument', '{{ $node['id'] }}')"
                                                        class="btn-primary"
                                                        style="font-size: 0.875rem; padding: 0.5rem 1rem;"
                                                        @click.stop>
                                                        View in Graph
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Arrow Between Nodes --}}
                                @if($nodeIndex < count($chain['nodes']) - 1)
                                    <div style="position: relative; margin: 0.5rem 0 0.5rem -1.5rem; display: flex; align-items: center;">
                                        <div style="width: 1rem; height: 0; border-bottom: 2px {{ $chain['type'] === 'OVERRULES' ? 'dashed' : 'solid' }} {{ $chain['type'] === 'FOLLOWS' ? '#10b981' : ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#6b7280') }};"></div>
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-4 w-4"
                                             style="color: {{ $chain['type'] === 'FOLLOWS' ? '#10b981' : ($chain['type'] === 'OVERRULES' ? '#ef4444' : '#6b7280') }};"
                                             fill="none"
                                             viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($index < count($precedentChain) - 1)
                    <div style="border-top: 1px solid #374151; margin: 1.5rem 0;"></div>
                @endif
            @endforeach
        </div>
    @endif
</div>

<style>
    .precedent-chain-container .node-card:hover {
        border-color: #3b82f6 !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .precedent-chain-container .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        border-radius: 0.375rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
    }

    .precedent-chain-container .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
</style>
