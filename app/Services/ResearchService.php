<?php

namespace App\Services;

use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

/**
 * Research Service
 *
 * Wrapper for ResearchOrchestrator that provides backward compatibility
 * with the deprecated AutonomousResearchAgent pattern.
 *
 * This service:
 * - Uses ResearchOrchestrator internally for research execution
 * - Persists results to AgentRun model for backward compatibility
 * - Dispatches events for real-time updates
 * - Provides both sync and async execution
 *
 * @see \App\Services\ResearchOrchestrator
 */
class ResearchService
{
    protected const AGENT_NAME = 'research_service';

    public function __construct(
        protected ResearchOrchestrator $orchestrator
    ) {}

    /**
     * Execute research and persist results to AgentRun model.
     *
     * This provides backward compatibility with the deprecated
     * AutonomousResearchAgent while using ResearchOrchestrator internally.
     *
     * @param  string  $objective  The research objective
     * @param  array  $options  Options:
     *                          - max_iterations: Maximum iterations (default: 5)
     *                          - quality_threshold: Quality threshold (default: 85)
     *                          - token_budget: Optional token budget
     *                          - time_limit_seconds: Optional time limit
     * @return AgentRun The completed run record
     */
    public function research(string $objective, array $options = []): AgentRun
    {
        $startTime = microtime(true);

        // Create AgentRun record
        $run = AgentRun::create([
            'agent_name' => self::AGENT_NAME,
            'objective' => $objective,
            'status' => 'running',
            'context' => $options,
            'max_iterations' => $options['max_iterations'] ?? 5,
            'threshold' => $options['quality_threshold'] ?? 85,
            'token_budget' => $options['token_budget'] ?? null,
            'time_limit_seconds' => $options['time_limit_seconds'] ?? null,
            'started_at' => now(),
        ]);

        Log::info('ResearchService: Starting research', [
            'run_id' => $run->id,
            'objective' => $objective,
        ]);

        try {
            // Execute research via orchestrator
            $result = $this->orchestrator->research($objective, [
                'limits' => [
                    'max_iterations' => $options['max_iterations'] ?? 5,
                    'quality_threshold' => $options['quality_threshold'] ?? 85,
                    'token_budget' => $options['token_budget'] ?? null,
                    'time_budget' => $options['time_limit_seconds'] ?? null,
                ],
            ]);

            $elapsedSeconds = microtime(true) - $startTime;

            // Update run with results
            $run->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'score' => $result['quality_score'],
                'current_iteration' => $result['iterations'],
                'tokens_used' => $result['total_tokens'],
                'elapsed_seconds' => (int) round($elapsedSeconds),
                'final_output' => $result['answer'],
                'iterations' => $result['search_results'], // Store search results in iterations array
                'completed_at' => now(),
            ]);

            // Fire appropriate event
            if ($result['success']) {
                event(new ResearchCompleted($run));
            } else {
                event(new ResearchFailed($run, 'Research did not meet quality threshold'));
            }

            Log::info('ResearchService: Research completed', [
                'run_id' => $run->id,
                'status' => $run->status,
                'score' => $run->score,
            ]);

        } catch (\Exception $e) {
            $elapsedSeconds = microtime(true) - $startTime;

            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'elapsed_seconds' => (int) round($elapsedSeconds),
                'completed_at' => now(),
            ]);

            event(new ResearchFailed($run, $e->getMessage()));

            Log::error('ResearchService: Research failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    /**
     * Queue research for async execution.
     *
     * @param  string  $objective  The research objective
     * @param  array  $options  Research options
     * @return AgentRun The pending run record
     */
    public function researchAsync(string $objective, array $options = []): AgentRun
    {
        // Create pending run
        $run = AgentRun::create([
            'agent_name' => self::AGENT_NAME,
            'objective' => $objective,
            'status' => 'pending',
            'context' => $options,
            'max_iterations' => $options['max_iterations'] ?? 5,
            'threshold' => $options['quality_threshold'] ?? 85,
            'token_budget' => $options['token_budget'] ?? null,
            'time_limit_seconds' => $options['time_limit_seconds'] ?? null,
        ]);

        // Dispatch job
        \App\Jobs\ExecuteResearchJob::dispatch($run->id);

        return $run;
    }

    /**
     * Execute research on an existing AgentRun record.
     *
     * Used by ExecuteResearchJob for async execution.
     * Does not create a new AgentRun - operates on the provided one.
     *
     * @param  AgentRun  $run  The existing run to execute
     * @return AgentRun The completed run record
     */
    public function executeRun(AgentRun $run): AgentRun
    {
        $startTime = microtime(true);

        // Mark as running
        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        Log::info('ResearchService: Executing existing run', [
            'run_id' => $run->id,
            'objective' => $run->objective,
        ]);

        try {
            // Build options from run record
            $options = [
                'limits' => [
                    'max_iterations' => $run->max_iterations ?? 5,
                    'quality_threshold' => $run->threshold ?? 85,
                    'token_budget' => $run->token_budget,
                    'time_budget' => $run->time_limit_seconds,
                ],
            ];

            // Execute research via orchestrator
            $result = $this->orchestrator->research($run->objective, $options);

            $elapsedSeconds = microtime(true) - $startTime;

            // Determine success based on quality threshold
            $threshold = $run->threshold ?? 85;
            $success = $result['success'] && $result['quality_score'] >= $threshold;

            // Update run with results
            $run->update([
                'status' => $success ? 'completed' : 'failed',
                'score' => $result['quality_score'],
                'current_iteration' => $result['iterations'],
                'tokens_used' => $result['total_tokens'],
                'elapsed_seconds' => (int) round($elapsedSeconds),
                'final_output' => $result['answer'],
                'iterations' => $result['search_results'],
                'completed_at' => now(),
                'error' => $success ? null : 'Did not meet quality threshold of '.$threshold,
            ]);

            // Fire appropriate event
            if ($success) {
                event(new ResearchCompleted($run));
            } else {
                event(new ResearchFailed($run, 'Research did not meet quality threshold'));
            }

            Log::info('ResearchService: Run completed', [
                'run_id' => $run->id,
                'status' => $run->status,
                'score' => $run->score,
            ]);

        } catch (\Exception $e) {
            $elapsedSeconds = microtime(true) - $startTime;

            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'elapsed_seconds' => (int) round($elapsedSeconds),
                'completed_at' => now(),
            ]);

            event(new ResearchFailed($run, $e->getMessage()));

            Log::error('ResearchService: Run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }
}
