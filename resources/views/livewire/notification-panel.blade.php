<div class="relative" x-data="{ userId: {{ $this->userId }} }">
    {{-- Notification Bell Button --}}
    <button
        wire:click="toggle"
        class="relative p-2 rounded-lg focus:outline-none transition-colors duration-200"
        style="color: var(--muted, #94a3b8);"
        onmouseover="this.style.color='var(--fg, #e5e7eb)'"
        onmouseout="this.style.color='var(--muted, #94a3b8)'"
        aria-label="Notifications"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- Unread Badge --}}
        @if($this->unreadCount > 0 || count($liveNotifications) > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 rounded-full" style="background: #ef4444;">
                {{ $this->unreadCount + count(array_filter($liveNotifications, fn($n) => $n['status'] === 'in_progress')) }}
            </span>
        @endif
    </button>

    {{-- Slide-out Panel --}}
    <div
        x-show="$wire.isOpen"
        x-transition:enter="transform transition ease-in-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.away="$wire.close()"
        class="fixed inset-y-0 right-0 w-96 shadow-xl z-50 overflow-hidden flex flex-col"
        style="display: none; background: var(--surface, #0f172a); border-left: 1px solid var(--border, #1f2937);"
    >
        {{-- Header --}}
        <div class="px-4 py-3 flex items-center justify-between" style="background: var(--bg, #0b1220); border-bottom: 1px solid var(--border, #1f2937);">
            <h2 class="text-lg font-semibold" style="color: var(--fg, #e5e7eb);">Notifications</h2>
            <div class="flex items-center space-x-2">
                @if($this->unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        class="text-sm hover:underline"
                        style="color: var(--accent, #3b82f6);"
                    >
                        Mark all read
                    </button>
                @endif
                <button
                    wire:click="close"
                    class="p-1 rounded transition-colors"
                    style="color: var(--muted, #94a3b8);"
                    onmouseover="this.style.color='var(--fg, #e5e7eb)'"
                    onmouseout="this.style.color='var(--muted, #94a3b8)'"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto">
            {{-- Live Notifications Section --}}
            @if(count($liveNotifications) > 0)
                <div class="px-4 py-2" style="background: rgba(59, 130, 246, 0.1); border-bottom: 1px solid var(--border, #1f2937);">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium" style="color: #93c5fd;">Active Jobs</span>
                        <button
                            wire:click="clearCompleted"
                            class="text-xs hover:underline"
                            style="color: var(--accent, #3b82f6);"
                        >
                            Clear completed
                        </button>
                    </div>
                </div>
                <div>
                    @foreach($liveNotifications as $notification)
                        <div class="px-4 py-3 transition-colors" style="border-bottom: 1px solid var(--border, #1f2937); {{ $notification['status'] === 'failed' ? 'background: rgba(239, 68, 68, 0.1);' : '' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" style="color: var(--fg, #e5e7eb);">
                                        {{ $notification['job_name'] }}
                                    </p>
                                    <p class="text-xs" style="color: var(--muted, #94a3b8);">
                                        {{ $notification['job_type'] }}
                                    </p>
                                </div>
                                <button
                                    wire:click="dismissLiveNotification('{{ $notification['job_id'] }}')"
                                    class="ml-2 p-1 rounded transition-colors"
                                    style="color: var(--muted, #94a3b8);"
                                    onmouseover="this.style.color='var(--fg, #e5e7eb)'"
                                    onmouseout="this.style.color='var(--muted, #94a3b8)'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Status Badge --}}
                            <div class="mt-2 flex items-center">
                                @if($notification['status'] === 'in_progress')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd;">
                                        <svg class="animate-spin -ml-0.5 mr-1.5 h-3 w-3" style="color: #60a5fa;" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $notification['stage'] }}
                                    </span>
                                @elseif($notification['status'] === 'started')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(234, 179, 8, 0.2); color: #fde047;">
                                        Starting...
                                    </span>
                                @elseif($notification['status'] === 'completed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(34, 197, 94, 0.2); color: #86efac;">
                                        Completed
                                    </span>
                                @elseif($notification['status'] === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;">
                                        Failed
                                    </span>
                                @endif
                            </div>

                            {{-- Progress Bar --}}
                            @if($notification['status'] === 'in_progress' && isset($notification['progress']))
                                <div class="mt-2">
                                    <div class="flex items-center justify-between text-xs mb-1" style="color: var(--muted, #94a3b8);">
                                        <span>{{ $notification['current_item'] ?? $notification['stage'] }}</span>
                                        <span>{{ $notification['progress'] }}%</span>
                                    </div>
                                    <div class="w-full rounded-full h-1.5" style="background: var(--border, #1f2937);">
                                        <div
                                            class="h-1.5 rounded-full transition-all duration-300"
                                            style="width: {{ $notification['progress'] }}%; background: var(--accent, #3b82f6);"
                                        ></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Error Message --}}
                            @if($notification['status'] === 'failed' && isset($notification['error']))
                                <p class="mt-2 text-xs truncate" style="color: #fca5a5;" title="{{ $notification['error'] }}">
                                    {{ $notification['error'] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Persisted Notifications Section --}}
            @if($this->persistedNotifications->isNotEmpty())
                <div class="px-4 py-2" style="background: var(--bg, #0b1220); border-bottom: 1px solid var(--border, #1f2937); border-top: 1px solid var(--border, #1f2937);">
                    <span class="text-sm font-medium" style="color: var(--muted, #94a3b8);">History</span>
                </div>
                <div>
                    @foreach($this->persistedNotifications as $notification)
                        <div
                            wire:click="markAsRead('{{ $notification->id }}')"
                            class="px-4 py-3 cursor-pointer transition-colors"
                            style="border-bottom: 1px solid var(--border, #1f2937); {{ !$notification->read ? 'background: rgba(59, 130, 246, 0.05);' : '' }}"
                        >
                            <div class="flex items-start">
                                @if(!$notification->read)
                                    <span class="flex-shrink-0 w-2 h-2 mt-2 mr-2 rounded-full" style="background: var(--accent, #3b82f6);"></span>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" style="color: var(--fg, #e5e7eb);">
                                        {{ $notification->job_name }}
                                    </p>
                                    <div class="mt-1 flex items-center space-x-2">
                                        @if($notification->status === 'completed')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(34, 197, 94, 0.2); color: #86efac;">
                                                Completed
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;">
                                                Failed
                                            </span>
                                        @endif
                                        <span class="text-xs" style="color: var(--muted, #94a3b8);">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    @if($notification->status === 'failed' && $notification->error)
                                        <p class="mt-1 text-xs truncate" style="color: #fca5a5;">
                                            {{ $notification->error }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Empty State --}}
            @if(count($liveNotifications) === 0 && $this->persistedNotifications->isEmpty())
                <div class="flex flex-col items-center justify-center py-12" style="color: var(--muted, #94a3b8);">
                    <svg class="w-12 h-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-sm">No notifications yet</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Backdrop --}}
    <div
        x-show="$wire.isOpen"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$wire.close()"
        class="fixed inset-0 z-40"
        style="display: none; background: rgba(0, 0, 0, 0.5);"
    ></div>
</div>
