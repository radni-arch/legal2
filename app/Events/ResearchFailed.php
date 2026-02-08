<?php

namespace App\Events;

use App\Models\AgentRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResearchFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AgentRun $run;

    public string $error;

    public function __construct(AgentRun $run, string $error)
    {
        $this->run = $run;
        $this->error = $error;
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
        return 'research.failed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'status' => 'failed',
            'error' => $this->error,
            'iteration' => $this->run->current_iteration,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
