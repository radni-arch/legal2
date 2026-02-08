<?php

namespace App\Http\Livewire\Components;

use App\Models\TextractJob;
use Livewire\Component;

class TextractStatusBadge extends Component
{
    public TextractJob $job;

    public function getOcrStatusColorProperty(): string
    {
        return match ($this->job->status) {
            'completed', 'succeeded' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'queued' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            default => 'bg-gray-400',
        };
    }

    public function getEmbeddingStatusColorProperty(): string
    {
        return match ($this->job->embedding_status) {
            'synced' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'pending' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            'blocked' => 'bg-orange-500',
            default => 'bg-gray-400',
        };
    }

    public function getGraphStatusColorProperty(): string
    {
        return match ($this->job->graph_sync_status) {
            'synced' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'pending' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            'blocked' => 'bg-orange-500',
            default => 'bg-gray-400',
        };
    }

    public function getOcrTooltipProperty(): string
    {
        return match ($this->job->status) {
            'completed', 'succeeded' => 'OCR completed',
            'processing' => 'Processing...',
            'queued' => 'Queued for processing',
            'failed' => 'Failed: '.($this->job->error ?? 'Unknown error'),
            default => 'Unknown status',
        };
    }

    public function getEmbeddingTooltipProperty(): string
    {
        return match ($this->job->embedding_status) {
            'synced' => 'Embeddings synced at '.$this->job->embedding_synced_at?->format('M j, Y H:i'),
            'processing' => 'Generating embeddings...',
            'pending' => 'Waiting for embeddings',
            'failed' => 'Embedding failed',
            'blocked' => 'Blocked: embeddings failed',
            default => 'Unknown status',
        };
    }

    public function getGraphTooltipProperty(): string
    {
        return match ($this->job->graph_sync_status) {
            'synced' => 'Graph synced at '.$this->job->graph_synced_at?->format('M j, Y H:i'),
            'processing' => 'Syncing to graph...',
            'pending' => 'Waiting for graph sync',
            'failed' => 'Graph sync failed',
            'blocked' => 'Blocked: waiting for embeddings',
            default => 'Unknown status',
        };
    }

    public function render()
    {
        return view('livewire.components.textract-status-badge');
    }
}
