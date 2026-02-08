<?php

namespace App\Services\Collaboration;

use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use Illuminate\Support\Facades\Log;

/**
 * Shared context system for multi-agent collaboration
 *
 * Provides a shared memory space where agents can:
 * - Read/write shared data
 * - Send messages to other agents
 * - Access outputs from other agents
 * - Track collaboration state
 */
class SharedAgentContext
{
    protected AgentCollaboration $collaboration;

    protected ?AgentExecution $currentExecution = null;

    public function __construct(AgentCollaboration $collaboration)
    {
        $this->collaboration = $collaboration;
    }

    /**
     * Set the current agent execution context
     */
    public function setCurrentExecution(AgentExecution $execution): void
    {
        $this->currentExecution = $execution;
    }

    /**
     * Write data to shared memory
     */
    public function write(string $key, $value): void
    {
        $this->collaboration->addToSharedMemory($key, $value);

        Log::info('SharedContext - Write', [
            'collaboration_id' => $this->collaboration->id,
            'agent' => $this->currentExecution?->agent_name ?? 'unknown',
            'key' => $key,
        ]);
    }

    /**
     * Read data from shared memory
     */
    public function read(string $key, $default = null)
    {
        return $this->collaboration->getFromSharedMemory($key, $default);
    }

    /**
     * Check if a key exists in shared memory
     */
    public function has(string $key): bool
    {
        $memory = $this->collaboration->shared_memory ?? [];

        return isset($memory[$key]);
    }

    /**
     * Get all shared memory
     */
    public function all(): array
    {
        return $this->collaboration->shared_memory ?? [];
    }

    /**
     * Send a message to another agent
     */
    public function sendMessage(string $targetAgent, array $message): void
    {
        if (! $this->currentExecution) {
            Log::warning('SharedContext - Cannot send message without current execution context');

            return;
        }

        $this->currentExecution->sendMessageTo($targetAgent, $message);

        // Also store in shared memory for easier access
        $messagesKey = "messages_{$this->currentExecution->agent_name}_to_{$targetAgent}";
        $messages = $this->read($messagesKey, []);
        $messages[] = array_merge($message, ['timestamp' => now()->toIso8601String()]);
        $this->write($messagesKey, $messages);

        Log::info('SharedContext - Message sent', [
            'from' => $this->currentExecution->agent_name,
            'to' => $targetAgent,
            'collaboration_id' => $this->collaboration->id,
        ]);
    }

    /**
     * Get messages sent to a specific agent
     */
    public function getMessagesFor(string $targetAgent): array
    {
        if (! $this->currentExecution) {
            return [];
        }

        $messagesKey = "messages_{$this->currentExecution->agent_name}_to_{$targetAgent}";

        return $this->read($messagesKey, []);
    }

    /**
     * Get output from another agent
     */
    public function getAgentOutput(string $agentName): ?array
    {
        $execution = $this->collaboration->executions()
            ->where('agent_name', $agentName)
            ->where('status', 'completed')
            ->first();

        return $execution?->output;
    }

    /**
     * Get outputs from multiple agents
     */
    public function getAgentOutputs(array $agentNames): array
    {
        $outputs = [];
        foreach ($agentNames as $agentName) {
            $output = $this->getAgentOutput($agentName);
            if ($output !== null) {
                $outputs[$agentName] = $output;
            }
        }

        return $outputs;
    }

    /**
     * Get all completed agent outputs
     */
    public function getAllAgentOutputs(): array
    {
        $executions = $this->collaboration->completedExecutions;
        $outputs = [];

        foreach ($executions as $execution) {
            $outputs[$execution->agent_name] = $execution->output;
        }

        return $outputs;
    }

    /**
     * Check if an agent has completed its task
     */
    public function hasAgentCompleted(string $agentName): bool
    {
        return $this->collaboration->executions()
            ->where('agent_name', $agentName)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get collaboration status
     */
    public function getStatus(): string
    {
        return $this->collaboration->status;
    }

    /**
     * Get problem statement
     */
    public function getProblemStatement(): string
    {
        return $this->collaboration->problem_statement;
    }

    /**
     * Get problem type
     */
    public function getProblemType(): ?string
    {
        return $this->collaboration->problem_type;
    }

    /**
     * Get initial context
     */
    public function getInitialContext(): array
    {
        return $this->collaboration->context ?? [];
    }

    /**
     * Get the collaboration model
     */
    public function getCollaboration(): AgentCollaboration
    {
        return $this->collaboration;
    }

    /**
     * Add tokens used by current agent
     */
    public function addTokensUsed(int $tokens): void
    {
        if ($this->currentExecution) {
            $this->currentExecution->addTokensUsed($tokens);
        } else {
            $this->collaboration->addTokensUsed($tokens);
        }
    }

    /**
     * Log an event in the collaboration
     */
    public function logEvent(string $event, array $data = []): void
    {
        Log::info('SharedContext - Event', array_merge([
            'collaboration_id' => $this->collaboration->id,
            'agent' => $this->currentExecution?->agent_name ?? 'orchestrator',
            'event' => $event,
        ], $data));
    }

    /**
     * Get a summary of the current collaboration state
     */
    public function getSummary(): array
    {
        return [
            'collaboration_id' => $this->collaboration->id,
            'session_id' => $this->collaboration->session_id,
            'status' => $this->collaboration->status,
            'problem_statement' => $this->collaboration->problem_statement,
            'agents_involved' => $this->collaboration->agents_involved ?? [],
            'progress' => $this->collaboration->progress,
            'completed_steps' => $this->collaboration->completed_steps,
            'total_steps' => $this->collaboration->total_steps,
            'tokens_used' => $this->collaboration->tokens_used,
            'cost_spent' => $this->collaboration->cost_spent,
            'started_at' => $this->collaboration->started_at?->toIso8601String(),
            'completed_executions' => $this->collaboration->completedExecutions->count(),
            'total_executions' => $this->collaboration->executions->count(),
        ];
    }
}
