{{-- resources/views/livewire/graph/partials/session-manager.blade.php --}}
<div x-data="{ showSaveModal: false, showLoadModal: false, sessionName: '', sessionDescription: '' }" class="relative">
    {{-- Session indicator --}}
    <div class="flex items-center gap-2 mb-4">
        <span class="text-xs text-gray-500">Session:</span>
        <span class="text-xs text-gray-300" x-text="$wire.sessionId ? 'Active #' + $wire.sessionId : 'New'"></span>

        <button
            @click="showSaveModal = true"
            class="text-xs text-blue-400 hover:underline ml-2"
        >
            Save
        </button>
        <button
            @click="showLoadModal = true; $wire.getSavedSessions().then(s => sessions = s)"
            class="text-xs text-gray-400 hover:underline"
        >
            Load
        </button>
    </div>

    {{-- Save Modal --}}
    <div
        x-show="showSaveModal"
        x-transition
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showSaveModal = false"
    >
        <div class="bg-gray-800 rounded-lg p-6 w-96 max-w-full">
            <h3 class="text-lg font-semibold text-white mb-4">Save Research Session</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Session Name</label>
                    <input
                        type="text"
                        x-model="sessionName"
                        class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                        placeholder="e.g., Property Law Research"
                    >
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Description (optional)</label>
                    <textarea
                        x-model="sessionDescription"
                        class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                        rows="2"
                        placeholder="Notes about this research..."
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button
                    @click="showSaveModal = false"
                    class="px-4 py-2 text-sm text-gray-400 hover:text-white"
                >
                    Cancel
                </button>
                <button
                    @click="$wire.saveSession(sessionName, sessionDescription); showSaveModal = false; sessionName = ''; sessionDescription = ''"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded"
                >
                    Save Session
                </button>
            </div>
        </div>
    </div>

    {{-- Load Modal --}}
    <div
        x-show="showLoadModal"
        x-transition
        x-data="{ sessions: [] }"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showLoadModal = false"
    >
        <div class="bg-gray-800 rounded-lg p-6 w-96 max-w-full max-h-96 overflow-y-auto">
            <h3 class="text-lg font-semibold text-white mb-4">Load Research Session</h3>

            <div class="space-y-2">
                <template x-for="session in sessions" :key="session.id">
                    <button
                        @click="$wire.loadSession(session.id); showLoadModal = false"
                        class="w-full text-left p-3 bg-gray-700 hover:bg-gray-600 rounded"
                    >
                        <div class="text-sm text-white" x-text="session.name"></div>
                        <div class="text-xs text-gray-400">
                            <span x-text="session.viewed_nodes?.length || 0"></span> nodes viewed
                            · <span x-text="new Date(session.last_activity_at).toLocaleDateString()"></span>
                        </div>
                    </button>
                </template>

                <div x-show="sessions.length === 0" class="text-sm text-gray-400 text-center py-4">
                    No saved sessions yet
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button
                    @click="showLoadModal = false"
                    class="px-4 py-2 text-sm text-gray-400 hover:text-white"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
