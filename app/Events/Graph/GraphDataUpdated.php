<?php

namespace App\Events\Graph;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GraphDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $updateType,
        public int $nodesAffected,
        public int $relationshipsAffected,
        public ?string $decisionId = null,
        public ?string $message = null
    ) {
        $this->message = $message ?? $this->generateMessage();
    }

    protected function generateMessage(): string
    {
        return match ($this->updateType) {
            'sync_complete' => "Graph sync complete: {$this->nodesAffected} nodes, {$this->relationshipsAffected} relationships",
            'new_decision' => "New decision added to graph",
            'new_relationships' => "{$this->relationshipsAffected} new relationships discovered",
            default => "Graph data updated",
        };
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('graph-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'graph.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->updateType,
            'nodes' => $this->nodesAffected,
            'relationships' => $this->relationshipsAffected,
            'decisionId' => $this->decisionId,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
