{{-- resources/views/components/toast-notification.blade.php --}}
<div
    x-data="{
        notifications: [],
        add(notification) {
            const id = Date.now();
            this.notifications.push({ id, ...notification });
            setTimeout(() => this.remove(id), notification.duration || 5000);
        },
        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }"
    @graph-notification.window="add($event.detail)"
    class="fixed top-4 right-4 z-50 space-y-2"
>
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="true"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="max-w-sm w-full bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
        >
            <div class="p-4">
                <div class="flex items-start">
                    {{-- Icon --}}
                    <div class="flex-shrink-0">
                        <svg
                            x-show="notification.type === 'success'"
                            class="h-6 w-6 text-green-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg
                            x-show="notification.type === 'info'"
                            class="h-6 w-6 text-blue-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    {{-- Content --}}
                    <div class="ml-3 w-0 flex-1">
                        <p class="text-sm font-medium text-gray-100" x-text="notification.title"></p>
                        <p class="mt-1 text-sm text-gray-400" x-text="notification.message"></p>

                        {{-- Action button --}}
                        <div x-show="notification.action" class="mt-3">
                            <button
                                @click="notification.action?.callback(); remove(notification.id)"
                                class="text-sm font-medium text-blue-400 hover:text-blue-300"
                                x-text="notification.action?.label || 'Refresh'"
                            ></button>
                        </div>
                    </div>

                    {{-- Close button --}}
                    <div class="ml-4 flex-shrink-0 flex">
                        <button
                            @click="remove(notification.id)"
                            class="rounded-md inline-flex text-gray-400 hover:text-gray-300 focus:outline-none"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
