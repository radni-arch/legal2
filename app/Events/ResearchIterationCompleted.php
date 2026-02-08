<?php

namespace App\Events;

use App\Models\AgentRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResearchIterationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AgentRun $run;

    public array $iteration;

    public function __construct(AgentRun $run, array $iteration)
    {
        $this->run = $run;
        $this->iteration = $iteration;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('agent-run.'.$this->run->id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'iteration.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'iteration' => $this->run->current_iteration,
            'max_iterations' => $this->run->max_iterations,
            'progress' => round(($this->run->current_iteration / $this->run->max_iterations) * 100, 1),
            'insights_count' => count($this->iteration['insights'] ?? []),
            'actions_taken' => count($this->iteration['actions'] ?? []),
            'tokens_used' => $this->run->tokens_used,
            'cost_spent' => $this->run->cost_spent,
            'status' => $this->run->status,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
