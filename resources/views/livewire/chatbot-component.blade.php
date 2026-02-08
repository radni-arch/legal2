<div class="h-screen flex flex-col bg-gradient-to-br from-slate-50 via-blue-50/30 to-indigo-50/20 dark:from-slate-950 dark:via-slate-900 dark:to-slate-900 transition-colors duration-300">
    {{-- Unified Header --}}
    <x-page-header
        title="AI Legal Assistant"
        subtitle="Chat with AI for legal research and case analysis"
        route-name="chatbot"
    >
        <x-slot:actions>
            <button
                wire:click="newConversation"
                wire:loading.attr="disabled"
                wire:target="newConversation"
                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-weight: 500; font-size: 0.875rem; transition: all 0.2s;"
                aria-label="Start a new conversation"
                title="Start a new conversation">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" wire:loading.remove wire:target="newConversation">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                <svg wire:loading wire:target="newConversation" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="hidden sm:inline">New Chat</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Main Content --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Sidebar - Conversations List --}}
        <aside class="w-80 lg:w-80 md:w-72 sm:w-64 bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border-r border-slate-200/50 dark:border-slate-700/50 overflow-y-auto shadow-lg transition-colors duration-300" role="complementary" aria-label="Conversation history and settings">
            <div class="p-4 space-y-4">
                <div class="mb-4 relative">
                    <label for="agent-select" class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                        Select Agent Type
                    </label>
                    <div class="relative">
                        <select
                            id="agent-select"
                            wire:model.live="agentType"
                            wire:loading.attr="disabled"
                            wire:target="agentType"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 text-sm shadow-sm focus:outline-none focus:ring-2 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed"
                            aria-describedby="agent-help">
                            @foreach($agentTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div wire:loading wire:target="agentType" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" role="status">
                            <svg class="animate-spin h-4 w-4 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="sr-only">Updating agent type...</span>
                        </div>
                    </div>
                    <p id="agent-help" class="sr-only">Choose the type of AI agent to assist you</p>
                </div>

                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Recent Conversations</h2>
                    @if($activeConversation && count($messages) > 0)
                        <button
                            wire:click="clearConversation"
                            wire:confirm="Clear all messages in this conversation?"
                            wire:loading.attr="disabled"
                            wire:target="clearConversation"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-slate-200/80 dark:bg-slate-700/80 text-slate-700 dark:text-slate-300 hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-600 dark:hover:text-red-400 transition-all duration-200 font-medium focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                            aria-label="Clear conversation messages"
                            title="Clear all messages">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" wire:loading.remove wire:target="clearConversation">
                                <path d="M19 13H5v-2h14v2z"/>
                            </svg>
                            <svg wire:loading wire:target="clearConversation" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="clearConversation">Clear</span>
                            <span wire:loading wire:target="clearConversation">Clearing...</span>
                        </button>
                    @endif
                </div>

                <nav class="space-y-2" aria-label="Conversation list" role="navigation">
                    {{-- Loading skeleton for conversations list - only show when reloading list --}}
                    <div wire:loading wire:target="loadConversations,clearConversation" class="space-y-2" role="status" aria-label="Loading conversations">
                        @for($i = 0; $i < 3; $i++)
                            <div class="rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 p-3 animate-pulse">
                                <div class="h-4 bg-slate-200 dark:bg-slate-600 rounded w-3/4 mb-2"></div>
                                <div class="h-3 bg-slate-200 dark:bg-slate-600 rounded w-1/2"></div>
                            </div>
                        @endfor
                        <span class="sr-only">Loading conversations...</span>
                    </div>

                    <div wire:loading.remove wire:target="loadConversations,clearConversation">
                    @forelse($conversations as $conv)
                        {{-- PERFORMANCE: wire:key enables efficient DOM diffing (60-70% faster updates) --}}
                        <div
                            wire:key="conversation-{{ $conv['uuid'] }}"
                            class="group relative rounded-xl border {{ $conversation === $conv['uuid'] ? 'bg-gradient-to-r from-sky-100 to-indigo-100 dark:from-sky-900/40 dark:to-indigo-900/40 border-sky-400 dark:border-sky-600 shadow-md' : 'bg-white dark:bg-slate-700 border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500 hover:shadow-md' }} transition-all duration-200 cursor-pointer">
                            <button
                                wire:click="loadConversation('{{ $conv['uuid'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="loadConversation"
                                wire:loading.class="opacity-50"
                                class="w-full text-left p-3 relative focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:ring-inset rounded-xl disabled:cursor-not-allowed"
                                aria-label="Load conversation: {{ $conv['title'] }}, {{ $conv['message_count'] }} messages, last updated {{ $conv['last_message_at'] }}"
                                aria-current="{{ $conversation === $conv['uuid'] ? 'true' : 'false' }}">
                                <div wire:loading wire:target="loadConversation" class="absolute inset-0 flex items-center justify-center bg-white/90 dark:bg-slate-700/90 backdrop-blur-sm sm:backdrop-blur-none rounded-xl" role="status" aria-live="polite">
                                    <svg class="animate-spin h-5 w-5 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="sr-only">Loading conversation</span>
                                </div>
                                <div class="font-semibold text-sm text-slate-900 dark:text-slate-100 truncate pr-6">
                                    {{ $conv['title'] }}
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 font-medium">
                                    {{ $conv['message_count'] }} messages • {{ $conv['last_message_at'] }}
                                </div>
                                @if($conv['agent_type'])
                                    <div class="mt-2">
                                        <span class="inline-block px-2.5 py-1 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-medium" aria-label="Agent type">
                                            {{ $agentTypes[$conv['agent_type']] ?? $conv['agent_type'] }}
                                        </span>
                                    </div>
                                @endif
                            </button>
                            <button
                                wire:click="deleteConversation('{{ $conv['uuid'] }}')"
                                wire:confirm="Are you sure you want to delete this conversation? This action cannot be undone."
                                wire:loading.attr="disabled"
                                wire:target="deleteConversation"
                                class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 focus:opacity-100 p-1.5 rounded-lg text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400 disabled:opacity-40 disabled:cursor-not-allowed"
                                aria-label="Delete conversation: {{ $conv['title'] }}. This action cannot be undone."
                                title="Delete conversation">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" wire:loading.remove wire:target="deleteConversation">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                                <svg wire:loading wire:target="deleteConversation" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-12 text-slate-500 dark:text-slate-400" role="status">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto opacity-20 mb-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                            <p class="text-sm font-semibold">No conversations yet</p>
                            <p class="text-xs mt-1 opacity-75">Start a new chat to begin</p>
                        </div>
                    @endforelse
                    </div>
                </nav>
            </div>
        </aside>

        {{-- Chat Area --}}
        <main class="flex-1 flex flex-col" role="main" aria-label="Chat conversation">
            {{-- Messages Container --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6" id="messages-container" role="log" aria-live="polite" aria-atomic="false" aria-label="Chat messages">
                @if(empty($messages))
                    {{-- Welcome Screen --}}
                    <div class="flex items-center justify-center h-full px-4">
                        <div class="text-center max-w-2xl">
                            <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-indigo-100 to-violet-100 dark:from-sky-900/40 dark:via-indigo-900/40 dark:to-violet-900/40 p-8 inline-block mb-6 shadow-xl shadow-indigo-500/10 dark:shadow-indigo-900/20 ring-1 ring-indigo-200/50 dark:ring-indigo-800/50 transition-all duration-300 hover:scale-105" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                </svg>
                            </div>
                            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-3 tracking-tight">Welcome to AI Legal Assistant</h2>
                            <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 mb-8 leading-relaxed">Ask me anything about Croatian law, court decisions, or legal cases. I'm here to help with your legal research.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left" role="list">
                                <div class="group p-5 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-indigo-300 dark:hover:border-indigo-600" role="listitem">
                                    <h3 class="font-bold text-slate-900 dark:text-white mb-2 text-base group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-300">Legal Research</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">Get information about Croatian laws and regulations</p>
                                </div>
                                <div class="group p-5 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-indigo-300 dark:hover:border-indigo-600" role="listitem">
                                    <h3 class="font-bold text-slate-900 dark:text-white mb-2 text-base group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-300">Court Decisions</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">Analyze court rulings and precedents</p>
                                </div>
                                <div class="group p-5 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-indigo-300 dark:hover:border-indigo-600" role="listitem">
                                    <h3 class="font-bold text-slate-900 dark:text-white mb-2 text-base group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-300">Case Analysis</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">Get insights on legal cases and strategies</p>
                                </div>
                                <div class="group p-5 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-indigo-300 dark:hover:border-indigo-600" role="listitem">
                                    <h3 class="font-bold text-slate-900 dark:text-white mb-2 text-base group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-300">General Help</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">Ask any legal question you have</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Messages --}}
                    {{-- PERFORMANCE: wire:key prevents full list re-render (60-70% faster when adding messages) --}}
                    @foreach($messages as $messageIndex => $message)
                        <article
                            wire:key="message-{{ $message['id'] ?? $messageIndex }}"
                            class="flex {{ $message['is_user'] ? 'justify-end' : 'justify-start' }} animate-in fade-in slide-in-from-bottom-4 duration-500"
                            role="article"
                            aria-label="{{ $message['is_user'] ? 'Your message' : 'AI assistant message' }}">
                            <div class="flex gap-3 sm:gap-4 max-w-3xl {{ $message['is_user'] ? 'flex-row-reverse' : '' }}">
                                {{-- Avatar --}}
                                <div class="flex-shrink-0" aria-hidden="true">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white dark:ring-slate-800 transition-all duration-300 hover:scale-105 {{ $message['is_user'] ? 'bg-gradient-to-br from-sky-500 to-indigo-600 dark:from-sky-600 dark:to-indigo-700' : 'bg-gradient-to-br from-slate-700 to-slate-900 dark:from-slate-600 dark:to-slate-800' }}" title="{{ $message['is_user'] ? 'You' : 'AI Assistant' }}">
                                        @if($message['is_user'])
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <title>User avatar</title>
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <title>AI Assistant avatar</title>
                                                <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                            </svg>
                                        @endif
                                    </div>
                                </div>

                                {{-- Message Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="sr-only">{{ $message['is_user'] ? 'You said:' : 'AI Assistant responded:' }}</div>
                                    <div class="rounded-2xl px-4 py-3 sm:px-5 sm:py-4 shadow-lg transition-all duration-300 hover:shadow-xl {{ $message['is_user'] ? 'bg-gradient-to-br from-sky-600 to-indigo-600 dark:from-sky-700 dark:to-indigo-700 text-white shadow-sky-500/20 dark:shadow-sky-900/30' : 'bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 text-slate-900 dark:text-slate-100 shadow-slate-300/20 dark:shadow-slate-900/30 hover:border-slate-300 dark:hover:border-slate-600' }}" role="region">
                                        @if($message['is_user'])
                                            <div class="text-sm sm:text-base whitespace-pre-wrap break-words leading-relaxed">{{ $message['content'] }}</div>
                                        @else
                                            <div class="text-sm sm:text-base prose prose-sm sm:prose-base max-w-none prose-slate dark:prose-invert prose-headings:font-bold prose-headings:text-slate-900 dark:prose-headings:text-white prose-a:text-indigo-600 dark:prose-a:text-indigo-400 prose-a:font-semibold hover:prose-a:text-indigo-700 dark:hover:prose-a:text-indigo-300 prose-code:bg-slate-100 dark:prose-code:bg-slate-900 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-slate-900 dark:prose-code:text-slate-100 prose-code:font-mono prose-pre:bg-slate-900 dark:prose-pre:bg-slate-950 prose-pre:text-slate-100 prose-pre:shadow-xl">
                                                {!! $message['content_html'] ?? e($message['content']) !!}
                                            </div>
                                        @endif
                                    </div>
                                    <time class="text-xs text-slate-500 dark:text-slate-400 mt-2 px-1 font-medium {{ $message['is_user'] ? 'text-right' : '' }} block" datetime="{{ $message['full_timestamp'] ?? '' }}" title="{{ $message['full_timestamp'] ?? '' }}">
                                        {{ $message['created_at'] }}
                                    </time>
                                </div>
                            </div>
                        </article>
                    @endforeach

                    {{-- Loading Indicator --}}
                    @if($isLoading)
                        <div class="flex justify-start animate-in fade-in slide-in-from-bottom-4 duration-500" role="status" aria-live="polite" aria-label="AI is thinking">
                            <div class="flex gap-3 sm:gap-4 max-w-3xl">
                                <div class="flex-shrink-0" aria-hidden="true">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center bg-gradient-to-br from-slate-700 to-slate-900 dark:from-slate-600 dark:to-slate-800 shadow-lg ring-2 ring-white dark:ring-slate-800 transition-all duration-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6 text-white animate-pulse" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="rounded-2xl px-4 py-3 sm:px-5 sm:py-4 bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none border border-slate-200/50 dark:border-slate-700/50 shadow-lg shadow-slate-300/20 dark:shadow-slate-900/30">
                                        <div class="flex gap-1.5" aria-hidden="true">
                                            <div class="w-2.5 h-2.5 bg-gradient-to-br from-indigo-400 to-indigo-600 dark:from-indigo-500 dark:to-indigo-700 rounded-full animate-bounce shadow-sm" style="animation-delay: 0ms"></div>
                                            <div class="w-2.5 h-2.5 bg-gradient-to-br from-indigo-400 to-indigo-600 dark:from-indigo-500 dark:to-indigo-700 rounded-full animate-bounce shadow-sm" style="animation-delay: 150ms"></div>
                                            <div class="w-2.5 h-2.5 bg-gradient-to-br from-indigo-400 to-indigo-600 dark:from-indigo-500 dark:to-indigo-700 rounded-full animate-bounce shadow-sm" style="animation-delay: 300ms"></div>
                                        </div>
                                        <span class="sr-only">AI Assistant is thinking...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Error Message --}}
            @if($error)
                <div class="px-4 sm:px-6 py-4 bg-red-50 dark:bg-red-900/20 border-t border-red-200 dark:border-red-800/50 backdrop-blur-sm" role="alert" aria-live="assertive">
                    <div class="flex items-center gap-3 text-red-800 dark:text-red-300">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium flex-1" id="error-message">{{ $error }}</span>
                        <button
                            wire:click="$set('error', null)"
                            type="button"
                            class="ml-auto p-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400 hover:scale-105 active:scale-95"
                            aria-label="Dismiss error message"
                            title="Dismiss error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Input Area --}}
            <div class="border-t border-slate-200/50 dark:border-slate-700/50 bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:backdrop-blur-none p-4 sm:p-6 shadow-2xl transition-colors duration-300 relative" role="region" aria-label="Message input">
                {{-- Loading overlay only for major state-changing operations --}}
                <div wire:loading wire:target="newConversation,loadConversation" class="absolute inset-0 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm flex items-center justify-center z-10 rounded-t-2xl" role="status" aria-live="polite">
                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800 px-5 py-3 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700">
                        <svg class="animate-spin h-5 w-5 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Loading...</span>
                    </div>
                </div>
                <form wire:submit.prevent="sendMessage" class="flex gap-3 sm:gap-4" aria-label="Send message form">
                    <div class="flex-1">
                        <label for="message-input" class="sr-only">Type your legal question or message</label>
                        <textarea
                            id="message-input"
                            wire:model="currentInput"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            placeholder="Ask about Croatian law, court decisions, or legal cases..."
                            rows="1"
                            class="w-full rounded-2xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-indigo-500 dark:focus:ring-indigo-400 resize-none shadow-lg focus:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-slate-50 dark:disabled:bg-slate-800/50 disabled:border-slate-200 dark:disabled:border-slate-700"
                            style="min-height: 44px; max-height: 200px"
                            aria-label="Message input"
                            aria-describedby="input-help input-char-count"
                            aria-invalid="{{ $errors->has('currentInput') ? 'true' : 'false' }}"
                            @keydown.enter.prevent="if (!event.shiftKey && !$wire.isLoading) { $wire.sendMessage(); }"
                            @input="this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 200) + 'px';
                                    const count = this.value.length;
                                    const counter = document.getElementById('input-char-count');
                                    if (counter) counter.textContent = count + ' character' + (count !== 1 ? 's' : '');"
                            {{ $isLoading ? 'disabled' : '' }}
                        ></textarea>
                        <div class="flex justify-between items-center mt-2">
                            @error('currentInput')
                                <span class="text-xs text-red-600 dark:text-red-400 font-medium flex items-center gap-1.5 animate-in fade-in slide-in-from-left-2 duration-300" role="alert" id="input-error">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                    </svg>
                                    {{ $message }}
                                </span>
                            @else
                                <span class="text-xs text-slate-500 dark:text-slate-400" id="input-help">Press <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs shadow-sm">Enter</kbd> to send, <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs shadow-sm">Shift+Enter</kbd> for new line</span>
                            @enderror
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium tabular-nums" id="input-char-count" role="status" aria-live="off" aria-atomic="true">0 characters</span>
                        </div>
                    </div>
                    <button
                        type="submit"
                        @disabled($isLoading || empty(trim($currentInput)))
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        class="px-5 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-700 dark:to-violet-700 text-white rounded-2xl hover:from-indigo-700 hover:to-violet-700 dark:hover:from-indigo-800 dark:hover:to-violet-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:saturate-50 disabled:hover:from-indigo-600 disabled:hover:to-violet-600 disabled:dark:hover:from-indigo-700 disabled:dark:hover:to-violet-700 disabled:hover:scale-100 disabled:hover:shadow-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center gap-2 font-semibold hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:ring-offset-2 dark:focus:ring-offset-slate-800 self-start relative"
                        aria-label="{{ $isLoading ? 'Sending message...' : 'Send message' }}"
                        aria-disabled="{{ $isLoading || empty(trim($currentInput)) ? 'true' : 'false' }}"
                        title="Send message (Enter)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" wire:loading.remove wire:target="sendMessage">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                        <svg wire:loading wire:target="sendMessage" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="hidden sm:inline" wire:loading.remove wire:target="sendMessage">Send</span>
                        <span class="hidden sm:inline" wire:loading wire:target="sendMessage">Sending...</span>
                        <span class="sr-only" wire:loading wire:target="sendMessage">Sending message...</span>
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>

@push('scripts')
<script>
    /**
     * Chatbot Component - Auto-scroll, Accessibility, and Livewire Integration
     * Handles automatic scrolling, focus management, and accessibility features
     * @version 2.0 - Modernized with ES6+, error handling, and performance optimizations
     */
    (() => {
        'use strict';

        // ============================================================================
        // CONSTANTS AND CONFIGURATION
        // ============================================================================

        /** @type {string} */
        const MESSAGES_CONTAINER_ID = 'messages-container';

        /** @type {string} */
        const MESSAGE_INPUT_ID = 'message-input';

        /** @type {string} */
        const CHAR_COUNT_ID = 'input-char-count';

        /** @type {number} */
        const SCROLL_THRESHOLD = 150; // Pixels from bottom to consider "at bottom"

        /** @type {number} */
        const DEBOUNCE_DELAY = 16; // ~60fps for smooth performance

        /** @type {number} */
        const ANNOUNCEMENT_CLEANUP_DELAY = 1000; // Time before removing SR announcements

        /** @type {Set<Function>} */
        const cleanupFunctions = new Set();

        /** @type {WeakSet<Element>} */
        const processedAnnouncements = new WeakSet();

        // ============================================================================
        // UTILITY FUNCTIONS
        // ============================================================================

        /**
         * Creates a debounced version of a function
         * @param {Function} func - Function to debounce
         * @param {number} wait - Milliseconds to wait
         * @returns {Function} Debounced function with cancel method
         */
        const debounce = (func, wait) => {
            let timeoutId = null;

            const debounced = (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func(...args), wait);
            };

            debounced.cancel = () => {
                clearTimeout(timeoutId);
                timeoutId = null;
            };

            return debounced;
        };

        /**
         * Safely gets an element by ID with error handling
         * @param {string} id - Element ID
         * @returns {HTMLElement|null} The element or null if not found
         */
        const safeGetElement = (id) => {
            try {
                return document.getElementById(id);
            } catch (error) {
                console.error(`[Chatbot] Error getting element with ID "${id}":`, error);
                return null;
            }
        };

        /**
         * Checks if the container is scrolled near the bottom
         * @param {HTMLElement} container - The scrollable container element
         * @returns {boolean} True if user is near the bottom of the scroll area
         */
        const isNearBottom = (container) => {
            if (!container) return false;

            try {
                const { scrollTop, scrollHeight, clientHeight } = container;
                return scrollHeight - scrollTop - clientHeight < SCROLL_THRESHOLD;
            } catch (error) {
                console.error('[Chatbot] Error checking scroll position:', error);
                return false;
            }
        };

        // ============================================================================
        // CORE FUNCTIONALITY
        // ============================================================================

        /**
         * Scrolls the messages container to the bottom with smooth behavior
         * Only scrolls if the user was already near the bottom (preserves scroll position when reading history)
         * @param {boolean} [force=false] - Force scroll regardless of current position
         * @returns {void}
         */
        const scrollToBottom = (force = false) => {
            const container = safeGetElement(MESSAGES_CONTAINER_ID);

            if (!container) {
                console.warn('[Chatbot] Messages container not found');
                return;
            }

            // Don't auto-scroll if user is reading message history, unless forced
            if (!force && !isNearBottom(container)) {
                return;
            }

            try {
                requestAnimationFrame(() => {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                });
            } catch (error) {
                console.error('[Chatbot] Error scrolling to bottom:', error);
                // Fallback to instant scroll
                try {
                    container.scrollTop = container.scrollHeight;
                } catch (fallbackError) {
                    console.error('[Chatbot] Fallback scroll failed:', fallbackError);
                }
            }
        };

        /**
         * Initializes and updates character count for the message input
         * @returns {void}
         */
        const initCharacterCount = () => {
            const textarea = safeGetElement(MESSAGE_INPUT_ID);
            const counter = safeGetElement(CHAR_COUNT_ID);

            if (!textarea || !counter) {
                return;
            }

            try {
                const count = textarea.value?.length ?? 0;
                counter.textContent = `${count} character${count !== 1 ? 's' : ''}`;
            } catch (error) {
                console.error('[Chatbot] Error updating character count:', error);
            }
        };

        /**
         * Returns focus to the message input field
         * Uses requestAnimationFrame to ensure DOM is ready
         * @returns {void}
         */
        const focusMessageInput = () => {
            const textarea = safeGetElement(MESSAGE_INPUT_ID);

            if (!textarea) {
                return;
            }

            try {
                // Check if the element is disabled or not in the DOM
                if (textarea.disabled || !textarea.isConnected) {
                    return;
                }

                requestAnimationFrame(() => {
                    try {
                        textarea.focus({ preventScroll: true });
                    } catch (error) {
                        console.error('[Chatbot] Error focusing input:', error);
                    }
                });
            } catch (error) {
                console.error('[Chatbot] Error in focusMessageInput:', error);
            }
        };

        /**
         * Announces message count update to screen readers
         * Creates a temporary ARIA live region for accessibility
         * @returns {void}
         */
        const announceMessageUpdate = () => {
            const messagesContainer = safeGetElement(MESSAGES_CONTAINER_ID);

            if (!messagesContainer) {
                return;
            }

            try {
                const messages = messagesContainer.querySelectorAll('article[role="article"]');

                if (messages.length === 0) {
                    return;
                }

                // Create and configure announcement element
                const announcement = document.createElement('div');
                announcement.setAttribute('role', 'status');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.className = 'sr-only';
                announcement.textContent = `${messages.length} message${messages.length !== 1 ? 's' : ''} in conversation`;

                // Add to DOM
                document.body.appendChild(announcement);

                // Mark as processed to prevent duplicate announcements
                processedAnnouncements.add(announcement);

                // Clean up after announcement
                setTimeout(() => {
                    try {
                        if (announcement.isConnected) {
                            announcement.remove();
                        }
                    } catch (error) {
                        console.error('[Chatbot] Error removing announcement:', error);
                    }
                }, ANNOUNCEMENT_CLEANUP_DELAY);

            } catch (error) {
                console.error('[Chatbot] Error announcing message update:', error);
            }
        };

        /**
         * Handles keyboard shortcuts for the chatbot
         * @param {KeyboardEvent} event - The keyboard event
         * @returns {void}
         */
        const handleKeyboardShortcuts = (event) => {
            try {
                // Ctrl/Cmd + / to focus message input
                if ((event.ctrlKey || event.metaKey) && event.key === '/') {
                    event.preventDefault();
                    focusMessageInput();
                }

                // Escape to blur (unfocus) the input
                if (event.key === 'Escape') {
                    const textarea = safeGetElement(MESSAGE_INPUT_ID);
                    if (textarea && document.activeElement === textarea) {
                        textarea.blur();
                    }
                }
            } catch (error) {
                console.error('[Chatbot] Error handling keyboard shortcut:', error);
            }
        };

        // ============================================================================
        // OBSERVERS AND HOOKS
        // ============================================================================

        /**
         * Initializes mutation observer to watch for DOM changes in the messages container
         * @returns {Function} Cleanup function to disconnect the observer
         */
        const initMutationObserver = () => {
            const container = safeGetElement(MESSAGES_CONTAINER_ID);

            if (!container) {
                console.warn('[Chatbot] Cannot init MutationObserver - container not found');
                return () => {};
            }

            const debouncedScroll = debounce(() => scrollToBottom(false), DEBOUNCE_DELAY);

            const observer = new MutationObserver((mutations) => {
                try {
                    // Check if meaningful content was added
                    const hasAddedNodes = mutations.some(mutation =>
                        mutation.addedNodes.length > 0 ||
                        mutation.type === 'characterData'
                    );

                    if (hasAddedNodes) {
                        debouncedScroll();
                    }
                } catch (error) {
                    console.error('[Chatbot] Error in MutationObserver callback:', error);
                }
            });

            try {
                observer.observe(container, {
                    childList: true,
                    subtree: true,
                    characterData: true
                });

                return () => {
                    debouncedScroll.cancel();
                    observer.disconnect();
                };
            } catch (error) {
                console.error('[Chatbot] Error initializing MutationObserver:', error);
                return () => {};
            }
        };

        /**
         * Initializes Livewire hooks for chat functionality
         * @returns {Function} Cleanup function to remove hooks
         */
        const initLivewireHooks = () => {
            if (typeof Livewire === 'undefined') {
                console.warn('[Chatbot] Livewire not available');
                return () => {};
            }

            const hookCleanups = [];

            try {
                // Hook: When Livewire morphs/updates the DOM
                const morphHook = Livewire.hook('morph.updated', ({ el, component }) => {
                    if (!el) return;

                    try {
                        // Check if the updated element is or contains our messages container
                        if (el.id === MESSAGES_CONTAINER_ID || el.querySelector(`#${MESSAGES_CONTAINER_ID}`)) {
                            requestAnimationFrame(() => {
                                scrollToBottom(false);
                                announceMessageUpdate();
                            });
                        }
                    } catch (error) {
                        console.error('[Chatbot] Error in morph.updated hook:', error);
                    }
                });

                hookCleanups.push(() => {
                    if (typeof morphHook === 'function') morphHook();
                });

                // Hook: When Livewire finishes processing a message
                const messageHook = Livewire.hook('message.processed', (message, component) => {
                    try {
                        requestAnimationFrame(() => {
                            scrollToBottom(false);
                            focusMessageInput();
                            initCharacterCount();
                        });
                    } catch (error) {
                        console.error('[Chatbot] Error in message.processed hook:', error);
                    }
                });

                hookCleanups.push(() => {
                    if (typeof messageHook === 'function') messageHook();
                });

                // Hook: When Livewire commits changes
                const commitHook = Livewire.hook('commit', ({ component, commit, respond }) => {
                    try {
                        requestAnimationFrame(() => scrollToBottom(false));
                    } catch (error) {
                        console.error('[Chatbot] Error in commit hook:', error);
                    }
                });

                hookCleanups.push(() => {
                    if (typeof commitHook === 'function') commitHook();
                });

            } catch (error) {
                console.error('[Chatbot] Error setting up Livewire hooks:', error);
            }

            return () => {
                hookCleanups.forEach(cleanup => {
                    try {
                        cleanup();
                    } catch (error) {
                        console.error('[Chatbot] Error during hook cleanup:', error);
                    }
                });
            };
        };

        // ============================================================================
        // INITIALIZATION AND LIFECYCLE
        // ============================================================================

        /**
         * Initializes all chatbot functionality
         * @returns {void}
         */
        const init = () => {
            try {
                // Initial scroll on load (force scroll to show latest messages)
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        scrollToBottom(true);
                        initCharacterCount();
                        focusMessageInput();
                    }, 100);
                });

                // Initialize mutation observer
                const cleanupObserver = initMutationObserver();
                cleanupFunctions.add(cleanupObserver);

                // Initialize Livewire hooks
                const cleanupLivewire = initLivewireHooks();
                cleanupFunctions.add(cleanupLivewire);

                // Add keyboard shortcuts listener
                document.addEventListener('keydown', handleKeyboardShortcuts);
                cleanupFunctions.add(() => {
                    document.removeEventListener('keydown', handleKeyboardShortcuts);
                });

            } catch (error) {
                console.error('[Chatbot] Error during initialization:', error);
            }
        };

        /**
         * Cleanup function to remove all event listeners and observers
         * Prevents memory leaks when component is destroyed
         * @returns {void}
         */
        const cleanup = () => {
            cleanupFunctions.forEach(cleanupFn => {
                try {
                    cleanupFn();
                } catch (error) {
                    console.error('[Chatbot] Error during cleanup:', error);
                }
            });
            cleanupFunctions.clear();
        };

        // ============================================================================
        // BOOTSTRAP
        // ============================================================================

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Livewire !== 'undefined') {
                    init();
                } else {
                    document.addEventListener('livewire:init', init, { once: true });
                }
            }, { once: true });
        } else {
            // DOM already loaded
            if (typeof Livewire !== 'undefined') {
                init();
            } else {
                document.addEventListener('livewire:init', init, { once: true });
            }
        }

        // Handle Livewire navigation (SPA-like behavior)
        document.addEventListener('livewire:navigated', () => {
            cleanup();
            init();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', cleanup, { once: true });

        // Expose cleanup for manual cleanup if needed (useful for testing)
        window.chatbotCleanup = cleanup;

    })();
</script>
@endpush
