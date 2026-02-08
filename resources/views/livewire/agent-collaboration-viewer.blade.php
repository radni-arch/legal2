<div class="p-6 bg-gray-900 text-gray-100 min-h-screen"
     @if($this->isRunning)
         wire:poll.{{ $pollingInterval }}ms="refreshData"
     @endif>

    @if($status === 'not_found')
        <div dusk="not-found-alert" class="bg-yellow-900 border-l-4 border-yellow-500 text-yellow-100 p-4 rounded" role="alert">
            <p class="font-bold">Orchestration not found</p>
            <p>The orchestration ID "{{ $orchestrationId }}" could not be found.</p>
        </div>
    @else
        {{-- Header: Task Description and Status --}}
        <div class="mb-6" dusk="header-section">
            <div class="flex items-center justify-between">
                <h1 dusk="task-description" class="text-3xl font-bold">{{ $taskDescription }}</h1>
                <span dusk="status-badge" class="px-4 py-2 rounded-full text-white {{ $this->statusColor }} font-semibold">
                    {{ ucfirst($status) }}
                </span>
            </div>
            <p dusk="orchestration-id" class="text-gray-400 mt-2">Orchestration ID: {{ $orchestrationId }}</p>
        </div>

        {{-- Metrics Card --}}
        <div dusk="metrics-section" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div dusk="metric-completed" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                <p class="text-gray-400 text-sm">Completed Agents</p>
                <p dusk="completed-count" class="text-2xl font-bold text-green-400">{{ $completedAgents }}</p>
            </div>
            <div dusk="metric-failed" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                <p class="text-gray-400 text-sm">Failed Agents</p>
                <p dusk="failed-count" class="text-2xl font-bold text-red-400">{{ $failedAgents }}</p>
            </div>
            <div dusk="metric-tokens" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                <p class="text-gray-400 text-sm">Tokens Used</p>
                <p dusk="tokens-count" class="text-2xl font-bold">{{ number_format($tokensUsed) }}</p>
            </div>
            <div dusk="metric-cost" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                <p class="text-gray-400 text-sm">Cost</p>
                <p dusk="cost-amount" class="text-2xl font-bold">${{ number_format($costSpent, 4) }}</p>
            </div>
            <div dusk="metric-duration" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                <p class="text-gray-400 text-sm">Duration</p>
                <p dusk="duration-time" class="text-2xl font-bold">{{ number_format($durationMs) }}ms</p>
            </div>
        </div>

        @if($errorMessage)
            <div dusk="error-alert" class="bg-red-900 border-l-4 border-red-500 text-red-100 p-4 rounded mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p dusk="error-message">{{ $errorMessage }}</p>
            </div>
        @endif

        {{-- Execution Timeline --}}
        <div dusk="timeline-section" class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Timeline
            </h2>

            @if(count($executionHistory) > 0)
                <div dusk="timeline-list" class="space-y-3">
                    @foreach($executionHistory as $index => $execution)
                        <div dusk="timeline-item-{{ $index }}" class="bg-gray-900 p-4 rounded border border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div dusk="agent-number-{{ $index }}" class="flex-shrink-0 w-8 h-8 rounded-full
                                        {{ $execution['status'] === 'completed' ? 'bg-green-500' : 'bg-red-500' }}
                                        flex items-center justify-center text-white font-bold">
                                        {{ $index + 1 }}
                                    </div>
                                    <div>
                                        <p dusk="agent-name-{{ $index }}" class="font-semibold">{{ $execution['agent'] }}</p>
                                        <p dusk="agent-status-{{ $index }}" class="text-sm text-gray-400">Status: {{ ucfirst($execution['status']) }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if(isset($execution['tokens_used']))
                                        <p dusk="agent-tokens-{{ $index }}" class="text-sm text-gray-400">{{ $execution['tokens_used'] }} tokens</p>
                                    @endif
                                    @if(isset($execution['cost']))
                                        <p dusk="agent-cost-{{ $index }}" class="text-sm text-gray-400">${{ number_format($execution['cost'], 4) }}</p>
                                    @endif
                                </div>
                            </div>

                            @if(isset($execution['error']))
                                <div dusk="agent-error-{{ $index }}" class="mt-2 text-sm text-red-400">
                                    Error: {{ $execution['error'] }}
                                </div>
                            @endif

                            @if(isset($execution['started_at']) || isset($execution['completed_at']))
                                <div class="mt-2 text-xs text-gray-500 flex space-x-4">
                                    @if(isset($execution['started_at']))
                                        <span dusk="agent-started-{{ $index }}">Started: {{ $execution['started_at'] }}</span>
                                    @endif
                                    @if(isset($execution['completed_at']))
                                        <span dusk="agent-completed-{{ $index }}">Completed: {{ $execution['completed_at'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p dusk="timeline-empty" class="text-gray-400">No execution history yet.</p>
            @endif
        </div>

        {{-- Shared Context --}}
        <div dusk="context-section" class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                </svg>
                Shared Context
            </h2>

            @if(count($sharedContext) > 0)
                <div dusk="context-content" class="bg-gray-900 p-4 rounded border border-gray-700">
                    <pre dusk="context-json" class="text-sm overflow-x-auto">{{ json_encode($sharedContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @else
                <p dusk="context-empty" class="text-gray-400">No shared context available.</p>
            @endif
        </div>

        {{-- Inter-Agent Messages --}}
        <div dusk="messages-section" class="bg-gray-800 p-6 rounded-lg border border-gray-700">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
                Messages
            </h2>

            @if(count($messages) > 0)
                <div dusk="messages-list" class="space-y-3">
                    @foreach($messages as $index => $message)
                        <div dusk="message-item-{{ $index }}" class="bg-gray-900 p-4 rounded border border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <span dusk="message-sender-{{ $index }}" class="font-semibold text-blue-400">{{ $message['sender'] }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    <span dusk="message-receiver-{{ $index }}" class="font-semibold text-green-400">{{ $message['receiver'] }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span dusk="message-type-{{ $index }}" class="px-2 py-1 bg-purple-900 text-purple-200 rounded text-xs">{{ $message['type'] }}</span>
                                    <span dusk="message-priority-{{ $index }}" class="px-2 py-1 bg-yellow-900 text-yellow-200 rounded text-xs">{{ $message['priority'] }}</span>
                                    <span dusk="message-status-{{ $index }}" class="px-2 py-1 bg-gray-700 text-gray-200 rounded text-xs">{{ $message['status'] }}</span>
                                </div>
                            </div>
                            <div dusk="message-payload-{{ $index }}" class="bg-gray-800 p-3 rounded text-sm">
                                <pre class="overflow-x-auto">{{ json_encode($message['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            @if($message['created_at'])
                                <p dusk="message-timestamp-{{ $index }}" class="text-xs text-gray-500 mt-2">{{ $message['created_at'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p dusk="messages-empty" class="text-gray-400">No messages between agents.</p>
            @endif
        </div>

        {{-- Auto-refresh indicator --}}
        @if($this->isRunning)
            <div dusk="polling-indicator" class="mt-6 flex items-center justify-center text-gray-400 text-sm">
                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Auto-refreshing every {{ $pollingInterval / 1000 }} seconds...
            </div>
        @endif
    @endif
</div>
