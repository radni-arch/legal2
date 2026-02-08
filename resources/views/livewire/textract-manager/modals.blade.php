{{-- Job Details Modal --}}
@if($selectedJobId && $selectedJobData)
    <div class="modal-backdrop" wire:click.self="closeJobDetails" x-data x-transition>
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <span class="modal-icon">👁️</span>
                    <h2 class="modal-title">Job Details: {{ Str::limit($selectedJobData['drive_file_name'] ?? ('#'.$selectedJobId), 50) }}</h2>
                </div>
                <button type="button" class="btn btn-sm btn-ghost modal-close" wire:click="closeJobDetails" wire:loading.attr="disabled" wire:target="closeJobDetails">
                    <svg class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Close
                </button>
            </div>

            <div class="modal-body">
                <div class="metadata-item">
                    <strong>Drive File ID:</strong>
                    <code>{{ $selectedJobData['drive_file_id'] ?? '' }}</code>
                </div>
                @if(!empty($selectedJobData['case_label']))
                    <div class="metadata-item">
                        <strong>Assigned Case:</strong>
                        <span class="badge badge-info">📁 {{ $selectedJobData['case_label'] }}</span>
                    </div>
                @endif

                <div class="sync-grid">
                    <div class="sync-card">
                        <h3>📁 Local Files</h3>
                        <ul class="file-list">
                            <li class="file-item">
                                <span class="file-name">Textract JSON</span>
                                <span class="file-meta">{{ ($selectedJobData['has_textract_json'] ?? false) ? 'Present' : 'Missing' }}</span>
                                <span class="file-size">
                                    @if(!empty($selectedJobData['textract_json_size']))
                                        {{ number_format(($selectedJobData['textract_json_size']/1024), 1) }} KB
                                    @else
                                        0 KB
                                    @endif
                                </span>
                            </li>
                            <li class="file-item">
                                <span class="file-name">Searchable PDF</span>
                                <span class="file-meta">{{ ($selectedJobData['has_reconstructed_pdf'] ?? false) ? 'Present' : 'Missing' }}</span>
                                <span class="file-size">
                                    @if(!empty($selectedJobData['reconstructed_pdf_size']))
                                        {{ number_format(($selectedJobData['reconstructed_pdf_size']/1024/1024), 2) }} MB
                                    @else
                                        0 MB
                                    @endif
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="sync-card">
                        <h3>☁️ S3 Objects</h3>
                        @if(isset($selectedJobData['s3_error']))
                            <div class="alert alert-error">
                                <strong>S3 Error:</strong> {{ $selectedJobData['s3_error'] }}
                            </div>
                        @endif
                        <ul class="file-list">
                            <li class="file-item">
                                <span class="file-name">Input PDF</span>
                                <span class="file-meta">{{ ($selectedJobData['has_s3_input'] ?? false) ? 'Present' : 'Missing' }}</span>
                            </li>
                            <li class="file-item">
                                <span class="file-name">Textract JSON</span>
                                <span class="file-meta">{{ ($selectedJobData['has_s3_json'] ?? false) ? 'Present' : 'Missing' }}</span>
                            </li>
                            <li class="file-item">
                                <span class="file-name">Searchable PDF</span>
                                <span class="file-meta">{{ ($selectedJobData['has_s3_output'] ?? false) ? 'Present' : 'Missing' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="job-metadata" style="margin-top:12px;">
                    @if(!empty($selectedJobData['job_id']))
                        <div><strong>Textract Job ID:</strong> <code>{{ Str::limit($selectedJobData['job_id'], 70) }}</code></div>
                    @endif
                    @if(!empty($selectedJobData['s3_key']))
                        <div><strong>S3 Input Key:</strong> <code>{{ Str::limit($selectedJobData['s3_key'], 120) }}</code></div>
                    @endif
                    <div><strong>Status:</strong> <span class="badge">{{ ucfirst($selectedJobData['status'] ?? 'unknown') }}</span></div>
                    @if(!empty($selectedJobData['error']))
                        <div class="alert alert-error" style="margin-top:10px;">
                            <strong>Last Error:</strong> {{ Str::limit($selectedJobData['error'], 300) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Content View Modal --}}
@if($showContentViewModal && $viewingContent)
    <div class="modal-backdrop" wire:click.self="closeViewModal" x-data x-transition>
        <div class="modal" dusk="content-modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <span class="modal-icon">👁️</span>
                    <h2 class="modal-title">{{ Str::limit($viewingContent['drive_file_name'], 45) }}</h2>
                </div>
                <div class="modal-header-actions">
                    <button type="button" class="btn btn-sm btn-primary" wire:click="editContent({{ $viewingContent['id'] }})" wire:loading.attr="disabled" wire:target="editContent({{ $viewingContent['id'] }})">
                        <svg wire:loading.remove wire:target="editContent({{ $viewingContent['id'] }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <svg wire:loading wire:target="editContent({{ $viewingContent['id'] }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                            <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                        </svg>
                        <span wire:loading.remove wire:target="editContent({{ $viewingContent['id'] }})">Edit</span>
                        <span wire:loading wire:target="editContent({{ $viewingContent['id'] }})">Loading...</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost modal-close" wire:click="closeViewModal" wire:loading.attr="disabled" wire:target="closeViewModal">
                        <svg class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Close
                    </button>
                </div>
            </div>

            <div class="modal-tabs" dusk="content-tabs">
                <button class="tab-btn {{ $contentTab === 'content' ? 'active' : '' }}" wire:click="$set('contentTab', 'content')">📄 Content</button>
                <button class="tab-btn {{ $contentTab === 'metadata' ? 'active' : '' }}" wire:click="$set('contentTab', 'metadata')">📊 Metadata</button>
                <button class="tab-btn {{ $contentTab === 'sync' ? 'active' : '' }}" wire:click="$set('contentTab', 'sync')">🔄 Sync Status</button>
            </div>

            <div class="modal-body">
                @if($contentTab === 'content')
                    @if($viewingContent['manually_edited'])
                        <div class="alert alert-info">
                            <strong>✏️ Manually Edited</strong> by {{ $viewingContent['edited_by_name'] ?? 'Unknown' }}
                            at {{ $viewingContent['content_edited_at'] ?? 'Unknown time' }}
                        </div>
                    @endif

                    @php
                        $content = $viewingContent['effective_content'] ?? '';
                        $stats = [
                            'words' => str_word_count($content),
                            'chars' => mb_strlen($content),
                            'lines' => substr_count($content, "\n") + 1,
                            'chunks' => $viewingContent['document_count'] ?? 0
                        ];
                    @endphp
                    <div class="content-stats">
                        <div class="stat-box"><strong>{{ number_format($stats['words']) }}</strong><span>Words</span></div>
                        <div class="stat-box"><strong>{{ number_format($stats['chars']) }}</strong><span>Characters</span></div>
                        <div class="stat-box"><strong>{{ number_format($stats['lines']) }}</strong><span>Lines</span></div>
                        <div class="stat-box"><strong>{{ $stats['chunks'] }}</strong><span>Chunks</span></div>
                    </div>

                    <div class="content-display">
                        <h3>{{ $viewingContent['manually_edited'] ? 'Edited Content' : 'Extracted Content' }}</h3>
                        <pre class="content-text">{{ $viewingContent['effective_content'] ?? 'No content available' }}</pre>
                    </div>

                    @if($viewingContent['manually_edited'] && $viewingContent['extracted_content'])
                        <div class="content-display">
                            <h3>Original Extracted Content (OCR)</h3>
                            <pre class="content-text faded">{{ $viewingContent['extracted_content'] }}</pre>
                        </div>
                    @endif
                @endif

                @if($contentTab === 'metadata')
                    @if($viewingContent['metadata'])
                        <pre class="json-display">{{ json_encode($viewingContent['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <div class="empty-state">No metadata available</div>
                    @endif

                    @if($viewingContent['case_label'])
                        <div class="metadata-item">
                            <strong>Assigned Case:</strong>
                            <span class="badge badge-info">📁 {{ $viewingContent['case_label'] }}</span>
                        </div>
                    @endif
                @endif

                @if($contentTab === 'sync')
                    <div class="sync-grid">
                        <div class="sync-card">
                            <h3>🔢 Embedding Status</h3>
                            @php $embStatus = $viewingContent['embedding_status']; @endphp
                            <span class="badge badge-large badge-{{ match($embStatus) { 'pending' => 'warn', 'processing' => 'info', 'synced' => 'success', 'failed' => 'error', default => '' } }}">
                                <span class="badge-icon">{{ match($embStatus) { 'pending' => '⏳', 'processing' => '⚙️', 'synced' => '✅', 'failed' => '❌', default => '•' } }}</span>
                                {{ ucfirst($embStatus) }}
                            </span>
                            @if($viewingContent['embedding_synced_at'])
                                <div class="sync-time">Last synced: {{ $viewingContent['embedding_synced_at'] }}</div>
                            @endif
                            <button type="button" class="btn btn-primary btn-sm" dusk="regenerate-embeddings-{{ $viewingContent['id'] }}" wire:click="regenerateEmbeddings({{ $viewingContent['id'] }})" wire:loading.attr="disabled" wire:target="regenerateEmbeddings({{ $viewingContent['id'] }})">
                                <svg wire:loading.remove wire:target="regenerateEmbeddings({{ $viewingContent['id'] }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg wire:loading wire:target="regenerateEmbeddings({{ $viewingContent['id'] }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                </svg>
                                <span wire:loading.remove wire:target="regenerateEmbeddings({{ $viewingContent['id'] }})">Regenerate</span>
                                <span wire:loading wire:target="regenerateEmbeddings({{ $viewingContent['id'] }})">Queueing...</span>
                            </button>
                        </div>

                        <div class="sync-card">
                            <h3>📈 Graph Sync Status</h3>
                            @php $graphStatus = $viewingContent['graph_sync_status']; @endphp
                            <span class="badge badge-large badge-{{ match($graphStatus) { 'pending' => 'warn', 'processing' => 'info', 'synced' => 'success', 'failed' => 'error', default => '' } }}">
                                <span class="badge-icon">{{ match($graphStatus) { 'pending' => '⏳', 'processing' => '⚙️', 'synced' => '✅', 'failed' => '❌', default => '•' } }}</span>
                                {{ ucfirst($graphStatus) }}
                            </span>
                            @if($viewingContent['graph_synced_at'])
                                <div class="sync-time">Last synced: {{ $viewingContent['graph_synced_at'] }}</div>
                            @endif
                            <button type="button" class="btn btn-primary btn-sm" dusk="sync-to-graph-{{ $viewingContent['id'] }}" wire:click="syncToGraph({{ $viewingContent['id'] }})" wire:loading.attr="disabled" wire:target="syncToGraph({{ $viewingContent['id'] }})">
                                <svg wire:loading.remove wire:target="syncToGraph({{ $viewingContent['id'] }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg wire:loading wire:target="syncToGraph({{ $viewingContent['id'] }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                </svg>
                                <span wire:loading.remove wire:target="syncToGraph({{ $viewingContent['id'] }})">Sync to Graph</span>
                                <span wire:loading wire:target="syncToGraph({{ $viewingContent['id'] }})">Queueing...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

{{-- Content Edit Modal --}}
@if($showContentEditModal && $editingContent)
    <div class="modal-backdrop" wire:click.self="closeEditModal" x-data x-transition>
        <div class="modal modal-large" dusk="edit-content-modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <span class="modal-icon">✏️</span>
                    <h2 class="modal-title">Edit Content: {{ Str::limit($editingContent['drive_file_name'], 40) }}</h2>
                </div>
                <button type="button" class="btn btn-sm btn-ghost modal-close" wire:click="closeEditModal" wire:loading.attr="disabled" wire:target="closeEditModal">
                    <svg class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Close
                </button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warn">
                    <strong>⚠️ Warning:</strong> Saving changes will trigger automatic regeneration of embeddings and graph synchronization.
                </div>

                @if($editingContent['case_label'])
                    <div class="metadata-item">
                        <strong>Assigned Case:</strong>
                        <span class="badge badge-info">📁 {{ $editingContent['case_label'] }}</span>
                    </div>
                @endif

                @php
                    $editContent = $editingContent['manual_content'] ?? '';
                    $editStats = [
                        'words' => str_word_count($editContent),
                        'chars' => mb_strlen($editContent),
                    ];
                @endphp
                <div class="editor-header">
                    <h3>Content Editor</h3>
                    <div class="editor-stats">{{ number_format($editStats['words']) }} words • {{ number_format($editStats['chars']) }} chars</div>
                </div>

                <textarea class="content-editor" dusk="content-editor" wire:model="editingContent.manual_content" rows="20">{{ $editContent }}</textarea>

                <div class="modal-footer">
                    <button type="button" class="btn btn-success" wire:click="saveContent" wire:loading.attr="disabled" wire:target="saveContent">
                        <svg wire:loading.remove wire:target="saveContent" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <svg wire:loading wire:target="saveContent" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                            <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                        </svg>
                        <span wire:loading.remove wire:target="saveContent">Save Content</span>
                        <span wire:loading wire:target="saveContent">Saving...</span>
                    </button>
                    @if($editingContent['manually_edited'])
                        <button type="button" class="btn btn-warn" wire:click="resetToOriginal({{ $editingContent['id'] }})"
                                wire:confirm="Reset to original extracted content? This will discard all manual edits." wire:loading.attr="disabled" wire:target="resetToOriginal({{ $editingContent['id'] }})">
                            <svg wire:loading.remove wire:target="resetToOriginal({{ $editingContent['id'] }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg wire:loading wire:target="resetToOriginal({{ $editingContent['id'] }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                            </svg>
                            <span wire:loading.remove wire:target="resetToOriginal({{ $editingContent['id'] }})">Reset to Original</span>
                            <span wire:loading wire:target="resetToOriginal({{ $editingContent['id'] }})">Resetting...</span>
                        </button>
                    @endif
                    <button type="button" class="btn btn-ghost" wire:click="closeEditModal" wire:loading.attr="disabled" wire:target="closeEditModal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<style>
    /* Modal Backdrop */
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.85);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
        backdrop-filter: blur(8px);
        animation: fadeIn 0.2s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Modal Container */
    .modal {
        background: var(--card, #111827);
        border: 1px solid var(--border, #1f2937);
        border-radius: 16px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        color: var(--fg, #e5e7eb);
        animation: slideUp 0.3s ease;
    }
    .modal-large {
        max-width: 1200px;
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Modal Header */
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid var(--border, #1f2937);
        background: linear-gradient(135deg, #0b1220, #1a1f2e);
    }
    .modal-title-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .modal-icon {
        font-size: 24px;
        line-height: 1;
    }
    .modal-title {
        font-size: 18px;
        font-weight: 800;
        margin: 0;
        background: linear-gradient(135deg, #60a5fa, #a78bfa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .modal-header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .modal-close {
        flex-shrink: 0;
    }

    /* Modal Tabs */
    .modal-tabs {
        display: flex;
        gap: 8px;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border, #1f2937);
        background: #0b1220;
    }
    .tab-btn {
        padding: 10px 16px;
        border: 1px solid transparent;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        background: transparent;
        color: var(--muted, #9ca3af);
        transition: all 0.2s;
    }
    .tab-btn:hover {
        background: rgba(59, 130, 246, 0.1);
        border-color: rgba(59, 130, 246, 0.3);
        color: #60a5fa;
    }
    .tab-btn.active {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.2));
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
    }

    /* Modal Body */
    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }
    .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    .modal-body::-webkit-scrollbar-track {
        background: #0b1220;
        border-radius: 4px;
    }
    .modal-body::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }

    /* Modal Footer */
    .modal-footer {
        display: flex;
        gap: 12px;
        padding: 16px 20px;
        border-top: 1px solid var(--border, #1f2937);
        background: linear-gradient(135deg, #0b1220, #1a1f2e);
        flex-wrap: wrap;
    }

    /* Content Stats */
    .content-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    .stat-box {
        background: linear-gradient(135deg, #0b1220, #1a1f2e);
        border: 1px solid var(--border, #1f2937);
        border-radius: 12px;
        padding: 14px;
        text-align: center;
        transition: all 0.3s;
    }
    .stat-box:hover {
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
    }
    .stat-box strong {
        display: block;
        font-size: 24px;
        font-weight: 800;
        color: var(--accent, #22d3ee);
        margin-bottom: 6px;
    }
    .stat-box span {
        font-size: 11px;
        color: var(--muted, #9ca3af);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Content Display */
    .content-display {
        margin-bottom: 18px;
    }
    .content-display h3 {
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 10px 0;
        color: #cbd5e1;
    }
    .content-text {
        background: #0b1220;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--border, #1f2937);
        font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
        font-size: 13px;
        line-height: 1.7;
        white-space: pre-wrap;
        max-height: 500px;
        overflow-y: auto;
        margin: 0;
        color: var(--fg, #e5e7eb);
    }
    .content-text::-webkit-scrollbar {
        width: 6px;
    }
    .content-text::-webkit-scrollbar-track {
        background: #0b1220;
        border-radius: 3px;
    }
    .content-text::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 3px;
    }
    .content-text.faded {
        opacity: 0.7;
        max-height: 300px;
    }

    /* JSON Display */
    .json-display {
        background: #0b1220;
        color: #e2e8f0;
        padding: 16px;
        border: 1px solid var(--border, #1f2937);
        border-radius: 12px;
        font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
        font-size: 12px;
        line-height: 1.7;
        overflow-x: auto;
        margin: 0;
    }

    /* Sync Grid */
    .sync-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }
    .sync-card {
        background: linear-gradient(135deg, #0b1220, #1a1f2e);
        border: 1px solid var(--border, #1f2937);
        border-radius: 12px;
        padding: 16px;
        transition: all 0.3s;
    }
    .sync-card:hover {
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
    }
    .sync-card h3 {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 12px 0;
        color: #cbd5e1;
    }
    .sync-time {
        font-size: 12px;
        color: var(--muted, #9ca3af);
        margin: 10px 0 12px 0;
    }

    /* Metadata Item */
    .metadata-item {
        padding: 12px 14px;
        background: linear-gradient(135deg, #0b1220, #1a1f2e);
        border: 1px solid var(--border, #1f2937);
        border-radius: 12px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Editor Header */
    .editor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .editor-header h3 {
        font-size: 15px;
        font-weight: 700;
        margin: 0;
        color: #cbd5e1;
    }
    .editor-stats {
        font-size: 12px;
        color: var(--muted, #9ca3af);
    }

    /* Content Editor */
    .content-editor {
        width: 100%;
        padding: 14px;
        background: #0b1220;
        color: var(--fg, #e5e7eb);
        border: 1px solid var(--border, #1f2937);
        border-radius: 12px;
        font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
        font-size: 13px;
        line-height: 1.7;
        resize: vertical;
        min-height: 400px;
        transition: all 0.2s;
    }
    .content-editor:focus {
        outline: none;
        border-color: var(--accent, #22d3ee);
        box-shadow: 0 0 0 3px rgba(34,211,238,0.12);
        background: #111827;
    }

    /* Alert Info */
    .alert-info {
        background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.15));
        border: 1px solid rgba(59,130,246,0.4);
        color: #93c5fd;
        padding: 14px;
        border-radius: 12px;
        margin-bottom: 16px;
        line-height: 1.6;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .modal {
            max-width: 100%;
            max-height: 95vh;
            margin: 10px;
        }
        .content-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .sync-grid {
            grid-template-columns: 1fr;
        }
        .modal-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }
        .modal-header-actions {
            width: 100%;
            justify-content: flex-end;
        }
        .modal-footer {
            flex-direction: column;
        }
        .modal-footer button {
            width: 100%;
        }
    }
</style>
