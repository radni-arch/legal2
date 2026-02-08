<?php

namespace App\Http\Livewire\Components;

use App\Jobs\ProcessTextractJob;
use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use Livewire\Component;

class TextractJobActions extends Component
{
    public TextractJob $job;

    protected $listeners = ['refreshJob' => '$refresh'];

    public function getAvailableActionsProperty(): array
    {
        $actions = [];

        // OCR Actions
        if ($this->job->status === 'failed') {
            $actions[] = ['key' => 'retryOcr', 'label' => 'Retry OCR', 'icon' => 'refresh', 'group' => 'processing', 'danger' => true];
        }
        if (in_array($this->job->status, ['completed', 'succeeded'])) {
            $actions[] = ['key' => 'reExtract', 'label' => 'Re-extract', 'icon' => 'document-text', 'group' => 'processing', 'danger' => false];
        }

        // Review Actions
        if ($this->job->needsReview()) {
            $actions[] = ['key' => 'markAsReviewed', 'label' => 'Mark as Reviewed', 'icon' => 'check-circle', 'group' => 'review', 'danger' => false];
        }

        // Embedding Actions
        if (in_array($this->job->status, ['completed', 'succeeded']) && $this->job->embedding_status !== 'processing') {
            $actions[] = ['key' => 'generateEmbeddings', 'label' => 'Generate Embeddings', 'icon' => 'cube', 'group' => 'sync', 'danger' => false];
        }
        if ($this->job->embedding_status === 'failed') {
            $actions[] = ['key' => 'retryEmbeddings', 'label' => 'Retry Embeddings', 'icon' => 'refresh', 'group' => 'sync', 'danger' => true];
        }

        // Graph Actions
        if ($this->job->embedding_status === 'synced' && $this->job->graph_sync_status !== 'processing') {
            $actions[] = ['key' => 'syncToGraph', 'label' => 'Sync to Graph', 'icon' => 'share', 'group' => 'sync', 'danger' => false];
        }
        if ($this->job->graph_sync_status === 'failed') {
            $actions[] = ['key' => 'retryGraphSync', 'label' => 'Retry Graph Sync', 'icon' => 'refresh', 'group' => 'sync', 'danger' => true];
        }

        // View Actions
        if (in_array($this->job->status, ['completed', 'succeeded'])) {
            $actions[] = ['key' => 'viewContent', 'label' => 'View Content', 'icon' => 'eye', 'group' => 'view', 'danger' => false];
            $actions[] = ['key' => 'editContent', 'label' => 'Edit Content', 'icon' => 'pencil', 'group' => 'view', 'danger' => false];
        }
        if ($this->job->graph_sync_status === 'synced') {
            $actions[] = ['key' => 'viewInGraph', 'label' => 'View in Graph', 'icon' => 'globe', 'group' => 'view', 'danger' => false];
        }

        return $actions;
    }

    public function retryOcr(): void
    {
        $this->job->update(['status' => 'queued', 'error' => null]);
        ProcessTextractJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'OCR retry queued');
        $this->dispatch('refreshJobs');
    }

    public function reExtract(): void
    {
        $this->job->update([
            'status' => 'queued',
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
            'error' => null,
        ]);
        ProcessTextractJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Re-extraction queued');
        $this->dispatch('refreshJobs');
    }

    public function generateEmbeddings(): void
    {
        $this->job->update(['embedding_status' => 'pending']);
        // ADR-001: Use RegenerateTextractEmbeddings (System B) as canonical embedding job
        RegenerateTextractEmbeddings::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Embedding generation queued');
        $this->dispatch('refreshJobs');
    }

    public function retryEmbeddings(): void
    {
        $this->job->update(['embedding_status' => 'pending', 'error' => null]);
        // ADR-001: Use RegenerateTextractEmbeddings (System B) as canonical embedding job
        RegenerateTextractEmbeddings::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Embedding retry queued');
        $this->dispatch('refreshJobs');
    }

    public function syncToGraph(): void
    {
        $this->job->update(['graph_sync_status' => 'pending']);
        SyncTextractToGraph::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Graph sync queued');
        $this->dispatch('refreshJobs');
    }

    public function retryGraphSync(): void
    {
        $this->job->update(['graph_sync_status' => 'pending', 'error' => null]);
        SyncTextractToGraph::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Graph sync retry queued');
        $this->dispatch('refreshJobs');
    }

    public function markAsReviewed(): void
    {
        // Clear the needsReview flag from metadata
        $this->job->clearNeedsReview();

        // Dispatch embedding job with skipReviewCheck option to bypass the review gate
        // since the user has now reviewed the content
        if (in_array($this->job->status, ['completed', 'succeeded'])
            && ! empty($this->job->effective_content)) {
            RegenerateTextractEmbeddings::dispatch($this->job->id, ['skipReviewCheck' => true]);
            $this->dispatch('notify', message: 'Marked as reviewed, embedding generation queued');
        } else {
            $this->dispatch('notify', message: 'Marked as reviewed');
        }

        $this->dispatch('refreshJobs');
    }

    public function viewContent(): void
    {
        $this->dispatch('openContentModal', jobId: $this->job->id);
    }

    public function editContent(): void
    {
        $this->dispatch('openEditModal', jobId: $this->job->id);
    }

    public function viewInGraph(): void
    {
        $this->dispatch('openGraphView', jobId: $this->job->id);
    }

    public function render()
    {
        return view('livewire.components.textract-job-actions');
    }
}
