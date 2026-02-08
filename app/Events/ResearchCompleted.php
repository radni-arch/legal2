<?php

namespace App\Events;

use App\Models\AgentRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResearchCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AgentRun $run;

    public function __construct(AgentRun $run)
    {
        $this->run = $run;
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
        return 'research.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'status' => $this->run->status,
            'score' => $this->run->score,
            'iterations' => $this->run->current_iteration,
            'tokens_used' => $this->run->tokens_used,
            'cost_spent' => $this->run->cost_spent,
            'elapsed_seconds' => $this->run->elapsed_seconds,
            'final_output' => $this->run->final_output,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
