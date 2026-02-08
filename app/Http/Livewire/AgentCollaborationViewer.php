<?php

namespace App\Http\Livewire;

use App\Models\AgentCommunication;
use App\Models\OrchestrationLog;
use Livewire\Component;

/**
 * Agent Collaboration Viewer Livewire Component
 *
 * Sprint 3.4: Agent Collaboration UI
 *
 * Displays real-time agent collaboration status, execution timeline,
 * shared context, and inter-agent messages.
 */
class AgentCollaborationViewer extends Component
{
    public ?string $orchestrationId = null;

    public ?OrchestrationLog $orchestration = null;

    public string $status = 'loading';

    public string $taskDescription = '';

    public array $agentPipeline = [];

    public array $executionHistory = [];

    public array $sharedContext = [];

    public array $messages = [];

    public int $tokensUsed = 0;

    public float $costSpent = 0.0;

    public int $durationMs = 0;

    public int $completedAgents = 0;

    public int $failedAgents = 0;

    public ?string $errorMessage = null;

    // Polling interval in milliseconds
    public int $pollingInterval = 3000; // 3 seconds

    protected $listeners = ['refreshData'];

    /**
     * Mount the component
     */
    public function mount(string $orchestrationId): void
    {
        $this->orchestrationId = $orchestrationId;
        $this->loadData();
    }

    /**
     * Load orchestration data
     */
    public function loadData(): void
    {
        try {
            $this->orchestration = OrchestrationLog::where('orchestration_id', $this->orchestrationId)
                ->select([
                    'id',
                    'orchestration_id',
                    'status',
                    'task_description',
                    'agent_pipeline',
                    'execution_history',
                    'shared_context',
                    'tokens_used',
                    'cost_spent',
                    'duration_ms',
                    'completed_agents',
                    'failed_agents',
                    'error_message',
                ])
                ->first();
        } catch (\Exception $e) {
            // Handle invalid UUID or other database errors
            $this->orchestration = null;
        }

        if (! $this->orchestration) {
            $this->status = 'not_found';

            return;
        }

        // Load data from orchestration
        $this->status = $this->orchestration->status ?? 'unknown';
        $this->taskDescription = $this->orchestration->task_description ?? '';
        $this->agentPipeline = $this->orchestration->agent_pipeline ?? [];
        $this->executionHistory = $this->orchestration->execution_history ?? [];
        $this->sharedContext = $this->orchestration->shared_context ?? [];
        $this->tokensUsed = $this->orchestration->tokens_used ?? 0;
        $this->costSpent = (float) ($this->orchestration->cost_spent ?? 0.0);
        $this->durationMs = $this->orchestration->duration_ms ?? 0;
        $this->completedAgents = $this->orchestration->completed_agents ?? 0;
        $this->failedAgents = $this->orchestration->failed_agents ?? 0;
        $this->errorMessage = $this->orchestration->error_message;

        // Load inter-agent messages
        $this->loadMessages();
    }

    /**
     * Load inter-agent messages
     */
    public function loadMessages(): void
    {
        // Note: orchestration_id column doesn't exist in agent_communications table
        // For now, load recent messages without filtering by orchestration
        // TODO: Add orchestration_id column in future migration for proper filtering
        try {
            $this->messages = AgentCommunication::query()
                ->select([
                    'id',
                    'sender_agent_type',
                    'receiver_agent_type',
                    'message_type',
                    'message_data',
                    'priority',
                    'status',
                    'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->limit(50) // Prevent loading thousands of messages
                ->get()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'sender' => $msg->sender_agent_type ?? 'Unknown',
                    'receiver' => $msg->receiver_agent_type ?? 'Unknown',
                    'type' => $msg->message_type ?? 'message',
                    'payload' => $msg->message_data ?? [],
                    'priority' => $msg->priority ?? 5,
                    'status' => $msg->status ?? 'pending',
                    'created_at' => $msg->created_at?->format('Y-m-d H:i:s'),
                ])
                ->toArray();
        } catch (\Exception $e) {
            // If there's an error, just set messages to empty array
            $this->messages = [];
        }
    }

    /**
     * Refresh data (called by polling or manually)
     */
    public function refreshData(): void
    {
        $this->loadData();
    }

    /**
     * Get status badge color
     */
    public function getStatusColorProperty(): string
    {
        return match ($this->status) {
            'pending' => 'bg-gray-500',
            'running' => 'bg-blue-500',
            'completed' => 'bg-green-500',
            'failed' => 'bg-red-500',
            'not_found' => 'bg-yellow-500',
            default => 'bg-gray-400',
        };
    }

    /**
     * Check if orchestration is still running (for polling)
     */
    public function getIsRunningProperty(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.agent-collaboration-viewer');
    }
}
