<?php

namespace App\Services\Agents;

use App\Models\OrchestrationLog;
use Exception;

class OrchestratorService
{
    /**
     * Orchestrate a pipeline of agents.
     *
     * @param  array  $pipeline  Array of agent names to execute in order
     * @param  string  $taskDescription  Description of the task
     * @param  array  $initialContext  Initial shared context
     * @param  array  $budgets  Budget limits (token_budget, cost_budget, time_budget_ms)
     * @return string Orchestration ID
     */
    public function orchestrate(
        array $pipeline,
        string $taskDescription,
        array $initialContext = [],
        array $budgets = []
    ): string {
        $log = OrchestrationLog::create([
            'task_description' => $taskDescription,
            'agent_pipeline' => $pipeline,
            'total_agents' => count($pipeline),
            'status' => 'pending',
            'shared_context' => $initialContext,
            'execution_history' => [],
            'token_budget' => $budgets['token_budget'] ?? null,
            'cost_budget' => $budgets['cost_budget'] ?? null,
            'time_budget_ms' => $budgets['time_budget_ms'] ?? null,
        ]);

        return $log->orchestration_id;
    }

    /**
     * Execute the orchestrated pipeline.
     *
     * @param  string  $orchestrationId  Orchestration ID
     * @return array Execution result
     */
    public function execute(string $orchestrationId): array
    {
        $log = OrchestrationLog::where('orchestration_id', $orchestrationId)->firstOrFail();

        $log->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);
        $sharedContext = $log->shared_context ?? [];
        $executionHistory = [];
        $tokensUsed = 0;
        $costSpent = 0;
        $completedAgents = 0;
        $failedAgents = 0;
        $success = true;
        $errorMessage = null;
        $stopReason = null;

