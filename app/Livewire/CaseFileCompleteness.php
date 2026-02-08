<?php

namespace App\Livewire;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CaseFileCompleteness extends Component
{
    public string $caseId;

    public ?array $caseData = null;

    public array $uploads = [];

    public array $documents = [];

    public array $textractJobs = [];

    public array $stats = [
        'total_uploads' => 0,
        'processed' => 0,
        'pending' => 0,
        'failed' => 0,
    ];

    public string $viewMode = 'matrica';

    public string $filter = 'svi';

    public ?string $lastLoadedAt = null;

    public ?string $error = null;

    public function mount(string $case): void
    {
        $this->caseId = $case;
        $this->loadCase();
    }

    public function loadCase(): void
    {
        $this->error = null;

        try {
            $case = LegalCase::with(['uploads', 'documents', 'textractJobs'])->find($this->caseId);
        } catch (\Throwable $e) {
            $this->error = 'Greška pri učitavanju podataka.';
            Log::error('CaseFileCompleteness.loadCase failed', ['case_id' => $this->caseId, 'error' => $e->getMessage()]);

            return;
        }

        if (! $case) {
            return;
        }

        Log::info('CaseFileCompleteness.loadCase', [
            'case_id' => $this->caseId,
            'uploads' => $case->uploads->count(),
            'documents' => $case->documents->count(),
            'textract_jobs' => $case->textractJobs->count(),
        ]);

        $this->caseData = [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'title' => $case->title,
            'client_name' => $case->client_name,
            'opponent_name' => $case->opponent_name,
            'court' => $case->court ?? 'N/A',
            'jurisdiction' => $case->jurisdiction ?? 'N/A',
            'judge' => $case->judge ?? 'N/A',
            'filing_date' => $case->filing_date?->format('Y-m-d'),
            'status' => $case->status ?? 'active',
            'case_type' => $case->case_type,
            'tags' => $case->tags ?? [],
            'description' => $case->description,
        ];

        // Uploads (raw files)
        $this->uploads = $case->uploads
            ->sortByDesc('created_at')
            ->map(fn (CaseDocumentUpload $upload) => [
                'id' => $upload->id,
                'filename' => $upload->original_filename,
                'mime_type' => $upload->mime_type,
                'file_size' => $upload->file_size,
                'status' => $upload->status ?? 'uploaded',
                'uploaded_at' => $upload->uploaded_at?->format('Y-m-d H:i') ?? $upload->created_at?->format('Y-m-d H:i'),
                'has_document' => $case->documents->where('upload_id', $upload->id)->isNotEmpty(),
                'error' => $upload->error,
            ])
            ->values()
            ->toArray();

        // Processed document chunks
        $this->documents = $case->documents
            ->sortByDesc('created_at')
            ->map(fn (CaseDocument $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'category' => $doc->category,
                'author' => $doc->author,
                'language' => $doc->language,
                'tags' => $doc->tags ?? [],
                'chunk_index' => $doc->chunk_index,
                'has_content' => ! empty($doc->content),
                'has_embedding' => ! empty($doc->embedding_vector) && $doc->embedding_vector !== array_fill(0, 1536, 0.0),
                'token_count' => $doc->token_count,
                'document_date' => $doc->document_date?->format('Y-m-d'),
                'content_hash' => $doc->content_hash ? substr($doc->content_hash, 0, 8) : null,
            ])
            ->values()
            ->toArray();

        // Textract OCR jobs
        $this->textractJobs = $case->textractJobs
            ->sortByDesc('created_at')
            ->map(fn (TextractJob $job) => [
                'id' => $job->id,
                'filename' => $job->drive_file_name,
                'status' => $job->status,
                'ocr_engine' => $job->ocr_engine ?? 'textract',
                'has_content' => ! empty($job->effective_content),
                'manually_edited' => $job->manually_edited,
                'embedding_status' => $job->embedding_status,
                'graph_sync_status' => $job->graph_sync_status,
                'needs_review' => $job->needsReview(),
                'review_reasons' => $job->getReviewReasons(),
                'created_at' => $job->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->toArray();

        // Stats
        $totalUploads = count($this->uploads);
        $processed = collect($this->uploads)->where('has_document', true)->count();
        $jobsFailed = collect($this->textractJobs)->where('status', 'failed')->count();
        $jobsPending = collect($this->textractJobs)->whereIn('status', ['pending', 'queued', 'processing'])->count();

        $this->stats = [
            'total_uploads' => $totalUploads,
            'processed' => $processed,
            'pending' => $jobsPending,
            'failed' => $jobsFailed,
        ];

        $this->lastLoadedAt = now()->format('H:i:s');
    }

    public function getDocsByCategoryProperty(): array
    {
        $grouped = collect($this->documents)->groupBy('category');

        return $grouped->map(fn ($docs, $category) => [
            'category' => $category ?: 'Uncategorized',
            'count' => $docs->count(),
            'documents' => $docs->values()->toArray(),
        ])->values()->toArray();
    }

    public function getFilteredUploadsProperty(): array
    {
        return match ($this->filter) {
            'prisutni' => collect($this->uploads)->where('has_document', true)->values()->toArray(),
            'nedostaju' => collect($this->uploads)->where('has_document', false)->values()->toArray(),
            default => $this->uploads,
        };
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function render()
    {
        return view('livewire.case-file-completeness');
    }
}
