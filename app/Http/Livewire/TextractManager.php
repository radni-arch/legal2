<?php

namespace App\Http\Livewire;

use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\ProcessDrivePdf;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class TextractManager extends Component
{
    use WithPagination;

    protected $listeners = [
        'refreshJobs',
        'openContentModal' => 'viewContent',
        'openEditModal' => 'editContent',
    ];

    // Configuration
    public string $folderId = '';

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $autoRefresh = false;

    public int $perPage = 20;

    // Manual processing
    public string $manualDriveFileId = '';

    public string $manualDriveFileName = '';

    public ?string $selectedCaseForManual = null;

    public bool $forceTextractForManual = false;

    // Case selection
    public array $selectedCaseForJob = [];

    public array $forceTextractForJob = [];

    public array $caseOptions = [];

    // Job details
    public ?int $selectedJobId = null;

    public ?array $selectedJobData = null;

    // UI state
    public array $expandedJobs = [];

    public bool $showContentViewModal = false;

    public ?array $viewingContent = null;

    public bool $showContentEditModal = false;

    public array $editingContent = [];

    public string $contentTab = 'content';

    // PDF Preview
    public bool $showPdfModal = false;

    public ?int $previewDocumentId = null;

    public ?string $pdfSignedUrl = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->folderId = (string) env('GOOGLE_DRIVE_FOLDER_ID', '');
        $this->loadCaseOptions();
        // Ensure we have the expected folder structure so folder listing doesn't fail
        $this->ensureTextractDirectories();
    }

    /**
     * Ensure textract directories exist on the configured local disk
     */
    private function ensureTextractDirectories(): void
    {
        try {
            $disk = Storage::disk('local');
            foreach (['textract/source', 'textract/json', 'textract/output'] as $dir) {
                if (! $disk->exists($dir)) {
                    $disk->makeDirectory($dir);
                }
                // Best-effort: relax permissions so web user can list/read
                $path = $disk->path($dir);
                if (is_dir($path)) {
                    @chmod($path, 0775);
                }
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Storage initialization warning: '.$e->getMessage());
        }
    }

    /**
     * Get status configuration with icon and color class
     */
    public function getStatusConfig(string $status): array
    {
        return match ($status) {
            'queued' => ['class' => 'warn', 'icon' => '⏳', 'label' => 'Queued'],
            'uploading' => ['class' => 'info', 'icon' => '📤', 'label' => 'Uploading'],
            'started' => ['class' => 'info', 'icon' => '🔄', 'label' => 'Started'],
            'analyzing' => ['class' => 'info', 'icon' => '🔍', 'label' => 'Analyzing'],
            'reconstructing' => ['class' => 'info', 'icon' => '🔧', 'label' => 'Reconstructing'],
            'succeeded' => ['class' => 'success', 'icon' => '✅', 'label' => 'Succeeded'],
            'failed' => ['class' => 'error', 'icon' => '❌', 'label' => 'Failed'],
            default => ['class' => '', 'icon' => '•', 'label' => ucfirst($status)],
        };
    }

    /**
     * Check if job is currently processing
     */
    public function isProcessing(TextractJob $job): bool
    {
        return in_array($job->status, ['uploading', 'started', 'analyzing', 'reconstructing']);
    }

    /**
     * Check if job can be reprocessed
     */
    public function canReprocess(TextractJob $job): bool
    {
        return ! $this->isProcessing($job) &&
               (in_array($job->status, ['succeeded', 'failed']) ||
               ($job->metadata['needsReview'] ?? false));
    }

    /**
     * Get OCR quality indicator
     */
    public function getQualityIndicator(?array $metadata): ?array
    {
        if (! $metadata) {
            return null;
        }

        $needsReview = $metadata['needsReview'] ?? false;
        $ocrQuality = $metadata['ocrQuality'] ?? null;

        if ($needsReview && $ocrQuality) {
            return [
                'needs_review' => true,
                'confidence' => $ocrQuality['confidence'] ?? 0,
                'coverage' => $ocrQuality['coverage'] ?? 0,
                'low_conf_pages' => $ocrQuality['low_confidence_pages'] ?? 0,
                'reasons' => $metadata['reviewReasons'] ?? [],
            ];
        }

        if ($ocrQuality && isset($ocrQuality['confidence'])) {
            return [
                'needs_review' => false,
                'confidence' => $ocrQuality['confidence'],
            ];
        }

        return null;
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));

        return round($bytes / pow(1024, $i), 1).' '.$units[$i];
    }

    private function loadCaseOptions(): void
    {
        $cases = LegalCase::query()
            ->select(['id', 'case_number', 'title'])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $this->caseOptions = $cases->map(fn ($c) => [
            'id' => (string) $c->id,
            'label' => (string) ($c->title ?: $c->case_number ?: $c->id),
        ])->all();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
    }

    public function assignJobCase(int $jobId): void
    {
        try {
            $caseId = (string) ($this->selectedCaseForJob[$jobId] ?? '');
            if ($caseId === '') {
                $this->dispatch('error', message: 'Select a case first.');

                return;
            }

            if (! LegalCase::query()->where('id', $caseId)->exists()) {
                $this->dispatch('error', message: 'Selected case not found.');

                return;
            }

            $job = TextractJob::findOrFail($jobId);
            $job->update(['case_id' => $caseId]);
            $this->dispatch('success', message: 'Case assigned successfully.');
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to assign case: '.$e->getMessage());
        }
    }

    public function refreshJobs(): void
    {
        $this->resetPage();
        // Clear stats and pipeline health cache on refresh
        \Illuminate\Support\Facades\Cache::forget('textract_manager_stats');
        \Illuminate\Support\Facades\Cache::forget('textract_pipeline_health');
        $this->dispatch('jobs-refreshed');
    }

    public function syncFromDrive(): void
    {
        try {
            $folderId = $this->folderId ?: env('GOOGLE_DRIVE_FOLDER_ID');
            if (! $folderId) {
                $this->dispatch('error', message: 'Folder ID not configured');

                return;
            }

            $files = ListDrivePdfs::run($folderId);
            $count = 0;

            foreach ($files as $f) {
                $driveId = (string) ($f['id'] ?? '');
                $name = (string) ($f['name'] ?? 'unknown.pdf');

                if ($driveId === '') {
                    continue;
                }

                TextractJob::firstOrCreate(
                    ['drive_file_id' => $driveId],
                    ['drive_file_name' => $name, 'status' => 'queued']
                );
                $count++;
            }

            $this->dispatch('success', message: "Synced {$count} files from Drive");
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Sync failed: '.$e->getMessage());
        }
    }

    public function processJob(int $jobId, bool $sync = false): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            $caseId = (string) ($job->case_id ?: ($this->selectedCaseForJob[$jobId] ?? ''));
            if (! $caseId) {
                $this->dispatch('error', message: 'Please select a case for this job before processing.');

                return;
            }

            if (! LegalCase::query()->where('id', $caseId)->exists()) {
                $this->dispatch('error', message: 'Selected case not found.');

                return;
            }

            if ($job->case_id !== $caseId) {
                $job->update(['case_id' => $caseId]);
            }

            $forceTextract = (bool) ($this->forceTextractForJob[$jobId] ?? false);

            if ($sync) {
                app(\App\Actions\Textract\ProcessDrivePdf::class)->handle($job->drive_file_id, $job->drive_file_name, $forceTextract);
                $this->dispatch('success', message: "Job processed synchronously: {$job->drive_file_name}");
            } else {
                \App\Jobs\ProcessTextractJob::dispatch($job->id);
                $this->dispatch('success', message: "Job queued: {$job->drive_file_name}");
            }

            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to process job: '.$e->getMessage());
        }
    }

    public function processManual(): void
    {
        if (! $this->manualDriveFileId || ! $this->manualDriveFileName) {
            $this->dispatch('error', message: 'Both Drive File ID and Name are required');

            return;
        }

        if (! $this->selectedCaseForManual) {
            $this->dispatch('error', message: 'Please select a case for manual processing.');

            return;
        }

        try {
            $caseId = (string) $this->selectedCaseForManual;
            if (! LegalCase::query()->where('id', $caseId)->exists()) {
                $this->dispatch('error', message: 'Selected case not found.');

                return;
            }

            $job = TextractJob::firstOrCreate(
                ['drive_file_id' => $this->manualDriveFileId],
                ['drive_file_name' => $this->manualDriveFileName, 'status' => 'queued']
            );

            if ($job->case_id !== $caseId) {
                $job->update(['case_id' => $caseId]);
            }

            ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name, $this->forceTextractForManual);
            $this->dispatch('success', message: "Job queued: {$job->drive_file_name}");

            $this->manualDriveFileId = '';
            $this->manualDriveFileName = '';
            $this->selectedCaseForManual = null;
            $this->forceTextractForManual = false;
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to queue job: '.$e->getMessage());
        }
    }

    public function retryJob(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            if (! $job->case_id) {
                $this->dispatch('error', message: 'Please select a case for this job before retrying.');

                return;
            }

            $job->update(['status' => 'queued', 'error' => null]);

            $forceTextract = (bool) ($this->forceTextractForJob[$jobId] ?? false);
            ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name, $forceTextract);
            $this->dispatch('success', message: "Job retried: {$job->drive_file_name}");
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to retry job: '.$e->getMessage());
        }
    }

    public function reprocessJob(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            if (! $job->case_id) {
                $this->dispatch('error', message: 'Please assign a case to this job before re-OCR.');

                return;
            }

            if (! LegalCase::query()->where('id', $job->case_id)->exists()) {
                $this->dispatch('error', message: 'Assigned case not found.');

                return;
            }

            if ($this->isProcessing($job)) {
                $this->dispatch('error', message: 'Cannot re-process: job is currently processing.');

                return;
            }

            $metadata = $job->metadata ?? [];
            unset($metadata['ocrQuality'], $metadata['needsReview'], $metadata['reviewReasons']);

            $job->update([
                'status' => 'queued',
                'error' => null,
                'metadata' => $metadata,
            ]);

            ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name, forceTextract: true);
            $this->dispatch('success', message: "Re-OCR queued for: {$job->drive_file_name}");
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to reprocess job: '.$e->getMessage());
        }
    }

    public function deleteJob(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            $job->delete();
            $this->dispatch('success', message: 'Job deleted');
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to delete job: '.$e->getMessage());
        }
    }

    public function viewJobDetails(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            $this->selectedJobId = $jobId;

            $data = $job->toArray();
            $data['case_id'] = $job->case_id;
            $data['case_label'] = null;

            if ($job->case_id) {
                $case = LegalCase::query()->select(['id', 'title', 'case_number'])->find($job->case_id);
                if ($case) {
                    $data['case_label'] = (string) ($case->title ?: $case->case_number ?: $case->id);
                }
            }

            $data['embedding_status'] = $job->embedding_status ?? 'pending';
            $data['graph_sync_status'] = $job->graph_sync_status ?? 'pending';
            $data['embedding_synced_at'] = $job->embedding_synced_at?->format('Y-m-d H:i:s');
            $data['graph_synced_at'] = $job->graph_synced_at?->format('Y-m-d H:i:s');

            $textractJsonPath = Storage::disk('local')->path('textract/json/'.$job->drive_file_id.'.json');
            $reconstructedPdfPath = Storage::disk('local')->path('textract/output/'.$job->drive_file_id.'-searchable.pdf');

            $data['has_textract_json'] = is_file($textractJsonPath);
            $data['has_reconstructed_pdf'] = is_file($reconstructedPdfPath);
            $data['textract_json_size'] = $data['has_textract_json'] ? filesize($textractJsonPath) : 0;
            $data['reconstructed_pdf_size'] = $data['has_reconstructed_pdf'] ? filesize($reconstructedPdfPath) : 0;

            try {
                $data['has_s3_input'] = $job->s3_key ? Storage::disk('s3')->exists($job->s3_key) : false;
                $data['has_s3_json'] = Storage::disk('s3')->exists('textract/json/'.$job->drive_file_id.'.json');
                $data['has_s3_output'] = Storage::disk('s3')->exists('textract/output/'.$job->drive_file_id.'-searchable.pdf');
            } catch (\Throwable $e) {
                $data['s3_error'] = $e->getMessage();
            }

            $this->selectedJobData = $data;
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to load job details: '.$e->getMessage());
        }
    }

    public function closeJobDetails(): void
    {
        $this->selectedJobId = null;
        $this->selectedJobData = null;
    }

    public function toggleJobCard(int $jobId): void
    {
        if (isset($this->expandedJobs[$jobId])) {
            unset($this->expandedJobs[$jobId]);
        } else {
            $this->expandedJobs[$jobId] = true;
        }
    }

    public function viewContent(int $jobId): void
    {
        try {
            $job = TextractJob::with(['editor', 'case', 'documents'])->findOrFail($jobId);

            $this->viewingContent = [
                'id' => $job->id,
                'drive_file_name' => $job->drive_file_name,
                'status' => $job->status,
                'extracted_content' => $job->extracted_content,
                'manual_content' => $job->manual_content,
                'effective_content' => $job->effective_content,
                'manually_edited' => $job->manually_edited,
                'content_edited_at' => $job->content_edited_at?->format('Y-m-d H:i:s'),
                'edited_by_name' => $job->editor?->name,
                'embedding_status' => $job->embedding_status,
                'graph_sync_status' => $job->graph_sync_status,
                'embedding_synced_at' => $job->embedding_synced_at?->format('Y-m-d H:i:s'),
                'graph_synced_at' => $job->graph_synced_at?->format('Y-m-d H:i:s'),
                'case_label' => $job->case ? ($job->case->title ?: $job->case->case_number) : null,
                'document_count' => $job->documents->count(),
                'metadata' => $job->metadata,
            ];

            $this->showContentViewModal = true;
            $this->contentTab = 'content';
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to load content: '.$e->getMessage());
        }
    }

    public function closeViewModal(): void
    {
        $this->showContentViewModal = false;
        $this->viewingContent = null;
        $this->contentTab = 'content';
    }

    public function editContent(int $jobId): void
    {
        try {
            $job = TextractJob::with(['editor', 'case', 'documents'])->findOrFail($jobId);

            // Allow opening editor even if there's no content yet, user can input manually
            $defaultManual = $job->manual_content ?? $job->extracted_content ?? '';

            $this->editingContent = [
                'id' => $job->id,
                'drive_file_name' => $job->drive_file_name,
                'status' => $job->status,
                'extracted_content' => $job->extracted_content,
                'manual_content' => $defaultManual,
                'manually_edited' => $job->manually_edited,
                'original_manual_content' => $job->manual_content,
                'embedding_status' => $job->embedding_status,
                'graph_sync_status' => $job->graph_sync_status,
                'case_label' => $job->case ? ($job->case->title ?: $job->case->case_number) : null,
            ];

            $this->showContentEditModal = true;
            $this->contentTab = 'content';
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to load content for editing: '.$e->getMessage());
        }
    }

    public function closeEditModal(): void
    {
        $this->showContentEditModal = false;
        $this->editingContent = [];
        $this->contentTab = 'content';
    }

    public function saveContent(): void
    {
        try {
            $jobId = (int) ($this->editingContent['id'] ?? 0);
            if (! $jobId) {
                $this->dispatch('error', message: 'Invalid job ID');

                return;
            }

            $job = TextractJob::findOrFail($jobId);
            $manualContent = (string) ($this->editingContent['manual_content'] ?? '');

            if (trim($manualContent) === '') {
                $this->dispatch('error', message: 'Content cannot be empty');

                return;
            }

            $originalManualContent = (string) ($this->editingContent['original_manual_content'] ?? '');
            if ($manualContent === $originalManualContent) {
                $this->dispatch('info', message: 'No changes detected');
                $this->closeEditModal();

                return;
            }

            $job->update([
                'manual_content' => $manualContent,
                'manually_edited' => true,
                'content_edited_at' => now(),
                'edited_by' => auth()->id(),
                'embedding_status' => 'pending',
                'graph_sync_status' => 'pending',
            ]);

            $this->dispatch('success', message: 'Content saved successfully. Embeddings and graph sync will be regenerated.');
            $this->closeEditModal();
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to save content: '.$e->getMessage());
        }
    }

    public function resetToOriginal(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            if (! $job->manually_edited) {
                $this->dispatch('info', message: 'Content has not been manually edited');

                return;
            }

            if (empty($job->extracted_content)) {
                $this->dispatch('error', message: 'No original content available');

                return;
            }

            $job->update([
                'manual_content' => null,
                'manually_edited' => false,
                'content_edited_at' => null,
                'edited_by' => null,
                'embedding_status' => 'pending',
                'graph_sync_status' => 'pending',
            ]);

            $this->dispatch('success', message: 'Content reset to original. Embeddings and graph will be regenerated.');
            $this->closeEditModal();
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to reset content: '.$e->getMessage());
        }
    }

    public function regenerateEmbeddings(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            if (empty($job->effective_content)) {
                $this->dispatch('error', message: 'No content available for embedding generation');

                return;
            }

            if (! in_array($job->status, ['completed', 'succeeded'])) {
                $this->dispatch('error', message: 'Job must be in completed status to regenerate embeddings');

                return;
            }

            \App\Jobs\RegenerateTextractEmbeddings::dispatch($jobId);
            $job->update(['embedding_status' => 'pending']);

            $this->dispatch('success', message: 'Embedding regeneration queued');
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to queue embedding regeneration: '.$e->getMessage());
        }
    }

    /**
     * Approve a job flagged for review and dispatch embedding generation.
     *
     * When OCR quality checks flag a job with needsReview=true, auto-embedding
     * is blocked. This action allows a human reviewer to clear the flag and
     * trigger embedding generation after confirming the content is acceptable.
     */
    public function approveAndEmbed(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            $metadata = $job->metadata ?? [];
            $metadata['needsReview'] = false;
            $metadata['reviewApprovedAt'] = now()->toIso8601String();
            $job->update(['metadata' => $metadata]);

            \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);

            $this->dispatch('notify', message: 'Review approved, embedding queued');
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to approve and embed: '.$e->getMessage());
        }
    }

    public function syncToGraph(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            if (! config('neo4j.sync.enabled', false)) {
                $this->dispatch('error', message: 'Neo4j sync is not enabled');

                return;
            }

            if ($job->embedding_status !== 'synced') {
                $this->dispatch('error', message: 'Embeddings must be synced before graph sync. Please regenerate embeddings first.');

                return;
            }

            if (! in_array($job->status, ['completed', 'succeeded'])) {
                $this->dispatch('error', message: 'Job must be in completed status to sync to graph');

                return;
            }

            \App\Jobs\SyncTextractToGraph::dispatch($jobId);
            $job->update(['graph_sync_status' => 'pending']);

            $this->dispatch('success', message: 'Graph sync queued');
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to queue graph sync: '.$e->getMessage());
        }
    }

    /**
     * Open PDF preview modal for a document
     */
    public function previewPdf(int $documentId): void
    {
        $document = TextractDocument::findOrFail($documentId);

        // Verify document has S3 output path
        if (empty($document->s3_output_path)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot preview: document has no PDF file',
            ]);

            return;
        }

        try {
            // Generate signed URL for PDF access
            $pdfService = app(\App\Services\Textract\TextractPdfService::class);
            $this->pdfSignedUrl = $pdfService->getSignedPdfUrl($document);

            $this->previewDocumentId = $documentId;
            $this->showPdfModal = true;

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to generate preview URL: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Close PDF preview modal
     */
    public function closePdfModal(): void
    {
        $this->showPdfModal = false;
        $this->previewDocumentId = null;
        $this->pdfSignedUrl = null;
    }

    public function getJobsProperty()
    {
        // Eager load relationships to prevent N+1 queries
        $query = TextractJob::query()
            ->with(['case:id,title,case_number', 'editor:id,name'])
            ->orderBy('updated_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('drive_file_name', 'like', '%'.$this->search.'%')
                    ->orWhere('drive_file_id', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'needs_review') {
                $query->whereRaw("(metadata->>'needsReview')::boolean IS TRUE");
            } else {
                $query->where('status', $this->statusFilter);
            }
        }

        return $query->paginate($this->perPage);
    }

    public function getStatsProperty()
    {
        // Cache stats for 30 seconds to reduce database load
        return \Illuminate\Support\Facades\Cache::remember('textract_manager_stats', 30, function () {
            return [
                'total' => TextractJob::count(),
                'queued' => TextractJob::where('status', 'queued')->count(),
                'processing' => TextractJob::whereIn('status', ['started', 'uploading', 'analyzing', 'reconstructing'])->count(),
                'succeeded' => TextractJob::where('status', 'succeeded')->count(),
                'failed' => TextractJob::where('status', 'failed')->count(),
                'needs_review' => TextractJob::whereRaw("(metadata->>'needsReview')::boolean IS TRUE")->count(),
            ];
        });
    }

    /**
     * Pipeline health metrics for the health dashboard.
     *
     * Provides: embedding status breakdown, graph sync breakdown,
     * average pipeline duration, recent failed jobs, and stale pending count.
     *
     * Uses updated_at for succeeded jobs as duration approximation
     * since completed_at column does not exist.
     */
    public function getPipelineHealthProperty(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('textract_pipeline_health', 30, function () {
            $embeddingBreakdown = TextractJob::query()
                ->selectRaw('embedding_status, count(*) as count')
                ->groupBy('embedding_status')
                ->pluck('count', 'embedding_status')
                ->toArray();

            $graphSyncBreakdown = TextractJob::query()
                ->selectRaw('graph_sync_status, count(*) as count')
                ->groupBy('graph_sync_status')
                ->pluck('count', 'graph_sync_status')
                ->toArray();

            $avgDuration = TextractJob::where('status', 'succeeded')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_seconds')
                ->value('avg_seconds');

            $failedJobs = TextractJob::where('status', 'failed')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get(['id', 'drive_file_name', 'error', 'updated_at'])
                ->toArray();

            $stalePending = TextractJob::where('status', 'pending')
                ->where('created_at', '<', now()->subHour())
                ->count();

            return [
                'embedding_breakdown' => $embeddingBreakdown,
                'graph_sync_breakdown' => $graphSyncBreakdown,
                'avg_pipeline_duration_seconds' => round((float) ($avgDuration ?? 0), 1),
                'failed_jobs' => $failedJobs,
                'stale_pending_count' => $stalePending,
            ];
        });
    }

    public function getStoragePreviewProperty(): array
    {
        $disk = Storage::disk('local');
        $folders = [
            'source' => 'textract/source',
            'json' => 'textract/json',
            'output' => 'textract/output',
        ];

        $preview = [];
        foreach ($folders as $key => $path) {
            try {
                $files = $disk->files($path);
                $entries = [];
                foreach ($files as $f) {
                    $full = $disk->path($f);
                    $entries[] = [
                        'name' => basename($f),
                        'path' => $f,
                        'size' => is_file($full) ? filesize($full) : 0,
                        'mtime' => is_file($full) ? date('Y-m-d H:i:s', filemtime($full)) : null,
                    ];
                }
                usort($entries, fn ($a, $b) => strcmp((string) ($b['mtime'] ?? ''), (string) ($a['mtime'] ?? '')));
                $preview[$key] = [
                    'path' => $path,
                    'count' => count($entries),
                    'entries' => array_slice($entries, 0, 50),
                ];
            } catch (\Throwable $e) {
                $preview[$key] = [
                    'path' => $path,
                    'count' => 0,
                    'entries' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $preview;
    }

    public function render()
    {
        return view('livewire.textract-manager', [
            'jobs' => $this->jobs,
            'stats' => $this->stats,
            'pipelineHealth' => $this->pipelineHealth,
            'storagePreview' => $this->storagePreview,
        ]);
    }
}
