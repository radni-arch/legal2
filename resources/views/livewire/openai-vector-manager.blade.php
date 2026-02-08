<div dusk="vector-manager-container" class="min-h-screen" style="background: var(--bg, #0b1220);">
    {{-- Scoped styles: modern scrollbars + spinner animation --}}
    <style>
        .vm-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .vm-scroll::-webkit-scrollbar-track { background: transparent; }
        .vm-scroll::-webkit-scrollbar-thumb { background: rgb(51 65 85 / 0.6); border-radius: 9999px; }
        .vm-scroll::-webkit-scrollbar-thumb:hover { background: rgb(100 116 139 / 0.8); }
        .vm-scroll { scrollbar-width: thin; scrollbar-color: rgb(51 65 85 / 0.6) transparent; }

        @keyframes vm-spin { to { transform: rotate(360deg); } }
        .vm-spinner { animation: vm-spin 0.7s linear infinite; }
    </style>

    @if($showHeader)
        <x-page-header
            title="OpenAI Vector Stores Manager"
            subtitle="Manage vector embeddings and vector store operations"
            route-name="openai.vectors"
            :show-nav="true"
        />
    @endif

    <div class="container mx-auto max-w-full px-6 py-6">

        <!-- Error Alert -->
        @if($error)
            <div dusk="error-alert" class="p-4 rounded-lg mb-4 flex items-start bg-red-500/10 border border-red-500/30 text-red-300">
                <div dusk="error-icon" class="flex-shrink-0 pt-0.5">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                </div>
                <div dusk="error-content" class="ml-3">
                    <p dusk="error-message" class="text-sm font-medium">{{ $error }}</p>
                </div>
            </div>
        @endif

        <!-- Main Content Grid -->
        <div dusk="main-content-grid" class="grid grid-cols-12 gap-6">

            {{-- ─── Left Panel: Vector Stores (3 cols) ─── --}}
            <div dusk="stores-panel" class="col-span-12 lg:col-span-3">
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-lg shadow-xl">
                    <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                        <div>
                            <h3 dusk="stores-title" class="text-lg font-semibold text-white">Available Stores</h3>
                            <p dusk="stores-count" class="text-xs text-slate-400 mt-1">{{ $storesTotalCount }} store(s)</p>
                        </div>
                        <button wire:click="refreshStores"
                            class="p-1.5 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 transition-colors" title="Refresh stores">
                            <svg wire:loading.remove wire:target="refreshStores" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <svg wire:loading wire:target="refreshStores" class="w-4 h-4 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                    </div>

                    {{-- Store loading overlay --}}
                    <div wire:loading wire:target="selectStore" class="p-8 flex flex-col items-center justify-center gap-3 text-slate-400">
                        <svg class="w-6 h-6 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                        <span class="text-xs">Loading store&hellip;</span>
                    </div>

                    <div wire:loading.remove wire:target="selectStore">
                        @if(empty($stores) && $storesTotalCount === 0)
                            <div dusk="empty-stores-message" class="p-4 text-center">
                                <p dusk="no-stores-text" class="text-sm text-yellow-300">No vector stores available</p>
                            </div>
                        @else
                            <div class="p-2 max-h-[calc(100vh-300px)] overflow-y-auto vm-scroll">
                                <ul dusk="stores-list" class="space-y-1">
                                    @foreach($stores as $index => $store)
                                        <li dusk="store-item-{{ $index }}">
                                            <button
                                                dusk="store-button-{{ $index }}"
                                                wire:click="selectStore('{{ $store['id'] }}')"
                                                class="w-full text-left px-3 py-2 rounded-lg transition-all {{ $selectedStore === $store['id']
                                                    ? 'bg-sky-500/15 border-2 border-sky-500/50 text-sky-300'
                                                    : 'bg-slate-700/50 border-2 border-transparent text-slate-200 hover:bg-slate-700' }}">
                                                <div dusk="store-name-{{ $index }}" class="font-semibold text-sm truncate">{{ $store['name'] ?? $store['id'] }}</div>
                                                <div dusk="store-id-{{ $index }}" class="text-xs opacity-60 truncate mt-0.5 font-mono">{{ Str::limit($store['id'], 20) }}</div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    @if(isset($store['file_counts']['total']))
                                                        <span class="text-xs opacity-60">{{ $store['file_counts']['total'] }} files</span>
                                                    @elseif(isset($store['file_count']))
                                                        <span class="text-xs opacity-60">{{ $store['file_count'] }} files</span>
                                                    @endif
                                                    @if(isset($store['usage_bytes']) && $store['usage_bytes'] > 0)
                                                        <span class="text-xs opacity-50">{{ number_format($store['usage_bytes'] / 1048576, 1) }} MB</span>
                                                    @endif
                                                </div>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            @if($this->storesTotalPages() > 1)
                                <div class="flex items-center justify-between px-4 py-2 border-t border-slate-700">
                                    <button wire:click="storesPreviousPage" @disabled($storesPage <= 1)
                                        class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                        Prev
                                    </button>
                                    <span class="text-xs text-slate-400">{{ $storesPage }} / {{ $this->storesTotalPages() }}</span>
                                    <button wire:click="storesNextPage" @disabled($storesPage >= $this->storesTotalPages())
                                        class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                        Next
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- ─── Middle Panel: Files in Store (4 cols) ─── --}}
            <div dusk="files-panel" class="col-span-12 lg:col-span-4">
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-lg shadow-xl">
                    @if($selectedStore)
                        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                            <div>
                                <h3 dusk="files-title" class="text-lg font-semibold text-white">Files in Store</h3>
                                <p dusk="files-count" class="text-xs text-slate-400 mt-1">{{ $filesTotalCount }} file(s)</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    dusk="build-catalog-button"
                                    wire:click="buildCatalog"
                                    wire:confirm="Build and upload catalog for this vector store? This will process all documents and create a catalog file."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded bg-purple-500/15 hover:bg-purple-500/25 border border-purple-500/30 text-purple-300 transition-colors disabled:opacity-50"
                                    wire:loading.attr="disabled"
                                    wire:target="buildCatalog"
                                    title="Build and upload catalog for this vector store">
                                    <svg wire:loading.remove wire:target="buildCatalog" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <svg wire:loading wire:target="buildCatalog" class="w-4 h-4 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                                    <span wire:loading.remove wire:target="buildCatalog">Build Catalog</span>
                                    <span wire:loading wire:target="buildCatalog">Building...</span>
                                </button>
                                <button wire:click="refreshFiles"
                                    class="p-1.5 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 transition-colors" title="Refresh files">
                                    <svg wire:loading.remove wire:target="refreshFiles" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <svg wire:loading wire:target="refreshFiles" class="w-4 h-4 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Catalog status message --}}
                        @if($catalogStatus)
                            <div dusk="catalog-status" class="px-4 py-2 border-b border-slate-700 text-sm {{ str_contains($catalogStatus, 'failed') ? 'text-red-300 bg-red-500/10' : 'text-purple-300 bg-purple-500/10' }}">
                                {{ $catalogStatus }}
                            </div>
                        @endif

                        {{-- Files loading overlay --}}
                        <div wire:loading wire:target="selectStore, refreshFiles, deleteFile" class="p-8 flex flex-col items-center justify-center gap-3 text-slate-400">
                            <svg class="w-6 h-6 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                            <span class="text-xs">Loading files&hellip;</span>
                        </div>

                        <div wire:loading.remove wire:target="selectStore, refreshFiles, deleteFile">
                            @if(empty($files) && $filesTotalCount === 0)
                                <div dusk="empty-files-message" class="p-4 text-center">
                                    <p dusk="no-files-text" class="text-sm text-yellow-300">No files in this store</p>
                                </div>
                            @else
                                <div class="p-2 max-h-[calc(100vh-300px)] overflow-y-auto vm-scroll">
                                    <ul dusk="files-list" class="space-y-1">
                                        @foreach($files as $index => $file)
                                            <li dusk="file-item-{{ $index }}" class="relative group">
                                                <div class="flex items-stretch">
                                                    <button
                                                        dusk="file-button-{{ $index }}"
                                                        wire:click="selectFile('{{ $file['id'] }}')"
                                                        class="flex-1 text-left px-3 py-2 rounded-l-lg transition-all {{ $selectedFile === $file['id']
                                                            ? 'bg-green-500/15 border-2 border-r-0 border-green-500/50 text-green-300'
                                                            : 'bg-slate-700/50 border-2 border-r-0 border-transparent text-slate-200 hover:bg-slate-700' }}">
                                                        <div dusk="file-name-{{ $index }}" class="font-semibold text-sm truncate">{{ $file['resolved_name'] ?? $file['filename'] ?? $file['id'] }}</div>
                                                        <div dusk="file-id-{{ $index }}" class="text-xs opacity-60 truncate mt-0.5 font-mono">{{ $file['id'] }}</div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            @if(isset($file['status']))
                                                                <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded {{ $file['status'] === 'completed' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400' }}">{{ $file['status'] }}</span>
                                                            @endif
                                                            @if(isset($file['size']))
                                                                <span class="text-xs opacity-60">{{ $file['size'] >= 1048576 ? number_format($file['size'] / 1048576, 1) . ' MB' : number_format($file['size'] / 1024, 1) . ' KB' }}</span>
                                                            @endif
                                                        </div>
                                                    </button>
                                                    <button
                                                        dusk="file-delete-button-{{ $index }}"
                                                        wire:click="deleteFile('{{ $file['id'] }}')"
                                                        wire:confirm="Delete this file? It will be detached from the vector store and permanently deleted from OpenAI."
                                                        class="flex items-center px-2 rounded-r-lg transition-all opacity-0 group-hover:opacity-100 bg-red-500/10 hover:bg-red-500/25 border-2 border-l-0 border-transparent hover:border-red-500/50 text-red-400"
                                                        title="Delete file">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                @if($this->filesTotalPages() > 1)
                                    <div class="flex items-center justify-between px-4 py-2 border-t border-slate-700">
                                        <button wire:click="filesPreviousPage" @disabled($filesPage <= 1)
                                            class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                            Prev
                                        </button>
                                        <span class="text-xs text-slate-400">{{ $filesPage }} / {{ $this->filesTotalPages() }}</span>
                                        <button wire:click="filesNextPage" @disabled($filesPage >= $this->filesTotalPages())
                                            class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                            Next
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @else
                        <div class="p-4 border-b border-slate-700">
                            <h3 class="text-lg font-semibold text-white">Files in Store</h3>
                        </div>
                        <div dusk="select-store-message" class="text-center py-12 text-slate-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            <p dusk="select-store-text" class="text-sm">Select a store to view files</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ─── Right Panel: Metadata & Attributes (5 cols) ─── --}}
            <div dusk="metadata-panel" class="col-span-12 lg:col-span-5">
                <div class="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-lg shadow-xl">
                    @if($selectedFile)
                        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                            <h3 dusk="file-info-title" class="text-lg font-semibold text-white">File Metadata</h3>
                            <div class="flex items-center gap-2">
                                <button
                                    dusk="regenerate-metadata-button"
                                    wire:click="regenerateFile"
                                    wire:confirm="Regenerate attributes? This will re-run LLM analysis on the file to extract fresh metadata (keywords, laws, dates, etc.). This may take a moment."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 transition-colors disabled:opacity-50"
                                    wire:loading.attr="disabled"
                                    wire:target="regenerateFile"
                                    title="Re-run LLM tagging to regenerate file attributes">
                                    <svg wire:loading.remove wire:target="regenerateFile" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <svg wire:loading wire:target="regenerateFile" class="w-3.5 h-3.5 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                                    <span wire:loading.remove wire:target="regenerateFile">Regenerate Attributes</span>
                                    <span wire:loading wire:target="regenerateFile">Regenerating&hellip;</span>
                                </button>
                                <button
                                    dusk="delete-selected-file-button"
                                    wire:click="deleteFile('{{ $selectedFile }}')"
                                    wire:confirm="Delete this file? It will be detached from the vector store and permanently deleted from OpenAI."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded bg-red-500/15 hover:bg-red-500/25 border border-red-500/30 text-red-300 transition-colors disabled:opacity-50"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteFile"
                                    title="Delete file from vector store and OpenAI">
                                    <svg wire:loading.remove wire:target="deleteFile" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <svg wire:loading wire:target="deleteFile" class="w-3.5 h-3.5 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                                    <span wire:loading.remove wire:target="deleteFile">Delete</span>
                                    <span wire:loading wire:target="deleteFile">Deleting&hellip;</span>
                                </button>
                            </div>
                        </div>

                        {{-- Metadata loading overlay --}}
                        <div wire:loading wire:target="selectFile, regenerateFile" class="p-8 flex flex-col items-center justify-center gap-3 text-slate-400">
                            <svg class="w-6 h-6 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                            <span wire:loading wire:target="selectFile" class="text-xs">Loading metadata&hellip;</span>
                            <span wire:loading wire:target="regenerateFile" class="text-xs">Running LLM analysis&hellip;</span>
                        </div>

                        <div wire:loading.remove wire:target="selectFile, regenerateFile" class="max-h-[calc(100vh-280px)] overflow-y-auto vm-scroll p-4">
                            <!-- Metadata Display -->
                            <div dusk="metadata-display-section" class="mb-4">
                                <h4 dusk="metadata-display-label" class="text-sm font-semibold mb-2 text-slate-400">Current Metadata</h4>
                                <pre dusk="metadata-display"
                                    class="p-3 rounded-lg text-xs max-h-48 overflow-auto vm-scroll bg-slate-900/60 border border-slate-600 text-slate-300 font-mono">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>

                            <!-- Attributes Display -->
                            <div dusk="attributes-display-section" class="mb-4">
                                <h4 dusk="attributes-display-label" class="text-sm font-semibold mb-2 text-slate-400">Current Attributes</h4>
                                <pre dusk="attributes-display"
                                    class="p-3 rounded-lg text-xs max-h-48 overflow-auto vm-scroll bg-slate-900/60 border border-slate-600 text-slate-300 font-mono">{{ json_encode($fileAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>

                            <!-- Edit Form -->
                            <form dusk="metadata-form" wire:submit="saveMeta" class="p-4 rounded-lg bg-slate-900/40 border border-slate-600">
                                <h4 dusk="form-title" class="text-sm font-semibold mb-4 text-white">Update Metadata</h4>

                                <div dusk="metadata-input-section" class="mb-4">
                                    <label dusk="metadata-input-label" class="block text-sm font-medium mb-2 text-slate-400">New Metadata (JSON):</label>
                                    <textarea dusk="metadata-input" wire:model.defer="newMetadata"
                                        class="w-full bg-slate-800 border border-slate-600 rounded px-3 py-2 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 font-mono"
                                        rows="3" placeholder='{"key": "value"}'></textarea>
                                </div>

                                <div dusk="attributes-input-section" class="mb-4">
                                    <label dusk="attributes-input-label" class="block text-sm font-medium mb-2 text-slate-400">New Attributes (JSON):</label>
                                    <textarea dusk="attributes-input" wire:model.defer="newAttributes"
                                        class="w-full bg-slate-800 border border-slate-600 rounded px-3 py-2 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 font-mono"
                                        rows="3" placeholder='{"attribute": "value"}'></textarea>
                                </div>

                                <button dusk="save-metadata-button" type="submit"
                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 inline-flex items-center justify-center gap-2"
                                    wire:loading.attr="disabled"
                                    wire:target="saveMeta">
                                    <svg wire:loading wire:target="saveMeta" class="w-4 h-4 vm-spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>
                                    <span wire:loading.remove wire:target="saveMeta">Save Changes</span>
                                    <span wire:loading wire:target="saveMeta">Saving&hellip;</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="p-4 border-b border-slate-700">
                            <h3 class="text-lg font-semibold text-white">File Metadata</h3>
                        </div>
                        <div dusk="select-file-message" class="text-center py-12 text-slate-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p dusk="select-file-text" class="text-sm">Select a file to view and edit metadata</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
