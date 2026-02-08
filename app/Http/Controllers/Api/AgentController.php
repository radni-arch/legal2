<?php

namespace App\Http\Controllers\Api;

use App\Agents\AutonomousResearchAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StartAgentResearchRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ErrorCode;
use App\Jobs\ExecuteAgentResearch;
use App\Models\AgentRun;
use App\Services\AgentEvaluationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Consolidated Agent API Controller
 *
 * Handles all agent-related endpoints:
 * - Research agent operations (start, get, list, delete)
 * - Agent evaluation and reporting
 *
 * Sprint 12.5 - Worker C: Controller Migration
 */
class AgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AutonomousResearchAgent $agent,
        protected AgentEvaluationService $evaluator
    ) {}

    /**
     * Start a new autonomous research run
     *
     * POST /api/agent/research/start
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function startResearch(StartAgentResearchRequest $request)
    {
        $validated = $request->validated();

        $context = [
            'topics' => $validated['topics'] ?? [],
            'jurisdiction' => $validated['jurisdiction'] ?? 'Croatia',
            'requested_by' => $request->user()?->id ?? 'system',
            'ip' => $request->ip(),
        ];

        $constraints = [
            'max_iterations' => $validated['max_iterations'] ?? config('agent.defaults.max_iterations', 10),
            'threshold' => $validated['threshold'] ?? config('agent.defaults.threshold', 0.75),
            'token_budget' => $validated['token_budget'] ?? config('agent.defaults.token_budget'),
            'cost_budget' => $validated['cost_budget'] ?? config('agent.defaults.cost_budget'),
            'time_limit_seconds' => $validated['time_limit_seconds'] ?? config('agent.defaults.time_limit_seconds'),
        ];

        try {
            $run = $this->agent->startRun($validated['objective'], $context, $constraints);

            // Determine execution mode
            $async = $validated['async'] ?? config('agent.performance.async_execution', false);

            if ($async) {
                // Dispatch to queue for background execution
                ExecuteAgentResearch::dispatch($run);

                return ApiResponse::success([
                    'async' => true,
                    'run' => [
                        'id' => $run->id,
                        'objective' => $run->objective,
                        'status' => $run->status,
                        'message' => 'Research run started in background. Check status using GET /api/agent/research/'.$run->id,
                    ],
                ]);
            } else {
                // Execute synchronously
                $run = $this->agent->executeRun($run);

                return ApiResponse::success([
                    'async' => false,
                    'run' => [
                        'id' => $run->id,
                        'objective' => $run->objective,
                        'status' => $run->status,
                        'score' => $run->score,
                        'iterations' => $run->current_iteration,
                        'elapsed_seconds' => $run->elapsed_seconds,
                        'final_output' => $run->final_output,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to start research run', [
                'objective' => $validated['objective'],
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                ErrorCode::AGENT_EXECUTION_FAILED,
                'Failed to start research run',
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get status of a research run
     *
     * GET /api/agent/research/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResearch(int $id)
    {
        $run = AgentRun::find($id);

        if (! $run) {
            return ApiResponse::notFound('Research run not found');
        }

        $this->authorize('view', $run);

        return ApiResponse::success([
            'run' => [
                'id' => $run->id,
                'agent_name' => $run->agent_name,
                'objective' => $run->objective,
                'topics' => $run->topics,
                'status' => $run->status,
                'score' => $run->score,
                'threshold' => $run->threshold,
                'current_iteration' => $run->current_iteration,
                'max_iterations' => $run->max_iterations,
                'tokens_used' => $run->tokens_used,
                'cost_spent' => $run->cost_spent,
                'elapsed_seconds' => $run->elapsed_seconds,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'final_output' => $run->final_output,
                'error' => $run->error,
                'iterations' => $run->iterations,
            ],
        ]);
    }

    /**
     * List all research runs
     *
     * GET /api/agent/research
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listResearch(Request $request)
    {
        $this->authorize('viewAny', AgentRun::class);

        $query = AgentRun::query()->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('agent_name')) {
            $query->where('agent_name', $request->input('agent_name'));
        }

        $limit = min((int) $request->input('limit', 20), 100);
        $runs = $query->limit($limit)->get();

        return ApiResponse::success([
            'runs' => $runs->map(fn ($run) => [
                'id' => $run->id,
                'agent_name' => $run->agent_name,
                'objective' => $run->objective,
                'status' => $run->status,
                'score' => $run->score,
                'iterations' => $run->current_iteration,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'elapsed_seconds' => $run->elapsed_seconds,
            ]),
            'count' => $runs->count(),
        ]);
    }

    /**
     * Get evaluation report for a run
     *
     * GET /api/agent/research/{id}/evaluation
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEvaluation(int $id)
    {
        $run = AgentRun::find($id);

        if (! $run) {
            return response()->json([
                'success' => false,
                'error' => 'Run not found',
            ], 404);
        }

        if ($run->status !== 'completed') {
            return response()->json([
                'success' => false,
                'error' => 'Run is not completed yet',
            ], 400);
        }

        try {
            $report = $this->evaluator->generateReport($id);

            return response()->json([
                'success' => true,
                'run_id' => $id,
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate evaluation report', [
                'run_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a research run
     *
     * DELETE /api/agent/research/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteResearch(int $id)
    {
        $run = AgentRun::find($id);

        if (! $run) {
            return response()->json([
                'success' => false,
                'error' => 'Run not found',
            ], 404);
        }

        $this->authorize('delete', $run);

        try {
            $run->delete();

            return response()->json([
                'success' => true,
                'message' => 'Run deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete run', [
                'run_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