        try {
            foreach ($log->agent_pipeline as $agentName) {
                // Check budget limits before executing each agent
                if ($this->isBudgetExceeded($log, $tokensUsed, $costSpent, $startTime)) {
                    $success = false;
                    $stopReason = 'budget_exceeded';
                    break;
                }

                try {
                    // Execute agent (simulated for testing)
                    $agentResult = $this->executeAgent($agentName, $sharedContext);

                    // Update metrics
                    $tokensUsed += $agentResult['tokens_used'] ?? 50; // Mock: 50 tokens per agent
                    $costSpent += $agentResult['cost'] ?? 0.001; // Mock: $0.001 per agent
                    $completedAgents++;

                    // Update shared context
                    $sharedContext = array_merge($sharedContext, $agentResult['output'] ?? []);

                    // Log execution
                    $executionHistory[] = [
                        'agent' => $agentName,
                        'status' => 'completed',
                        'tokens_used' => $agentResult['tokens_used'] ?? 50,
                        'cost' => $agentResult['cost'] ?? 0.001,
                        'output' => $agentResult['output'] ?? [],
                    ];
                } catch (Exception $e) {
                    $failedAgents++;
                    $success = false;
                    $errorMessage = $e->getMessage();

                    $executionHistory[] = [
                        'agent' => $agentName,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    break; // Stop on first error
                }
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $status = $success ? 'completed' : 'failed';

            // Update log
            $log->update([
                'status' => $status,
                'shared_context' => $sharedContext,
                'execution_history' => $executionHistory,
                'tokens_used' => $tokensUsed,
                'cost_spent' => $costSpent,
                'duration_ms' => $durationMs,
                'completed_agents' => $completedAgents,
                'failed_agents' => $failedAgents,
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);

            return [
                'success' => $success,
                'status' => $status,
                'orchestration_id' => $orchestrationId,
                'completed_agents' => $completedAgents,
                'failed_agents' => $failedAgents,
                'tokens_used' => $tokensUsed,
                'cost_spent' => $costSpent,
                'duration_ms' => $durationMs,
                'shared_context' => $sharedContext,
                'execution_history' => $executionHistory,
                'error_message' => $errorMessage,
                'stop_reason' => $stopReason,
            ];
        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute a single agent (simulated for testing).
     *
     * @param  string  $agentName  Agent name
     * @param  array  $context  Shared context
     * @return array Agent result
     */
    protected function executeAgent(string $agentName, array $context): array
    {
        // Mock implementation for testing
        // In production, this would dispatch to actual agent implementations
        return [
            'output' => [
                'agent' => $agentName,
                'result' => "Result from {$agentName}",
                'timestamp' => now()->toIso8601String(),
            ],
            'tokens_used' => 50,
            'cost' => 0.001,
        ];
    }

    /**
     * Check if budget limits are exceeded.
     *
     * @param  OrchestrationLog  $log  Orchestration log
     * @param  int  $tokensUsed  Tokens used so far
     * @param  float  $costSpent  Cost spent so far
     * @param  float  $startTime  Start time
     * @return bool True if budget exceeded
     */
    protected function isBudgetExceeded(
        OrchestrationLog $log,
        int $tokensUsed,
        float $costSpent,
        float $startTime
    ): bool {
        if ($log->token_budget && $tokensUsed >= $log->token_budget) {
            return true;
        }

        if ($log->cost_budget && $costSpent >= $log->cost_budget) {
            return true;
        }

        if ($log->time_budget_ms) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            if ($elapsedMs >= $log->time_budget_ms) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute agents in parallel.
     *
     * @param  string  $orchestrationId  Orchestration ID
     * @param  int  $timeout  Timeout in milliseconds
     * @return array Execution result
     */
    public function executeParallel(string $orchestrationId, int $timeout = 30000): array
    {
        $log = OrchestrationLog::where('orchestration_id', $orchestrationId)->firstOrFail();

        $log->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);
        $sharedContext = $log->shared_context ?? [];
        $executionHistory = [];
        $tokensUsed = 0;
        $costSpent = 0;
        $completedAgents = 0;
        $failedAgents = 0;
        $success = true;
        $errorMessage = null;
        $stopReason = null;

        try {
            // Flatten pipeline if it contains parallel groups
            $agents = [];
            foreach ($log->agent_pipeline as $item) {
                if (is_array($item)) {
                    // Parallel group - execute all in parallel
                    $agents = array_merge($agents, $item);
                } else {
                    $agents[] = $item;
                }
            }

            // Execute all agents in parallel (simulated with concurrent processing)
            $results = $this->executeAgentsInParallel($agents, $sharedContext, $timeout);

            // Process results
            foreach ($results as $result) {
                if ($result['success']) {
                    $completedAgents++;
                    $tokensUsed += $result['tokens_used'];
                    $costSpent += $result['cost'];
                    $sharedContext = array_merge($sharedContext, $result['output'] ?? []);

                    $executionHistory[] = [
                        'agent' => $result['agent'],
                        'status' => 'completed',
                        'tokens_used' => $result['tokens_used'],
                        'cost' => $result['cost'],
                        'output' => $result['output'],
                    ];
                } else {
                    $failedAgents++;
                    $success = false;
                    if (! $errorMessage) {
                        $errorMessage = $result['error'];
                    }

                    $executionHistory[] = [
                        'agent' => $result['agent'],
                        'status' => 'failed',
                        'error' => $result['error'],
                    ];
                }
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Check timeout
            if ($durationMs >= $timeout) {
                $success = false;
                $stopReason = 'timeout';
            }

            $status = $success ? 'completed' : 'failed';

            // Update log
            $log->update([
                'status' => $status,
                'shared_context' => $sharedContext,
                'execution_history' => $executionHistory,
                'tokens_used' => $tokensUsed,
                'cost_spent' => $costSpent,
                'duration_ms' => $durationMs,
                'completed_agents' => $completedAgents,
                'failed_agents' => $failedAgents,
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);

            return [
                'success' => $success,
                'status' => $status,
                'orchestration_id' => $orchestrationId,
                'completed_agents' => $completedAgents,
                'failed_agents' => $failedAgents,
                'tokens_used' => $tokensUsed,
                'cost_spent' => $costSpent,
                'duration_ms' => $durationMs,
                'shared_context' => $sharedContext,
                'execution_history' => $executionHistory,
                'error_message' => $errorMessage,
                'stop_reason' => $stopReason,
            ];
        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute multiple agents in parallel.
     *
     * @param  array  $agents  Agent names
     * @param  array  $context  Shared context
     * @param  int  $timeout  Timeout in milliseconds
     * @return array Array of results
     */
    protected function executeAgentsInParallel(array $agents, array $context, int $timeout): array
    {
        // Simulate parallel execution
        // In production, this would use Laravel's concurrent processing or async jobs
        $results = [];

        // Execute all agents "in parallel" (simulated with fast sequential)
        foreach ($agents as $agentName) {
            try {
                $result = $this->executeAgent($agentName, $context);
                $results[] = [
                    'success' => true,
                    'agent' => $agentName,
                    'tokens_used' => $result['tokens_used'] ?? 50,
                    'cost' => $result['cost'] ?? 0.001,
                    'output' => $result['output'] ?? [],
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'agent' => $agentName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Request additional research from ResearchSpecialistAgent.
     *
     * @param  string  $orchestrationId  Orchestration ID
     * @param  string  $topic  Research topic
     * @param  array  $context  Additional context for research
     * @return array Feedback request result
     */
    public function requestAdditionalResearch(string $orchestrationId, string $topic, array $context = []): array
    {
        // Validate orchestration exists
        try {
            $log = OrchestrationLog::where('orchestration_id', $orchestrationId)->first();
        } catch (Exception $e) {
            // Handle invalid UUID or database errors
            return [
                'feedback_sent' => false,
                'reason' => 'Orchestration not found',
            ];
        }

        if (! $log) {
            return [
                'feedback_sent' => false,
                'reason' => 'Orchestration not found',
            ];
        }

        // Determine target agent (ResearchSpecialistAgent for additional research)
        $targetAgent = 'ResearchSpecialistAgent';

        // Create feedback request
        $feedbackRequest = [
            'topic' => $topic,
            'context' => $context,
            'target_agent' => $targetAgent,
            'requested_at' => now()->toIso8601String(),
        ];

        // Append to feedback_requests array
        $feedbackRequests = $log->feedback_requests ?? [];
        $feedbackRequests[] = $feedbackRequest;

        $log->update([
            'feedback_requests' => $feedbackRequests,
        ]);

        return [
            'feedback_sent' => true,
            'target_agent' => $targetAgent,
            'topic' => $topic,
            'context' => $context,
        ];
    }

    /**
     * Execute a feedback iteration.
     *
     * @param  string  $orchestrationId  Orchestration ID
     * @return array Feedback iteration result
     */
    public function executeFeedbackIteration(string $orchestrationId): array
    {
        $log = OrchestrationLog::where('orchestration_id', $orchestrationId)->firstOrFail();

        // Check iteration limit (max 2 iterations)
        $currentIterationCount = $log->feedback_iteration_count ?? 0;

        if ($currentIterationCount >= 2) {
            return [
                'feedback_processed' => false,
                'reason' => 'Maximum feedback iteration limit reached (2 iterations)',
                'iteration' => $currentIterationCount,
            ];
        }

        // Get the latest feedback request
        $feedbackRequests = $log->feedback_requests ?? [];

        if (empty($feedbackRequests)) {
            return [
                'feedback_processed' => false,
                'reason' => 'No feedback requests found',
                'iteration' => $currentIterationCount,
            ];
        }

        // Get the last feedback request
        $latestFeedback = end($feedbackRequests);
        $targetAgent = $latestFeedback['target_agent'] ?? 'ResearchSpecialistAgent';

        // Execute the target agent with feedback context
        $sharedContext = $log->shared_context ?? [];
        $feedbackContext = array_merge($sharedContext, [
            'feedback_topic' => $latestFeedback['topic'],
            'feedback_context' => $latestFeedback['context'],
            'is_feedback_iteration' => true,
        ]);

        try {
            $agentResult = $this->executeAgent($targetAgent, $feedbackContext);

            // Update shared context with new results
            $updatedContext = array_merge($sharedContext, $agentResult['output'] ?? []);

            // Mock improvement: Add researched items
            if (! isset($updatedContext['researched_items'])) {
                $updatedContext['researched_items'] = [];
            }
            $updatedContext['researched_items'][] = 'feedback_research_'.($currentIterationCount + 1);

            // Increment iteration count
            $newIterationCount = $currentIterationCount + 1;

            // Update log
            $log->update([
                'shared_context' => $updatedContext,
                'feedback_iteration_count' => $newIterationCount,
            ]);

            return [
                'feedback_processed' => true,
                'iteration' => $newIterationCount,
                'feedback_topic' => $latestFeedback['topic'],
                'feedback_context' => $latestFeedback['context'],
                'requested_at' => $latestFeedback['requested_at'],
                'processed_at' => now()->toIso8601String(),
                'shared_context' => $updatedContext,
            ];
        } catch (Exception $e) {
            return [
                'feedback_processed' => false,
                'reason' => 'Agent execution failed: '.$e->getMessage(),
                'iteration' => $currentIterationCount,
            ];
        }
    }

    /**
     * Get orchestration log.
     *
     * @param  string  $orchestrationId  Orchestration ID
     * @return array Orchestration log
     */
    public function getOrchestrationLog(string $orchestrationId): array
    {
        $log = OrchestrationLog::where('orchestration_id', $orchestrationId)->firstOrFail();

        return $log->toArray();
    }
}
