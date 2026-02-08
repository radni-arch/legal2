<div class="vector-store-manager">
    {{-- Header --}}
    <x-page-header
        title="Vector Store Manager"
        subtitle="Manage and browse all vector stores: Laws, Court Decisions, Cases, Textract"
        route-name="vectors.manage"
        :show-nav="true"
    />

    {{-- Messages --}}
    @if ($successMessage)
        <div class="alert alert-success">
            ✓ {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="alert alert-error">
            ✗ {{ $errorMessage }}
        </div>
    @endif

    {{-- Store Selection --}}
    <div class="store-selection">
        @foreach($stores as $key => $store)
            <button
                type="button"
                class="store-btn @if($selectedStore === $key) active @endif"
                wire:click="selectStore('{{ $key }}')"
                wire:loading.attr="disabled"
                wire:target="selectStore"
                dusk="select-store-{{ $key }}"
            >
                <div class="store-name">
                    <span wire:loading.remove wire:target="selectStore">{{ $store['name'] }}</span>
                    <span wire:loading wire:target="selectStore" class="loading-text">
                        <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                </div>
                <div class="store-desc">{{ $store['description'] }}</div>
            </button>
        @endforeach
    </div>

    {{-- Statistics Cards --}}
    @if(!empty($stats))
        <div class="stats-grid" dusk="stats-grid">
            <div class="stat-card" dusk="stat-total-documents">
                <div class="stat-label">Total Documents</div>
                <div class="stat-value stat-accent">{{ number_format($stats['total_documents'] ?? 0) }}</div>
            </div>
            <div class="stat-card" dusk="stat-unique-documents">
                <div class="stat-label">Unique Doc IDs</div>
                <div class="stat-value stat-success">{{ number_format($stats['unique_documents'] ?? 0) }}</div>
            </div>
            <div class="stat-card" dusk="stat-total-tokens">
                <div class="stat-label">Total Tokens</div>
                <div class="stat-value stat-info">{{ number_format($stats['total_tokens'] ?? 0) }}</div>
            </div>
            <div class="stat-card" dusk="stat-avg-tokens">
                <div class="stat-label">Avg Tokens/Doc</div>
                <div class="stat-value stat-warn">{{ $stats['avg_tokens'] ?? 0 }}</div>
            </div>
        </div>
    @endif

    {{-- Search & Actions Bar --}}
    <div class="actions-bar" dusk="actions-bar">
        <div class="search-section">
            <select class="control-input" wire:model="searchType" dusk="search-type-select">
                <option value="content">Content Search</option>
                <option value="similarity">Similarity Search</option>
                <option value="doc_id">Doc ID Search</option>
            </select>
            <input
                type="text"
                class="control-input search-input"
                wire:model="searchQuery"
                placeholder="Enter search query..."
                dusk="search-input"
            >
            <button
                type="button"
                class="btn btn-info"
                wire:click="search"
                wire:loading.attr="disabled"
                wire:target="search"
                dusk="search-button"
            >
                <span wire:loading.remove wire:target="search">🔍 Search</span>
                <span wire:loading wire:target="search">
                    <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </span>
            </button>
            @if($searchQuery)
                <button
                    type="button"
                    class="btn"
                    wire:click="resetSearch"
                    wire:loading.attr="disabled"
                    wire:target="resetSearch"
                    dusk="clear-search-button"
                >
                    <span wire:loading.remove wire:target="resetSearch">Clear</span>
                    <span wire:loading wire:target="resetSearch">Clearing...</span>
                </button>
            @endif
        </div>

        <div class="bulk-actions">
            @if(count($selectedDocuments) > 0)
                <button
                    type="button"
                    class="btn btn-success"
                    wire:click="reindexSelected"
                    wire:loading.attr="disabled"
                    wire:target="reindexSelected"
                    dusk="reindex-selected-button"
                >
                    <span wire:loading.remove wire:target="reindexSelected">
                        🔄 Re-index ({{ count($selectedDocuments) }})
                    </span>
                    <span wire:loading wire:target="reindexSelected">
                        <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    wire:click="deleteSelected"
                    wire:confirm="Are you sure you want to delete {{ count($selectedDocuments) }} documents?"
                    wire:loading.attr="disabled"
                    wire:target="deleteSelected"
                    dusk="delete-selected-button"
                >
                    <span wire:loading.remove wire:target="deleteSelected">
                        🗑️ Delete ({{ count($selectedDocuments) }})
                    </span>
                    <span wire:loading wire:target="deleteSelected">
                        <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Deleting...
                    </span>
                </button>
            @endif
            <button
                type="button"
                class="btn"
                wire:click="refreshStats"
                wire:loading.attr="disabled"
                wire:target="refreshStats"
                dusk="refresh-stats-button"
            >
                <span wire:loading.remove wire:target="refreshStats">📊 Refresh Stats</span>
                <span wire:loading wire:target="refreshStats">
                    <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Refreshing...
                </span>
            </button>
        </div>
    </div>

    {{-- Documents List --}}
    <div class="documents-card" dusk="documents-card">
        <div class="section-header" dusk="section-header">
            @if($showingSearchResults)
                🔍 Search Results ({{ count($searchResults) }} found)
            @else
                📄 Documents ({{ number_format($totalDocuments) }} total)
            @endif
        </div>

        {{-- Select All --}}
        @if(!$showingSearchResults && count($documents) > 0)
            <div class="select-all-section">
                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        wire:model="selectAll"
                        wire:click="toggleSelectAll"
                        dusk="select-all-checkbox"
                    >
                    <span>Select All</span>
                </label>
            </div>
        @endif

        {{-- Documents Table --}}
        @if($showingSearchResults && count($searchResults) > 0)
            {{-- Similarity Search Results --}}
            <div class="results-table-wrapper" style="position: relative;">
                {{-- Loading Overlay --}}
                <div wire:loading wire:target="search" class="table-loading-overlay">
                    <div class="loading-spinner">
                        <svg class="animate-spin h-12 w-12 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="loading-text">Loading results...</p>
                    </div>
                </div>

                <div class="results-table" dusk="search-results-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Doc ID</th>
                                <th>Content Preview</th>
                                <th style="width: 100px;">Similarity</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searchResults as $result)
                                <tr dusk="search-result-row-{{ $loop->index }}">
                                    <td>
                                        <span class="doc-id">{{ $result['doc_id'] ?? $result['id'] }}</span>
                                    </td>
                                    <td>
                                        <div class="content-preview">
                                            {{ \Str::limit($result['content'] ?? '', 200) }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="similarity-score">
                                            {{ isset($result['similarity']) ? number_format($result['similarity'], 3) : 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn-small btn-info"
                                            wire:click="previewDocument('{{ $result['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="previewDocument"
                                            dusk="preview-document-{{ $result['id'] }}"
                                        >
                                            <span wire:loading.remove wire:target="previewDocument">👁️ View</span>
                                            <span wire:loading wire:target="previewDocument" class="inline-flex items-center">
                                                <svg class="animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif(count($documents) > 0)
            {{-- Regular Documents List --}}
            <div class="results-table-wrapper" style="position: relative;">
                {{-- Loading Overlay --}}
                <div wire:loading wire:target="selectStore,refreshStats,nextPage,previousPage,reindexDocument,deleteDocument,reindexSelected,deleteSelected" class="table-loading-overlay">
                    <div class="loading-spinner">
                        <svg class="animate-spin h-12 w-12 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="loading-text">Loading documents...</p>
                    </div>
                </div>

                <div class="results-table" dusk="document-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">Select</th>
                                <th style="width: 200px;">Doc ID</th>
                                <th>Content Preview</th>
                                <th style="width: 80px;">Chunk</th>
                                <th style="width: 120px;">Model</th>
                                <th style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $doc)
                                <tr dusk="document-row-{{ $doc->id }}">
                                    <td>
                                        <input
                                            type="checkbox"
                                            value="{{ $doc->id }}"
                                            wire:click="toggleSelection('{{ $doc->id }}')"
                                            @if(in_array($doc->id, $selectedDocuments)) checked @endif
                                            dusk="select-document-{{ $doc->id }}"
                                        >
                                    </td>
                                    <td>
                                        <span class="doc-id" dusk="doc-id-{{ $doc->id }}">{{ $doc->doc_id }}</span>
                                    </td>
                                    <td>
                                        <div class="content-preview">
                                            {{ $doc->content_preview }}
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $doc->chunk_index ?? 0 }}</td>
                                    <td>
                                        <span class="model-badge">{{ \Str::limit($doc->embedding_model ?? 'N/A', 20) }}</span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn-small btn-info"
                                            wire:click="previewDocument('{{ $doc->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="previewDocument"
                                            dusk="preview-document-{{ $doc->id }}"
                                        >
                                            <span wire:loading.remove wire:target="previewDocument">👁️</span>
                                            <span wire:loading wire:target="previewDocument">⏳</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-small btn-success"
                                            wire:click="reindexDocument('{{ $doc->id }}')"
                                            wire:confirm="Re-index this document?"
                                            wire:loading.attr="disabled"
                                            wire:target="reindexDocument"
                                            dusk="reindex-document-{{ $doc->id }}"
                                        >
                                            <span wire:loading.remove wire:target="reindexDocument">🔄</span>
                                            <span wire:loading wire:target="reindexDocument">⏳</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-small btn-danger"
                                            wire:click="deleteDocument('{{ $doc->id }}')"
                                            wire:confirm="Delete this document?"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteDocument"
                                            dusk="delete-document-{{ $doc->id }}"
                                        >
                                            <span wire:loading.remove wire:target="deleteDocument">🗑️</span>
                                            <span wire:loading wire:target="deleteDocument">⏳</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if($totalPages > 1)
                <div class="pagination" dusk="pagination">
                    <button
                        type="button"
                        class="btn-small"
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        wire:target="previousPage"
                        @if($currentPage <= 1) disabled @endif
                        dusk="prev-page-button"
                    >
                        <span wire:loading.remove wire:target="previousPage">← Previous</span>
                        <span wire:loading wire:target="previousPage">
                            <svg class="inline animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>

                    <span class="page-info" dusk="page-info">
                        Page {{ $currentPage }} of {{ $totalPages }}
                    </span>

                    <button
                        type="button"
                        class="btn-small"
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        wire:target="nextPage"
                        @if($currentPage >= $totalPages) disabled @endif
                        dusk="next-page-button"
                    >
                        <span wire:loading.remove wire:target="nextPage">Next →</span>
                        <span wire:loading wire:target="nextPage">
                            <svg class="inline animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading...
                        </span>
                    </button>
                </div>
            @endif
        @else
            <div class="empty-state-card">
                <div class="empty-icon">📭</div>
                <div class="empty-text">No documents found</div>
            </div>
        @endif
    </div>

    {{-- Preview Modal --}}
    @if($showPreviewModal && $previewDocumentData)
        <div
            class="modal-overlay"
            wire:click="closePreview"
            dusk="preview-modal"
            x-data
            x-init="$el.focus()"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div
                class="modal-content"
                wire:click.stop
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
            >
                <div class="modal-header">
                    <h2>📄 Document Details</h2>
                    <button
                        type="button"
                        class="modal-close"
                        wire:click="closePreview"
                        dusk="modal-close-button"
                    >✕</button>
                </div>

                <div class="modal-body">
                    <div class="preview-grid">
                        <div class="preview-field">
                            <div class="preview-label">Document ID</div>
                            <div class="preview-value" dusk="preview-document-id">{{ $previewDocumentData['id'] ?? 'N/A' }}</div>
                        </div>

                        <div class="preview-field">
                            <div class="preview-label">Doc ID</div>
                            <div class="preview-value" dusk="preview-doc-id">{{ $previewDocumentData['doc_id'] ?? 'N/A' }}</div>
                        </div>

                        <div class="preview-field">
                            <div class="preview-label">Chunk Index</div>
                            <div class="preview-value" dusk="preview-chunk-index">{{ $previewDocumentData['chunk_index'] ?? 0 }}</div>
                        </div>

                        <div class="preview-field">
                            <div class="preview-label">Token Count</div>
                            <div class="preview-value" dusk="preview-token-count">{{ number_format($previewDocumentData['token_count'] ?? 0) }}</div>
                        </div>

                        <div class="preview-field">
                            <div class="preview-label">Embedding Model</div>
                            <div class="preview-value" dusk="preview-embedding-model">{{ $previewDocumentData['embedding_model'] ?? 'N/A' }}</div>
                        </div>

                        <div class="preview-field">
                            <div class="preview-label">Created At</div>
                            <div class="preview-value" dusk="preview-created-at">{{ $previewDocumentData['created_at'] ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="preview-field full-width">
                        <div class="preview-label">Content</div>
                        <div class="preview-content" dusk="preview-content">
                            {{ $previewDocumentData['content'] ?? $previewDocumentData['extracted_text'] ?? 'No content available' }}
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-success"
                        wire:click="reindexDocument('{{ $previewDocumentData['id'] }}')"
                        wire:confirm="Re-index this document?"
                        wire:loading.attr="disabled"
                        wire:target="reindexDocument"
                        dusk="modal-reindex-button"
                    >
                        <span wire:loading.remove wire:target="reindexDocument">🔄 Re-index</span>
                        <span wire:loading wire:target="reindexDocument">
                            <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-danger"
                        wire:click="deleteDocument('{{ $previewDocumentData['id'] }}')"
                        wire:confirm="Delete this document permanently?"
                        wire:loading.attr="disabled"
                        wire:target="deleteDocument"
                        dusk="modal-delete-button"
                    >
                        <span wire:loading.remove wire:target="deleteDocument">🗑️ Delete</span>
                        <span wire:loading wire:target="deleteDocument">
                            <svg class="inline animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Deleting...
                        </span>
                    </button>
                    <button
                        type="button"
                        class="btn"
                        wire:click="closePreview"
                        dusk="modal-close-footer-button"
                    >Close</button>
                </div>
            </div>
        </div>
    @endif

    <style>
    .vector-store-manager {
    padding: 1.5rem;
    background: #0a0e1a;
    color: #e0e6ed;
    min-height: 100vh;
}

.header {
    margin-bottom: 2rem;
}

.title {
    font-size: 2rem;
    font-weight: 700;
    color: #f0f6fc;
    margin-bottom: 0.5rem;
}

.subtitle {
    color: #8b949e;
    font-size: 1rem;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-success {
    background: rgba(63, 185, 80, 0.1);
    border: 1px solid rgba(63, 185, 80, 0.3);
    color: #3fb950;
}

.alert-error {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.3);
    color: #f85149;
}

.store-selection {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.store-btn {
    background: #161b22;
    border: 2px solid #30363d;
    border-radius: 6px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.store-btn:hover {
    border-color: #58a6ff;
    background: #1c2128;
}

.store-btn.active {
    border-color: #58a6ff;
    background: rgba(88, 166, 255, 0.1);
}

.store-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #f0f6fc;
    margin-bottom: 0.25rem;
}

.store-desc {
    font-size: 0.875rem;
    color: #8b949e;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #8b949e;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
}

.stat-accent { color: #58a6ff; }
.stat-success { color: #3fb950; }
.stat-info { color: #a371f7; }
.stat-warn { color: #d29922; }

.actions-bar {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.search-section {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.search-input {
    flex: 1;
}

.bulk-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.control-input {
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: #e0e6ed;
    font-size: 0.875rem;
}

.control-input:focus {
    outline: none;
    border-color: #58a6ff;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #30363d;
    background: #21262d;
    color: #e0e6ed;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn:hover {
    background: #30363d;
}

.btn-info {
    background: #1f6feb;
    border-color: #1f6feb;
}

.btn-info:hover {
    background: #388bfd;
}

.btn-success {
    background: #238636;
    border-color: #238636;
}

.btn-success:hover {
    background: #2ea043;
}

.btn-danger {
    background: #da3633;
    border-color: #da3633;
}

.btn-danger:hover {
    background: #f85149;
}

.btn-small {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.documents-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1.5rem;
}

.section-header {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #f0f6fc;
}

.select-all-section {
    margin-bottom: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #e0e6ed;
    cursor: pointer;
}

.results-table {
    overflow-x: auto;
}

.results-table table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th {
    text-align: left;
    padding: 0.75rem;
    background: #0d1117;
    border-bottom: 2px solid #30363d;
    color: #8b949e;
    font-size: 0.875rem;
    font-weight: 600;
}

.results-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #21262d;
}

.results-table tr:hover {
    background: #161b22;
}

.doc-id {
    color: #58a6ff;
    font-weight: 500;
    font-size: 0.875rem;
}

.content-preview {
    color: #8b949e;
    font-size: 0.875rem;
    max-width: 500px;
}

.model-badge {
    background: rgba(163, 113, 247, 0.1);
    color: #a371f7;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.similarity-score {
    color: #3fb950;
    font-weight: 600;
}

.text-center {
    text-align: center;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-top: 1.5rem;
}

.page-info {
    color: #8b949e;
    font-size: 0.875rem;
}

.empty-state-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 3rem;
    text-align: center;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-text {
    color: #8b949e;
    font-size: 1.125rem;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #30363d;
}

.modal-header h2 {
    color: #f0f6fc;
    font-size: 1.5rem;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
}

.modal-close:hover {
    color: #f0f6fc;
}

.modal-body {
    padding: 1.5rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.preview-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.preview-field.full-width {
    grid-column: 1 / -1;
}

.preview-label {
    font-size: 0.875rem;
    color: #8b949e;
    font-weight: 600;
}

.preview-value {
    color: #e0e6ed;
    font-size: 1rem;
}

.preview-content {
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 1rem;
    color: #e0e6ed;
    font-size: 0.875rem;
    line-height: 1.6;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #30363d;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Table Loading Overlay */
.table-loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(10, 14, 26, 0.85);
    backdrop-filter: blur(4px);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.loading-spinner svg {
    color: #58a6ff;
}

.loading-spinner .loading-text {
    color: #e0e6ed;
    font-size: 1rem;
    font-weight: 500;
}

/* Inline Utilities */
.inline {
    display: inline;
}

.inline-flex {
    display: inline-flex;
}

.items-center {
    align-items: center;
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.h-3 {
    height: 0.75rem;
}

.w-3 {
    width: 0.75rem;
}

.h-4 {
    height: 1rem;
}

.w-4 {
    width: 1rem;
}

.h-12 {
    height: 3rem;
}

.w-12 {
    width: 3rem;
}

.mr-1 {
    margin-right: 0.25rem;
}

.mr-2 {
    margin-right: 0.5rem;
}

.text-blue-500 {
    color: #58a6ff;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .vector-store-manager {
        padding: 1rem;
    }

    .title {
        font-size: 1.5rem;
    }

    .store-selection {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .actions-bar {
        padding: 1rem;
    }

    .search-section {
        flex-direction: column;
        align-items: stretch;
    }

    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .results-table {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .results-table table {
        min-width: 600px;
    }

    .preview-grid {
        grid-template-columns: 1fr;
    }

    .modal-content {
        max-width: 100%;
        margin: 0.5rem;
    }

    .modal-footer {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 1.5rem;
    }
}
    </style>
</div>
