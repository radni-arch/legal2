<div class="space-y-6" dusk="llm-brain-panel">
    <!-- Mode Selector -->
    <div class="flex gap-3 mb-6" dusk="mode-selector">
        <button
            wire:click="$set('mode', 'query')"
            wire:loading.attr="disabled"
            wire:target="$set('mode', 'query')"
            class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed @if($mode === 'query') bg-blue-600 text-white @else bg-gray-800 text-gray-400 hover:bg-gray-700 @endif"
            dusk="mode-query-btn"
        >
            <span wire:loading.remove wire:target="$set('mode', 'query')">🔍 Query</span>
            <span wire:loading wire:target="$set('mode', 'query')" class="inline-flex items-center">
                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            </span>
        </button>
        <button
            wire:click="$set('mode', 'chat')"
            wire:loading.attr="disabled"
            wire:target="$set('mode', 'chat')"
            class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed @if($mode === 'chat') bg-purple-600 text-white @else bg-gray-800 text-gray-400 hover:bg-gray-700 @endif"
            dusk="mode-chat-btn"
        >
            <span wire:loading.remove wire:target="$set('mode', 'chat')">💬 Chat</span>
            <span wire:loading wire:target="$set('mode', 'chat')" class="inline-flex items-center">
                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            </span>
        </button>
        <button
            wire:click="$set('mode', 'reasoning')"
            wire:loading.attr="disabled"
            wire:target="$set('mode', 'reasoning')"
            class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed @if($mode === 'reasoning') bg-green-600 text-white @else bg-gray-800 text-gray-400 hover:bg-gray-700 @endif"
            dusk="mode-reasoning-btn"
        >
            <span wire:loading.remove wire:target="$set('mode', 'reasoning')">🧠 Reasoning Chains</span>
            <span wire:loading wire:target="$set('mode', 'reasoning')" class="inline-flex items-center">
                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            </span>
        </button>
    </div>

    @if($mode === 'query')
        <!-- Natural Language Query Input -->
        <div class="llm-card" dusk="query-card">
            <div class="llm-card-header" dusk="query-card-header">
                <h2 class="llm-card-title" dusk="query-card-title">Natural Language Query</h2>
                <p class="text-sm mt-1" style="color: var(--muted);" dusk="query-card-description">Ask questions in plain Croatian or English, and the AI will convert them to graph queries</p>
            </div>
            <div class="llm-card-body space-y-4" dusk="query-card-body">
                <!-- Query Input -->
                <div dusk="query-input-wrapper">
                    <label class="block text-sm font-medium mb-2" style="color: var(--fg);" dusk="query-input-label">Your Question</label>
                    <textarea
                        wire:model.defer="naturalQuery"
                        rows="3"
                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        placeholder="e.g., Find Supreme Court decisions citing ZKP Article 9"
                        @if($loading) disabled @endif
                        dusk="query-input"
                    ></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3" dusk="action-buttons">
                    <button
                        wire:click="executeQuery"
                        wire:loading.attr="disabled"
                        wire:target="executeQuery"
                        class="llm-btn-primary"
                        dusk="execute-query-btn"
                    >
                        <span wire:loading.remove wire:target="executeQuery" dusk="execute-query-btn-text">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Execute Query
                        </span>
                        <span wire:loading wire:target="executeQuery" class="inline-flex items-center" dusk="execute-query-btn-loading">
                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>

                    @if($queryResult || $error)
                        <button
                            wire:click="clearResults"
                            wire:loading.attr="disabled"
                            wire:target="clearResults"
                            class="llm-btn-secondary"
                            dusk="clear-results-btn"
                        >
                            <span wire:loading.remove wire:target="clearResults" dusk="clear-results-btn-text">
                                Clear Results
                            </span>
                            <span wire:loading wire:target="clearResults" class="inline-flex items-center" dusk="clear-results-btn-loading">
                                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Clearing...
                            </span>
                        </button>
                    @endif
                </div>

                <!-- Error Display -->
                @if($error)
                    <div class="rounded-lg p-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);" dusk="error-display">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="error-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h4 class="font-semibold" style="color: #ef4444;" dusk="error-title">Error</h4>
                                <p class="text-sm mt-1" style="color: #fca5a5;" dusk="error-message">{{ $error }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Example Queries -->
        <div class="llm-card" dusk="examples-card">
            <div class="llm-card-header" dusk="examples-card-header">
                <h3 class="llm-card-title" dusk="examples-card-title">Example Queries</h3>
                <p class="text-sm mt-1" style="color: var(--muted);" dusk="examples-card-description">Click any example to try it</p>
            </div>
            <div class="llm-card-body" dusk="examples-card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" dusk="examples-grid">
                    @foreach($examples as $index => $example)
                        <button
                            wire:click="useExample({{ $index }})"
                            wire:loading.attr="disabled"
                            wire:target="useExample({{ $index }})"
                            class="text-left p-4 rounded-lg border border-gray-700 hover:border-blue-500 bg-gray-900 hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative"
                            dusk="example-btn-{{ $index }}"
                        >
                            <!-- Loading Overlay -->
                            <div wire:loading wire:target="useExample({{ $index }})" class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm flex items-center justify-center rounded-lg" dusk="example-btn-{{ $index }}-loading">
                                <svg class="animate-spin h-6 w-6 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            <div class="font-medium text-blue-400 mb-1" dusk="example-query-{{ $index }}">{{ $example['query'] }}</div>
                            <div class="text-sm text-gray-500" dusk="example-description-{{ $index }}">{{ $example['description'] }}</div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Query History -->
        @if(count($queryHistory) > 0)
            <div class="llm-card" dusk="history-card">
                <div class="llm-card-header">
                    <h3 class="llm-card-title">Query History</h3>
                </div>
                <div class="llm-card-body">
                    <div class="space-y-2">
                        @foreach($queryHistory as $index => $item)
                            <button
                                wire:click="rerunFromHistory({{ $index }})"
                                class="w-full text-left p-3 rounded-lg border border-gray-700 hover:border-blue-500 bg-gray-900 hover:bg-gray-800 transition-all"
                                dusk="history-item-{{ $index }}"
                            >
                                <div class="text-sm text-gray-300 truncate">{{ $item['query'] }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ \Carbon\Carbon::parse($item['timestamp'])->diffForHumans() }} • {{ $item['result_count'] }} results
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Empty State - No Results Yet -->
        @if(!$queryResult && !$generatedCypher && !$error && !$loading)
            <div class="llm-card" dusk="no-results-state">
                <div class="llm-card-body">
                    <div class="text-center py-8" style="color: #94a3b8;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" style="color: #6b7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="no-results-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <h3 class="text-lg font-semibold mb-2" style="color: #e5e7eb;" dusk="no-results-title">Ready to Query the Knowledge Graph</h3>
                        <p class="text-sm" dusk="no-results-description">Enter your question above or select an example query to get started</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Generated Cypher Query -->
        @if($generatedCypher)
            <div class="llm-card relative" dusk="cypher-card">
                <!-- Loading Overlay -->
                <div wire:loading wire:target="executeQuery" class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg" dusk="cypher-loading-overlay">
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-300 font-medium" dusk="cypher-loading-text">Generating Cypher query...</p>
                        <p class="text-gray-500 text-sm mt-1" dusk="cypher-loading-subtext">This may take a few seconds</p>
                    </div>
                </div>

                <div class="llm-card-header" dusk="cypher-card-header">
                    <h3 class="llm-card-title" dusk="cypher-card-title">Generated Cypher Query</h3>
                    @if($explanation)
                        <p class="text-sm mt-1" style="color: var(--muted);" dusk="cypher-explanation">{{ $explanation }}</p>
                    @endif
                </div>
                <div class="llm-card-body" dusk="cypher-card-body">
                    <pre class="bg-gray-900 border border-gray-700 rounded-lg p-4 overflow-x-auto" dusk="cypher-code-block"><code class="language-cypher text-sm" dusk="cypher-code">{{ $generatedCypher }}</code></pre>
                </div>
            </div>
        @endif

        <!-- Query Results -->
        @if($queryResult)
            <div class="llm-card relative" dusk="results-card">
                <!-- Loading Overlay -->
                <div wire:loading wire:target="executeQuery" class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm z-10 flex items-center justify-center rounded-lg" dusk="results-loading-overlay">
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 text-purple-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-300 font-medium" dusk="results-loading-text">Brain is thinking...</p>
                        <p class="text-gray-500 text-sm mt-1" dusk="results-loading-subtext">Executing query and analyzing results</p>
                    </div>
                </div>

                <div class="llm-card-header" dusk="results-card-header">
                    <h3 class="llm-card-title" dusk="results-card-title">Results (<span dusk="results-count">{{ count($queryResult) }}</span>)</h3>
                </div>
                <div class="llm-card-body" dusk="results-card-body">
                    <div class="overflow-x-auto" dusk="results-wrapper">
                        @include('livewire.partials.llm-result-table', ['results' => $queryResult])
                    </div>
                </div>
            </div>
        @endif

    @elseif($mode === 'chat')
        <div class="llm-card" dusk="chat-card">
            <div class="llm-card-header" dusk="chat-card-header">
                <h2 class="llm-card-title" dusk="chat-card-title">Legal Expert Chat</h2>
                <p class="text-sm mt-1" style="color: var(--muted);" dusk="chat-card-description">Ask questions about Croatian law and legal concepts</p>
            </div>
            <div class="llm-card-body space-y-4" dusk="chat-card-body">
                <!-- Chat Messages -->
                <div class="space-y-3 min-h-[400px] max-h-[600px] overflow-y-auto p-4 bg-gray-900 rounded-lg border border-gray-700" dusk="chat-messages-container">
                    @if(count($chatMessages) === 0)
                        <div class="text-center py-8" style="color: #94a3b8;" dusk="chat-empty-messages">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3" style="color: #8b5cf6;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p class="text-sm" style="color: #9ca3af;">Start a conversation with the legal assistant</p>
                        </div>
                    @else
                        @foreach($chatMessages as $index => $message)
                            <div class="flex @if($message['role'] === 'user') justify-end @else justify-start @endif" dusk="chat-message-{{ $index }}">
                                <div class="max-w-[80%] rounded-lg p-3 @if($message['role'] === 'user') bg-blue-600 text-white @elseif($message['role'] === 'assistant') bg-gray-800 text-gray-200 @else bg-yellow-900/30 text-yellow-200 border border-yellow-700 @endif" dusk="chat-message-{{ $index }}-bubble">
                                    <div class="text-xs font-semibold mb-1 opacity-70" dusk="chat-message-{{ $index }}-role">
                                        @if($message['role'] === 'user')
                                            You
                                        @elseif($message['role'] === 'assistant')
                                            Legal Assistant
                                        @else
                                            System
                                        @endif
                                    </div>
                                    <div class="text-sm whitespace-pre-wrap" dusk="chat-message-{{ $index }}-content">{{ $message['content'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Chat Input -->
                <div class="space-y-3" dusk="chat-input-wrapper">
                    <textarea
                        wire:model.defer="chatMessage"
                        rows="3"
                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:outline-none transition-colors"
                        placeholder="Ask a legal question..."
                        dusk="chat-input"
                    ></textarea>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3" dusk="chat-action-buttons">
                        <button
                            wire:click="sendChatMessage"
                            wire:loading.attr="disabled"
                            wire:target="sendChatMessage"
                            class="llm-btn-primary"
                            dusk="send-chat-btn"
                        >
                            <span wire:loading.remove wire:target="sendChatMessage" dusk="send-chat-btn-text">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                Send Message
                            </span>
                            <span wire:loading wire:target="sendChatMessage" class="inline-flex items-center" dusk="send-chat-btn-loading">
                                <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Sending...
                            </span>
                        </button>

                        @if(count($chatMessages) > 0)
                            <button
                                wire:click="clearChat"
                                wire:loading.attr="disabled"
                                wire:target="clearChat"
                                class="llm-btn-secondary"
                                dusk="clear-chat-btn"
                            >
                                <span wire:loading.remove wire:target="clearChat" dusk="clear-chat-btn-text">
                                    Clear Chat
                                </span>
                                <span wire:loading wire:target="clearChat" class="inline-flex items-center" dusk="clear-chat-btn-loading">
                                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Clearing...
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @elseif($mode === 'reasoning')
        <!-- Reasoning Chains Interface -->
        <div class="llm-card" dusk="reasoning-card">
            <div class="llm-card-header" dusk="reasoning-card-header">
                <h2 class="llm-card-title" dusk="reasoning-card-title">Reasoning Chains</h2>
                <p class="text-sm mt-1" style="color: var(--muted);" dusk="reasoning-card-description">Multi-hop reasoning with explainability traces</p>
            </div>
            <div class="llm-card-body space-y-4" dusk="reasoning-card-body">
                <!-- Query Input -->
                <div dusk="reasoning-input-wrapper">
                    <label class="block text-sm font-medium mb-2" style="color: var(--fg);" dusk="reasoning-input-label">Your Question</label>
                    <textarea
                        wire:model.defer="naturalQuery"
                        rows="3"
                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:outline-none transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        placeholder="e.g., Find contradicting decisions about proportionality"
                        @if($loading) disabled @endif
                        dusk="reasoning-input"
                    ></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3" dusk="reasoning-action-buttons">
                    <button
                        wire:click="executeReasoningQuery"
                        wire:loading.attr="disabled"
                        wire:target="executeReasoningQuery"
                        class="llm-btn-primary"
                        style="background: linear-gradient(135deg, #10b981, #059669);"
                        dusk="execute-reasoning-btn"
                    >
                        <span wire:loading.remove wire:target="executeReasoningQuery" dusk="execute-reasoning-btn-text">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Execute Reasoning Chain
                        </span>
                        <span wire:loading wire:target="executeReasoningQuery" class="inline-flex items-center" dusk="execute-reasoning-btn-loading">
                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>

                    @if($queryResult || $error)
                        <button
                            wire:click="clearResults"
                            wire:loading.attr="disabled"
                            wire:target="clearResults"
                            class="llm-btn-secondary"
                            dusk="reasoning-clear-results-btn"
                        >
                            <span wire:loading.remove wire:target="clearResults">Clear Results</span>
                            <span wire:loading wire:target="clearResults" class="inline-flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Clearing...
                            </span>
                        </button>
                    @endif
                </div>

                <!-- Error Display -->
                @if($error)
                    <div class="rounded-lg p-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);" dusk="reasoning-error-display">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0" style="color: #ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor" dusk="reasoning-error-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h4 class="font-semibold" style="color: #ef4444;" dusk="reasoning-error-title">Error</h4>
                                <p class="text-sm mt-1" style="color: #fca5a5;" dusk="reasoning-error-message">{{ $error }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Reasoning Trace Information -->
        @if($traceId || $durationMs)
            <div class="llm-card" dusk="reasoning-trace-card">
                <div class="llm-card-header">
                    <h3 class="llm-card-title" dusk="reasoning-trace-title">Reasoning Trace</h3>
                </div>
                <div class="llm-card-body" dusk="reasoning-trace-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($traceId)
                            <div dusk="trace-id-section">
                                <span class="text-sm font-medium text-gray-400">Trace ID:</span>
                                <p class="text-green-400 font-mono text-sm mt-1" dusk="trace-id-value">{{ $traceId }}</p>
                            </div>
                        @endif
                        @if($durationMs)
                            <div dusk="duration-section">
                                <span class="text-sm font-medium text-gray-400">Duration:</span>
                                <p class="text-green-400 font-mono text-sm mt-1" dusk="duration-value">{{ $durationMs }} ms</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Multi-hop Explanation -->
        @if($explanation)
            <div class="llm-card" dusk="reasoning-explanation-card">
                <div class="llm-card-header">
                    <h3 class="llm-card-title" dusk="reasoning-explanation-title">Multi-hop Reasoning Path</h3>
                </div>
                <div class="llm-card-body" dusk="reasoning-explanation-body">
                    <p class="text-gray-300 whitespace-pre-wrap" dusk="reasoning-explanation-text">{{ $explanation }}</p>
                </div>
            </div>
        @endif

        <!-- Generated Cypher Query -->
        @if($generatedCypher)
            <div class="llm-card" dusk="reasoning-cypher-card">
                <div class="llm-card-header">
                    <h3 class="llm-card-title" dusk="reasoning-cypher-title">Generated Cypher Query</h3>
                </div>
                <div class="llm-card-body" dusk="reasoning-cypher-body">
                    <pre class="bg-gray-900 border border-gray-700 rounded-lg p-4 overflow-x-auto" dusk="reasoning-cypher-code-block"><code class="language-cypher text-sm" dusk="reasoning-cypher-code">{{ $generatedCypher }}</code></pre>
                </div>
            </div>
        @endif

        <!-- Query Results -->
        @if($queryResult)
            <div class="llm-card" dusk="reasoning-results-card">
                <div class="llm-card-header">
                    <h3 class="llm-card-title" dusk="reasoning-results-title">Results (<span dusk="reasoning-results-count">{{ count($queryResult) }}</span>)</h3>
                </div>
                <div class="llm-card-body" dusk="reasoning-results-body">
                    <div class="overflow-x-auto" dusk="reasoning-results-wrapper">
                        @include('livewire.partials.llm-result-table', ['results' => $queryResult])
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
