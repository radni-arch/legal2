<div class="textract-manager" x-data="{ autoRefreshEnabled: @entangle('autoRefresh') }">
    {{-- Unified Header --}}
    <x-page-header
        title="Textract Pipeline Manager"
        subtitle="Manage PDF processing through AWS Textract with OCR reconstruction"
        route-name="textract.manager"
    />

    {{-- Storage Preview --}}
    <details class="storage-preview" open>
        <summary class="section-header">
            <span class="section-icon">🗂️</span>
            <span>Storage Folders</span>
            <span class="section-path">storage/app/private/textract</span>
        </summary>
        <div class="storage-grid">
            @foreach([
                ['key' => 'source', 'label' => 'Source PDFs', 'icon' => '📥'],
                ['key' => 'json', 'label' => 'Textract JSON', 'icon' => '📋'],
                ['key' => 'output', 'label' => 'Searchable PDFs', 'icon' => '📤'],
            ] as $folder)
                <div class="storage-card">
                    <div class="card-header">
                        <span class="storage-icon">{{ $folder['icon'] }}</span>
                        <strong>{{ $folder['label'] }}</strong>
                        <span class="badge badge-count">{{ $storagePreview[$folder['key']]['count'] ?? 0 }}</span>
                    </div>
                    <div class="storage-path">{{ $storagePreview[$folder['key']]['path'] ?? '' }}</div>
                    @if(isset($storagePreview[$folder['key']]['error']))
                        <div class="error-msg">⚠️ {{ $storagePreview[$folder['key']]['error'] }}</div>
                    @endif
                    <ul class="file-list">
                        @forelse(($storagePreview[$folder['key']]['entries'] ?? []) as $file)
                            <li class="file-item">
                                <span class="file-name" title="{{ $file['path'] }}">{{ $file['name'] }}</span>
                                <span class="file-meta">{{ $file['mtime'] }}</span>
                                <span class="file-size">{{ number_format(($file['size'] ?? 0)/1024, 1) }} KB</span>
                            </li>
                        @empty
                            <li class="empty-state">No files yet</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </details>

    {{-- Statistics Dashboard --}}
    <div class="stats-grid">
        <div class="stat-card stat-total">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-label">Total Jobs</div>
                <div class="stat-value stat-accent">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['total'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-queued">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <div class="stat-label">Queued</div>
                <div class="stat-value stat-warn">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['queued'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-processing">
            <div class="stat-icon">⚙️</div>
            <div class="stat-content">
                <div class="stat-label">Processing</div>
                <div class="stat-value stat-info">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['processing'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-succeeded">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-label">Succeeded</div>
                <div class="stat-value stat-success">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['succeeded'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-failed">
            <div class="stat-icon">❌</div>
            <div class="stat-content">
                <div class="stat-label">Failed</div>
                <div class="stat-value stat-error">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['failed'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-review">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <div class="stat-label">Needs Review</div>
                <div class="stat-value stat-warn">
                    <span wire:loading.remove wire:target="refreshJobs,syncFromDrive">{{ $stats['needs_review'] }}</span>
                    <span wire:loading wire:target="refreshJobs,syncFromDrive" class="loading-pulse">—</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="controls-bar">
        <div class="control-group">
            <label>
                <span class="label-icon">📁</span>
                Drive Folder ID
            </label>
            <input type="text" class="control-input" wire:model.blur="folderId" placeholder="Enter Google Drive Folder ID">
        </div>
        <div class="control-group">
            <label>
                <span class="label-icon">🔍</span>
                Search
            </label>
            <div class="input-wrapper">
                <input type="text" class="control-input" wire:model.debounce.400ms="search" placeholder="Search by file name or ID...">
                <span wire:loading wire:target="search" class="input-spinner">
                    <svg class="spinner" viewBox="0 0 24 24"><circle class="spinner-circle" cx="12" cy="12" r="10"></circle></svg>
                </span>
            </div>
        </div>
        <div class="control-group">
            <label>
                <span class="label-icon">🎯</span>
                Status Filter
            </label>
            <div class="input-wrapper">
                <select class="control-input" dusk="status-filter" wire:model.live="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="queued">⏳ Queued</option>
                    <option value="uploading">📤 Uploading</option>
                    <option value="started">🔄 Started</option>
                    <option value="analyzing">🔍 Analyzing</option>
                    <option value="reconstructing">🔧 Reconstructing</option>
                    <option value="succeeded">✅ Succeeded</option>
                    <option value="failed">❌ Failed</option>
                    <option value="needs_review">⚠️ Needs Review</option>
                </select>
                <span wire:loading wire:target="statusFilter" class="input-spinner">
                    <svg class="spinner" viewBox="0 0 24 24"><circle class="spinner-circle" cx="12" cy="12" r="10"></circle></svg>
                </span>
            </div>
        </div>
        <div class="control-actions">
            <label class="checkbox-label" x-bind:class="{ 'checkbox-active': autoRefreshEnabled }">
                <input type="checkbox" wire:model.live="autoRefresh">
                <span>Auto-refresh (10s)</span>
            </label>
            <button type="button" class="btn btn-icon" wire:click="refreshJobs" wire:loading.attr="disabled" wire:target="refreshJobs">
                <svg wire:loading.remove wire:target="refreshJobs" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="refreshJobs" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                </svg>
                <span wire:loading.remove wire:target="refreshJobs">Refresh</span>
                <span wire:loading wire:target="refreshJobs">Refreshing...</span>
            </button>
            <button type="button" class="btn btn-primary" wire:click="syncFromDrive" wire:loading.attr="disabled" wire:target="syncFromDrive">
                <svg wire:loading.remove wire:target="syncFromDrive" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                </svg>
                <svg wire:loading wire:target="syncFromDrive" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                </svg>
                <span wire:loading.remove wire:target="syncFromDrive">Sync Drive</span>
                <span wire:loading wire:target="syncFromDrive">Syncing...</span>
            </button>
            @if($search)
                <button type="button" class="btn btn-ghost" wire:click="$set('search','')" wire:loading.attr="disabled" wire:target="$set('search','')">
                    <svg class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Manual Processing --}}
    <details class="manual-process">
        <summary class="section-header">
            <span class="section-icon">➕</span>
            <span>Process Single File Manually</span>
        </summary>
        <div class="manual-form">
            <div class="control-group">
                <label>
                    <span class="label-icon">🆔</span>
                    Drive File ID
                </label>
                <input type="text" class="control-input" dusk="manual-drive-file-id" wire:model="manualDriveFileId" placeholder="1abc...xyz">
            </div>
            <div class="control-group">
                <label>
                    <span class="label-icon">📄</span>
                    File Name
                </label>
                <input type="text" class="control-input" dusk="manual-drive-file-name" wire:model="manualDriveFileName" placeholder="document.pdf">
            </div>
            <div class="control-group">
                <label>
                    <span class="label-icon">📁</span>
                    Attach to Case
                </label>
                <select class="control-input" dusk="manual-case-selector" wire:model="selectedCaseForManual">
                    <option value="">— Select a case —</option>
                    @foreach($caseOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="control-group">
                <label class="checkbox-label">
                    <input type="checkbox" wire:model="forceTextractForManual">
                    <span>Force OCR Re-processing</span>
                </label>
            </div>
            <button type="button" class="btn btn-success" dusk="process-manual-button" wire:click="processManual" wire:loading.attr="disabled" wire:target="processManual">
                <svg wire:loading.remove wire:target="processManual" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <svg wire:loading wire:target="processManual" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                </svg>
                <span wire:loading.remove wire:target="processManual">Queue Job</span>
                <span wire:loading wire:target="processManual">Queueing...</span>
            </button>
        </div>
    </details>

    {{-- Jobs List --}}
    <div class="jobs-container" @if($autoRefresh) wire:poll.10s="refreshJobs" @endif>
        <div wire:loading.class="loading-overlay-active" wire:target="search,statusFilter" class="loading-overlay">
            <div class="loading-spinner">
                <svg class="spinner-large" viewBox="0 0 24 24">
                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                </svg>
                <div class="loading-text">Loading jobs...</div>
            </div>
        </div>

        @if($jobs->isEmpty())
            <div class="empty-state-card">
                <div class="empty-icon">📭</div>
                <div class="empty-title">No Jobs Found</div>
                <div class="empty-text">
                    @if($search || $statusFilter !== 'all')
                        No jobs match your current filters. Try adjusting your search or filter criteria.
                    @else
                        Get started by syncing files from Google Drive or adding them manually.
                    @endif
                </div>
                @if(!$search && $statusFilter === 'all')
                    <button type="button" class="btn btn-primary" wire:click="syncFromDrive" wire:loading.attr="disabled" wire:target="syncFromDrive">
                        <svg wire:loading.remove wire:target="syncFromDrive" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                        </svg>
                        <span wire:loading.remove wire:target="syncFromDrive">Sync from Drive</span>
                        <span wire:loading wire:target="syncFromDrive">Syncing...</span>
                    </button>
                @endif
            </div>
        @else
            <ul class="jobs-list">
                @foreach($jobs as $job)
                    @php
                        $isExpanded = isset($expandedJobs[$job->id]);
                        $statusConfig = $this->getStatusConfig($job->status);
                        $quality = $this->getQualityIndicator($job->metadata);
                        $isProcessing = $this->isProcessing($job);
                    @endphp
                    <li class="job-card {{ $isExpanded ? 'expanded' : '' }} {{ $isProcessing ? 'processing' : '' }}" wire:key="job-{{ $job->id }}" x-data="{ expanded: {{ $isExpanded ? 'true' : 'false' }} }">
                        <div class="job-header" wire:click="toggleJobCard({{ $job->id }})">
                            <span class="expand-icon" x-bind:class="{ 'expanded': expanded }">
                                <svg class="expand-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                            <span class="job-filename" title="{{ $job->drive_file_name }}">
                                <span class="file-icon">📄</span>
                                {{ Str::limit($job->drive_file_name, 60) }}
                            </span>
                            <div class="job-badges">
                                {{-- Pipeline Status Badges --}}
                                <livewire:components.textract-status-badge :job="$job" :key="'badge-'.$job->id" />

                                <span class="badge badge-{{ $statusConfig['class'] }} badge-status {{ $isProcessing ? 'badge-pulse' : '' }}">
                                    <span class="badge-icon">{{ $statusConfig['icon'] }}</span>
                                    {{ $statusConfig['label'] }}
                                </span>
                                @if($quality && $quality['needs_review'])
                                    <span class="badge badge-warn badge-quality" title="OCR Quality: {{ number_format($quality['confidence'] * 100, 1) }}%">
                                        <span class="badge-icon">⚠️</span>
                                        Review
                                    </span>
                                @elseif($quality)
                                    <span class="badge badge-neutral" title="OCR Confidence: {{ number_format($quality['confidence'] * 100, 1) }}%">
                                        <span class="badge-icon">✓</span>
                                        {{ number_format($quality['confidence'] * 100, 0) }}%
                                    </span>
                                @endif
                                @if($job->manually_edited)
                                    <span class="badge badge-info" title="Content manually edited">
                                        <span class="badge-icon">✏️</span>
                                        Edited
                                    </span>
                                @endif
                                @if($job->case_id)
                                    <span class="badge badge-info" title="Case ID: {{ $job->case_id }}">
                                        <span class="badge-icon">📁</span>
                                        Case
                                    </span>
                                @else
                                    <span class="badge badge-warn" title="No case assigned">
                                        <span class="badge-icon">⚠️</span>
                                        No Case
                                    </span>
                                @endif
                                <span class="badge badge-neutral badge-time" title="{{ $job->updated_at->format('Y-m-d H:i:s') }}">
                                    <span class="badge-icon">🕒</span>
                                    {{ $job->updated_at->diffForHumans() }}
                                </span>
                            </div>
                            {{-- Actions Dropdown --}}
                            <div class="job-actions-dropdown" onclick="event.stopPropagation()">
                                <livewire:components.textract-job-actions :job="$job" :key="'actions-'.$job->id" />
                            </div>
                        </div>

                        {{-- Metadata Summary --}}
                        @if(in_array($job->status, ['completed', 'succeeded']) && $job->metadata)
                            <div class="metadata-summary">
                                @if($job->metadata['document_type'] ?? null)
                                    <span class="meta-tag">{{ $job->metadata['document_type'] }}</span>
                                @endif
                                @if(($job->metadata['courts'] ?? null) && count($job->metadata['courts']) > 0)
                                    <span class="meta-tag">{{ count($job->metadata['courts']) }} courts</span>
                                @endif
                                @if(($job->metadata['total_citations'] ?? 0) > 0)
                                    <span class="meta-tag">{{ $job->metadata['total_citations'] }} citations</span>
                                @endif
                                @if(($job->metadata['parties'] ?? null) && count($job->metadata['parties']) > 0)
                                    <span class="meta-tag">{{ count($job->metadata['parties']) }} parties</span>
                                @endif
                                @if($job->metadata['page_count'] ?? null)
                                    <span class="meta-tag">{{ $job->metadata['page_count'] }} pages</span>
                                @endif
                            </div>
                        @endif

                        @if($isExpanded)
                            <div class="job-details" x-data="{ showCaseAssign: {{ $job->case_id ? 'false' : 'true' }} }">
                                {{-- Case Assignment --}}
                                <div class="detail-section case-assignment">
                                    <div class="case-info">
                                        @if($job->case_id)
                                            <span class="badge badge-success badge-large">
                                                <span class="badge-icon">📁</span>
                                                Case Assigned: {{ $job->case?->title ?? $job->case?->case_number ?? $job->case_id }}
                                            </span>
                                            <button type="button" class="btn btn-sm btn-ghost" @click="showCaseAssign = !showCaseAssign">
                                                <span x-show="!showCaseAssign">Change Case</span>
                                                <span x-show="showCaseAssign">Hide</span>
                                            </button>
                                        @else
                                            <span class="badge badge-warn badge-large">
                                                <span class="badge-icon">⚠️</span>
                                                No case assigned - Required for processing
                                            </span>
                                        @endif
                                    </div>
                                    <div class="case-selector" x-show="showCaseAssign" x-transition>
                                        <select class="control-input" wire:model="selectedCaseForJob.{{ $job->id }}">
                                            <option value="">— Select a case —</option>
                                            @foreach($caseOptions as $opt)
                                                <option value="{{ $opt['id'] }}" {{ $job->case_id == $opt['id'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="assignJobCase({{ $job->id }})" wire:loading.attr="disabled" wire:target="assignJobCase({{ $job->id }})">
                                            <svg wire:loading.remove wire:target="assignJobCase({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg wire:loading wire:target="assignJobCase({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                            </svg>
                                            <span wire:loading.remove wire:target="assignJobCase({{ $job->id }})">Assign Case</span>
                                            <span wire:loading wire:target="assignJobCase({{ $job->id }})">Assigning...</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Error Display --}}
                                @if($job->error)
                                    <div class="alert alert-error">
                                        <strong>❌ Error:</strong> {{ Str::limit($job->error, 250) }}
                                    </div>
                                @endif

                                {{-- OCR Quality Warning --}}
                                @if($quality && $quality['needs_review'])
                                    <div class="alert alert-warn">
                                        <strong>⚠️ OCR Quality Issues:</strong>
                                        <div class="quality-details">
                                            Confidence: {{ number_format($quality['confidence'] * 100, 1) }}% |
                                            Coverage: {{ number_format($quality['coverage'] * 100, 1) }}% |
                                            Low-Conf Pages: {{ $quality['low_conf_pages'] }}
                                        </div>
                                        @if(!empty($quality['reasons']))
                                            <div class="quality-reasons">{{ implode(' • ', array_slice($quality['reasons'], 0, 2)) }}</div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Actions --}}
                                <div class="job-actions">
                                    <div class="action-group">
                                        <div class="action-label">View Options</div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-ghost" wire:click="viewJobDetails({{ $job->id }})" wire:loading.attr="disabled" wire:target="viewJobDetails({{ $job->id }})">
                                                <svg wire:loading.remove wire:target="viewJobDetails({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg wire:loading wire:target="viewJobDetails({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                </svg>
                                                <span>Details</span>
                                            </button>

                                            @if($job->extracted_content || $job->manual_content)
                                                <button type="button" class="btn btn-sm btn-ghost" dusk="view-content-{{ $job->id }}" wire:click="viewContent({{ $job->id }})" wire:loading.attr="disabled" wire:target="viewContent({{ $job->id }})">
                                                    <svg wire:loading.remove wire:target="viewContent({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <svg wire:loading wire:target="viewContent({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                        <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                    </svg>
                                                    <span>Content</span>
                                                </button>
                                            @endif

                                            <button type="button" class="btn btn-sm btn-ghost" dusk="edit-content-{{ $job->id }}" wire:click="editContent({{ $job->id }})" wire:loading.attr="disabled" wire:target="editContent({{ $job->id }})">
                                                <svg wire:loading.remove wire:target="editContent({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <svg wire:loading wire:target="editContent({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                </svg>
                                                <span>Edit</span>
                                            </button>

                                            @if($job->documents()->exists() && $job->documents()->whereNotNull('s3_output_path')->exists())
                                                @php
                                                    $documentWithPdf = $job->documents()->whereNotNull('s3_output_path')->first();
                                                @endphp
                                                <button type="button" wire:click="previewPdf({{ $documentWithPdf->id }})" dusk="preview-pdf-{{ $job->id }}" class="btn btn-sm btn-ghost" wire:loading.attr="disabled" wire:target="previewPdf({{ $documentWithPdf->id }})">
                                                    <svg wire:loading.remove wire:target="previewPdf({{ $documentWithPdf->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                    <svg wire:loading wire:target="previewPdf({{ $documentWithPdf->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                        <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                    </svg>
                                                    <span>Preview PDF</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if(in_array($job->status, ['queued', 'failed']) || $this->canReprocess($job))
                                        <div class="action-group">
                                            <div class="action-label">Processing Actions</div>
                                            <div class="action-buttons">
                                                @if(in_array($job->status, ['queued', 'failed']))
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="processJob({{ $job->id }})" @disabled(!$job->case_id) wire:loading.attr="disabled" wire:target="processJob({{ $job->id }})">
                                                        <svg wire:loading.remove wire:target="processJob({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        <svg wire:loading wire:target="processJob({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                            <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                        </svg>
                                                        <span wire:loading.remove wire:target="processJob({{ $job->id }})">Process Now</span>
                                                        <span wire:loading wire:target="processJob({{ $job->id }})">Processing...</span>
                                                    </button>
                                                @endif

                                                @if($job->status === 'failed')
                                                    <button type="button" class="btn btn-sm btn-warn" wire:click="retryJob({{ $job->id }})" wire:loading.attr="disabled" wire:target="retryJob({{ $job->id }})">
                                                        <svg wire:loading.remove wire:target="retryJob({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        <svg wire:loading wire:target="retryJob({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                            <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                        </svg>
                                                        <span wire:loading.remove wire:target="retryJob({{ $job->id }})">Retry</span>
                                                        <span wire:loading wire:target="retryJob({{ $job->id }})">Retrying...</span>
                                                    </button>
                                                @endif

                                                @if($this->canReprocess($job))
                                                    <button type="button" class="btn btn-sm btn-warn" wire:click="reprocessJob({{ $job->id }})" @disabled(!$job->case_id) wire:confirm="Re-run OCR processing? This will replace existing results." wire:loading.attr="disabled" wire:target="reprocessJob({{ $job->id }})">
                                                        <svg wire:loading.remove wire:target="reprocessJob({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        <svg wire:loading wire:target="reprocessJob({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                            <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                        </svg>
                                                        <span wire:loading.remove wire:target="reprocessJob({{ $job->id }})">Re-OCR</span>
                                                        <span wire:loading wire:target="reprocessJob({{ $job->id }})">Re-processing...</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    <div class="action-group">
                                        <div class="action-label">Danger Zone</div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-error" wire:click="deleteJob({{ $job->id }})" wire:confirm="Delete this job? This action cannot be undone." wire:loading.attr="disabled" wire:target="deleteJob({{ $job->id }})">
                                                <svg wire:loading.remove wire:target="deleteJob({{ $job->id }})" class="btn-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                <svg wire:loading wire:target="deleteJob({{ $job->id }})" class="btn-icon-svg spinner-icon" viewBox="0 0 24 24">
                                                    <circle class="spinner-circle" cx="12" cy="12" r="10"></circle>
                                                </svg>
                                                <span wire:loading.remove wire:target="deleteJob({{ $job->id }})">Delete Job</span>
                                                <span wire:loading wire:target="deleteJob({{ $job->id }})">Deleting...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Metadata --}}
                                <div class="job-metadata">
                                    <strong>Drive ID:</strong> <code>{{ $job->drive_file_id }}</code>
                                    @if($job->job_id)
                                        <br><strong>Textract Job:</strong> <code>{{ Str::limit($job->job_id, 70) }}</code>
                                    @endif
                                    @if($job->s3_key)
                                        <br><strong>S3 Key:</strong> <code>{{ Str::limit($job->s3_key, 70) }}</code>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Pagination --}}
            <div class="pagination">
                {{ $jobs->links() }}
            </div>
        @endif
    </div>

    @include('livewire.textract-manager.modals')

    <style>
        /* Base Layout */
        .textract-manager {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            color: var(--fg, #e5e7eb);
        }

        /* Header */
        .header {
            margin-bottom: 32px;
        }
        .header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-icon-wrapper {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.25);
        }
        .header-icon {
            font-size: 42px;
            line-height: 1;
        }
        .title {
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 6px 0;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .subtitle {
            color: var(--muted, #9ca3af);
            margin: 0;
            font-size: 15px;
        }

        /* Storage Preview */
        .storage-preview, .manual-process {
            background: var(--card, #111827);
            border: 1px solid var(--border, #1f2937);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .storage-preview:hover, .manual-process:hover {
            border-color: #2d3748;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .section-header {
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            user-select: none;
            color: var(--fg, #e5e7eb);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.2s;
        }
        .section-header:hover {
            color: var(--accent, #22d3ee);
        }
        .section-icon {
            font-size: 20px;
        }
        .section-path {
            margin-left: auto;
            font-size: 12px;
            color: var(--muted, #6b7280);
            font-family: monospace;
        }
        .storage-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 16px;
        }
        .storage-card {
            background: linear-gradient(135deg, #0b1220, #1a1f2e);
            border: 1px solid var(--border, #1f2937);
            border-radius: 12px;
            padding: 14px;
            transition: all 0.3s ease;
        }
        .storage-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .storage-icon {
            font-size: 20px;
        }
        .badge-count {
            margin-left: auto;
            background: rgba(34, 211, 238, 0.15);
            color: #22d3ee;
            border-color: rgba(34, 211, 238, 0.35);
        }
        .storage-path {
            font-size: 11px;
            color: var(--muted, #9ca3af);
            margin-bottom: 10px;
            font-family: monospace;
        }
        .file-list {
            list-style: none;
            padding: 0;
            margin: 8px 0 0 0;
            max-height: 240px;
            overflow-y: auto;
        }
        .file-list::-webkit-scrollbar {
            width: 6px;
        }
        .file-list::-webkit-scrollbar-track {
            background: #0b1220;
            border-radius: 3px;
        }
        .file-list::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(148,163,184,0.1);
        }
        .file-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-meta, .file-size {
            color: var(--muted, #9ca3af);
            font-size: 11px;
        }
        .empty-state {
            font-size: 12px;
            color: var(--muted, #9ca3af);
            padding: 12px 0;
            text-align: center;
            font-style: italic;
        }
        .error-msg {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            padding: 6px 10px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 6px;
        }

        /* Statistics Dashboard */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #0b1220, #1a1f2e);
            border: 1px solid var(--border, #1f2937);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--stat-color, #3b82f6), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-card:hover::before {
            opacity: 1;
        }
        .stat-card:hover {
            border-color: var(--stat-color, #3b82f6);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
        }
        .stat-total { --stat-color: #22d3ee; }
        .stat-queued { --stat-color: #eab308; }
        .stat-processing { --stat-color: #0ea5e9; }
        .stat-succeeded { --stat-color: #22c55e; }
        .stat-failed { --stat-color: #ef4444; }
        .stat-review { --stat-color: #f59e0b; }
        .stat-icon {
            font-size: 32px;
            line-height: 1;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        .stat-content {
            flex: 1;
        }
        .stat-label {
            font-size: 11px;
            color: var(--muted, #9ca3af);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }
        .stat-accent { color: #22d3ee; }
        .stat-warn { color: #eab308; }
        .stat-info { color: #0ea5e9; }
        .stat-success { color: #22c55e; }
        .stat-error { color: #ef4444; }
        .loading-pulse {
            animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Controls Bar */
        .controls-bar {
            background: var(--card, #111827);
            border: 1px solid var(--border, #1f2937);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: end;
        }
        .control-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }
        .control-group label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--muted, #9ca3af);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .label-icon {
            font-size: 14px;
        }
        .input-wrapper {
            position: relative;
        }
        .control-input {
            width: 100%;
            padding: 10px 12px;
            background: #0b1220;
            color: var(--fg, #e5e7eb);
            border: 1px solid var(--border, #1f2937);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .control-input::placeholder {
            color: #6b7280;
        }
        .control-input:focus {
            outline: none;
            border-color: var(--accent, #22d3ee);
            box-shadow: 0 0 0 3px rgba(34,211,238,0.12);
            background: #111827;
        }
        .input-spinner {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
        }
        .control-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--muted, #9ca3af);
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.2s;
            user-select: none;
        }
        .checkbox-label:hover {
            background: rgba(34, 211, 238, 0.08);
            color: #22d3ee;
        }
        .checkbox-label.checkbox-active {
            background: rgba(34, 211, 238, 0.15);
            color: #22d3ee;
        }
        .checkbox-label input {
            accent-color: var(--accent, #22d3ee);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Manual Form */
        .manual-form {
            display: flex;
            gap: 14px;
            margin-top: 16px;
            flex-wrap: wrap;
            align-items: end;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .loading-overlay-active {
            opacity: 1;
            pointer-events: all;
        }
        .loading-spinner {
            text-align: center;
        }
        .loading-text {
            color: white;
            font-weight: 600;
            margin-top: 12px;
        }
        .spinner-large {
            width: 64px;
            height: 64px;
            animation: spin 1s linear infinite;
        }

        /* Empty State */
        .empty-state-card {
            background: var(--card, #111827);
            border: 2px dashed var(--border, #1f2937);
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
        }
        .empty-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.5;
            filter: grayscale(0.3);
        }
        .empty-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--fg, #e5e7eb);
        }
        .empty-text {
            color: var(--muted, #9ca3af);
            font-size: 15px;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* Jobs List */
        .jobs-container {
            position: relative;
        }
        .jobs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .job-card {
            background: var(--card, #111827);
            border: 1px solid var(--border, #1f2937);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        .job-card:hover {
            border-color: #2d3748;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .job-card.processing {
            border-color: rgba(14, 165, 233, 0.5);
            background: linear-gradient(135deg, #111827, #1a2332);
        }
        .job-header {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }
        .expand-icon {
            width: 20px;
            height: 20px;
            color: var(--muted, #9ca3af);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .expand-icon.expanded {
            transform: rotate(90deg);
            color: var(--accent, #22d3ee);
        }
        .expand-svg {
            width: 100%;
            height: 100%;
        }
        .job-filename {
            flex: 1;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }
        .file-icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        .job-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .job-details {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border, #1f2937);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Case Assignment */
        .case-assignment {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(139, 92, 246, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .case-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .case-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .case-selector select {
            flex: 1;
            min-width: 250px;
        }

        /* Job Actions */
        .job-actions {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 16px 0;
        }
        .action-group {
            background: linear-gradient(135deg, #0b1220, #1a1f2e);
            border: 1px solid var(--border, #1f2937);
            border-radius: 12px;
            padding: 14px;
        }
        .action-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted, #6b7280);
            margin-bottom: 10px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Job Metadata */
        .job-metadata {
            font-size: 12px;
            color: var(--muted, #9ca3af);
            line-height: 1.8;
            margin-top: 12px;
            padding: 12px;
            background: #0b1220;
            border-radius: 10px;
            border: 1px solid var(--border, #1f2937);
        }
        .job-metadata code {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            background: #1a1f2e;
            border: 1px solid var(--border, #1f2937);
            padding: 3px 8px;
            border-radius: 6px;
            color: var(--fg, #e5e7eb);
            font-size: 11px;
        }

        /* Alerts */
        .alert {
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 12px;
            font-size: 13px;
            line-height: 1.6;
        }
        .alert-error {
            background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1));
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }
        .alert-warn {
            background: linear-gradient(135deg, rgba(234,179,8,0.1), rgba(202,138,4,0.1));
            border: 1px solid rgba(234,179,8,0.3);
            color: #fde047;
        }
        .quality-details {
            font-size: 12px;
            color: var(--muted, #9ca3af);
            margin-top: 8px;
        }
        .quality-reasons {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: var(--chip, #334155);
            color: #d1d5db;
            white-space: nowrap;
            border: 1px solid var(--border, #1f2937);
            transition: all 0.2s;
        }
        .badge-icon {
            font-size: 14px;
            line-height: 1;
        }
        .badge-large {
            padding: 8px 16px;
            font-size: 13px;
        }
        .badge-status {
            font-weight: 700;
        }
        .badge-pulse {
            animation: badgePulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes badgePulse {
            0%, 100% {
                opacity: 1;
                box-shadow: 0 0 0 0 currentColor;
            }
            50% {
                opacity: 0.9;
                box-shadow: 0 0 8px 2px currentColor;
            }
        }
        .badge-warn {
            background: linear-gradient(135deg, rgba(234,179,8,0.15), rgba(234,179,8,0.2));
            color: #fde047;
            border-color: rgba(234,179,8,0.4);
        }
        .badge-info {
            background: linear-gradient(135deg, rgba(14,165,233,0.15), rgba(14,165,233,0.2));
            color: #7dd3fc;
            border-color: rgba(14,165,233,0.4);
        }
        .badge-success {
            background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(34,197,94,0.2));
            color: #86efac;
            border-color: rgba(34,197,94,0.4);
        }
        .badge-error {
            background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(239,68,68,0.2));
            color: #fca5a5;
            border-color: rgba(239,68,68,0.4);
        }
        .badge-neutral {
            background: rgba(71,85,105,0.2);
            color: #cbd5e1;
            border-color: rgba(71,85,105,0.4);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border: 1px solid var(--border, #1f2937);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: #0b1220;
            color: var(--fg, #e5e7eb);
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn:hover:not(:disabled) {
            background: #131b2e;
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .btn:active:not(:disabled) {
            transform: translateY(0);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-sm {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 8px;
        }
        .btn-icon-svg {
            width: 18px;
            height: 18px;
        }
        .spinner {
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        .spinner-icon {
            animation: spin 1s linear infinite;
        }
        .spinner-circle {
            fill: none;
            stroke: currentColor;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-dasharray: 50;
            stroke-dashoffset: 25;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-color: #1d4ed8;
        }
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        .btn-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            border-color: #0369a1;
        }
        .btn-info:hover:not(:disabled) {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            box-shadow: 0 8px 24px rgba(14, 165, 233, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border-color: #15803d;
        }
        .btn-success:hover:not(:disabled) {
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.4);
        }
        .btn-warn {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: #0f172a;
            border-color: #a16207;
        }
        .btn-warn:hover:not(:disabled) {
            background: linear-gradient(135deg, #ca8a04, #a16207);
            box-shadow: 0 8px 24px rgba(234, 179, 8, 0.4);
        }
        .btn-error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-color: #b91c1c;
        }
        .btn-error:hover:not(:disabled) {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
        }
        .btn-ghost {
            background: transparent;
            border-color: rgba(148, 163, 184, 0.3);
        }
        .btn-ghost:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.1);
            border-color: #3b82f6;
        }

        /* Pipeline Status & Actions */
        .job-actions-dropdown {
            flex-shrink: 0;
        }
        .metadata-summary {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 0 0 32px;
            font-size: 12px;
        }
        .meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: rgba(71, 85, 105, 0.15);
            border: 1px solid rgba(71, 85, 105, 0.25);
            border-radius: 6px;
            color: #94a3b8;
            font-size: 11px;
        }

        /* Pagination */
        .pagination {
            margin-top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .storage-grid {
                grid-template-columns: 1fr 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 768px) {
            .storage-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            .controls-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .control-group {
                width: 100%;
            }
            .control-actions {
                width: 100%;
                flex-direction: column;
            }
            .control-actions button {
                width: 100%;
            }
            .job-badges {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons button {
                width: 100%;
            }
        }
    </style>

    {{-- PDF Preview Modal --}}
    @if($showPdfModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="pdfModalHandler()">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            {{-- Background overlay --}}
            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" wire:click="closePdfModal"></div>

            {{-- Modal panel --}}
            <div class="inline-block w-full max-w-6xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6">

                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-white">PDF Preview</h3>
                    <button wire:click="closePdfModal" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- PDF Canvas --}}
                <div class="relative bg-gray-900 rounded-lg p-4 mb-4 flex items-center justify-center min-h-[600px]">
                    <canvas id="pdf-canvas" class="max-w-full"></canvas>
                    <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75">
                        <div class="text-white">Loading PDF...</div>
                    </div>
                </div>

                {{-- Controls --}}
                <div class="flex items-center justify-between">
                    <div class="flex space-x-2">
                        <button @click="previousPage" :disabled="currentPage <= 1"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <button @click="nextPage" :disabled="currentPage >= totalPages"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>

                    <div class="text-white">
                        Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                    </div>

                    <div class="flex space-x-2">
                        <button @click="zoomOut"
                                class="px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-600">
                            Zoom Out
                        </button>
                        <button @click="zoomIn"
                                class="px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-600">
                            Zoom In
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function pdfModalHandler() {
                return {
                    viewer: null,
                    loading: true,
                    currentPage: 1,
                    totalPages: 0,

                    init() {
                        this.$nextTick(() => {
                            const canvas = document.getElementById('pdf-canvas');
                            if (!canvas) {
                                console.error('PDF canvas not found');
                                return;
                            }

                            this.viewer = new window.PdfViewer(canvas, {
                                scale: 1.5,
                                onPageChange: (page, total) => {
                                    this.currentPage = page;
                                    this.totalPages = total;
                                },
                                onError: (error) => {
                                    console.error('PDF Viewer Error:', error);
                                    this.loading = false;
                                    alert('Failed to load PDF: ' + error.message);
                                }
                            });

                            this.loadPdf();
                        });
                    },

                    async loadPdf() {
                        try {
                            this.loading = true;
                            await this.viewer.loadDocument(@js($pdfSignedUrl));
                            this.loading = false;
                        } catch (error) {
                            console.error('PDF Load Error:', error);
                            this.loading = false;
                        }
                    },

                    async nextPage() {
                        await this.viewer.nextPage();
                    },

                    async previousPage() {
                        await this.viewer.previousPage();
                    },

                    async zoomIn() {
                        await this.viewer.zoomIn();
                    },

                    async zoomOut() {
                        await this.viewer.zoomOut();
                    }
                };
            }
        </script>
    </div>
    @endif
</div>
