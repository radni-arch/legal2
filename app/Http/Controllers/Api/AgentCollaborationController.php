<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartCollaborationRequest;
use App\Services\Agents\OrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Agent Collaboration API Controller
 *
 * Sprint 3.7: Orchestrator API Endpoints
 *
 * Provides REST API endpoints for starting and monitoring multi-agent collaborations.
 */
class AgentCollaborationController extends Controller
{
    public function __construct(
        protected OrchestratorService $orchestrator
    ) {}

    /**
     * Start a new agent collaboration.
     *
     * POST /api/agents/collaborate
     */
    public function start(StartCollaborationRequest $request): JsonResponse
    {
        try {
            $orchestrationId = $this->orchestrator->orchestrate(
                pipeline: $request->input('pipeline'),
                taskDescription: $request->input('task_description'),
                initialContext: $request->input('initial_context', []),
                budgets: $request->input('budgets', [])
            );

            Log::info('Agent collaboration started', [
                'orchestration_id' => $orchestrationId,
                'pipeline' => $request->input('pipeline'),
                'task' => $request->input('task_description'),
            ]);

            return response()->json([
                'success' => true,
                'orchestration_id' => $orchestrationId,
                'status' => 'pending',
                'message' => 'Collaboration started successfully',
                'metadata' => [
                    'total_agents' => count($request->input('pipeline')),
                    'budgets' => $request->input('budgets', []),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to start collaboration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start collaboration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get collaboration status.
     *
     * GET /api/agents/collaborate/{id}/status
     *
     * @param  string  $id  Orchestration ID
     */
    public function status(string $id): JsonResponse
    {
        try {
            $log = $this->orchestrator->getOrchestrationLog($id);

            return response()->json([
                'success' => true,
                'orchestration_id' => $id,
                'status' => $log['status'],
                'progress' => [
                    'total_agents' => $log['total_agents'],
                    'completed_agents' => $log['completed_agents'] ?? 0,
                    'failed_agents' => $log['failed_agents'] ?? 0,
                ],
                'metrics' => [
                    'tokens_used' => $log['tokens_used'] ?? 0,
                    'cost_spent' => $log['cost_spent'] ?? 0,
                    'duration_ms' => $log['duration_ms'] ?? null,
                ],
                'started_at' => $log['started_at'] ?? null,
                'completed_at' => $log['completed_at'] ?? null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Orchestration not found',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle invalid UUID format or other database errors
            return response()->json([
                'success' => false,
                'error' => 'Orchestration not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get collaboration status', [
                'orchestration_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get collaboration result.
     *
     * GET /api/agents/collaborate/{id}/result
     *
     * @param  string  $id  Orchestration ID
     */
    public function result(string $id): JsonResponse
    {
        try {
            $log = $this->orchestrator->getOrchestrationLog($id);

            // Check if collaboration is completed
            if ($log['status'] !== 'completed' && $log['status'] !== 'failed') {
                return response()->json([
                    'success' => false,
                    'error' => 'Collaboration not yet completed',
                    'current_status' => $log['status'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'orchestration_id' => $id,
                'status' => $log['status'],
                'result' => [
                    'shared_context' => $log['shared_context'] ?? [],
                    'execution_history' => $log['execution_history'] ?? [],
                    'tokens_used' => $log['tokens_used'] ?? 0,
                    'cost_spent' => $log['cost_spent'] ?? 0,
                    'duration_ms' => $log['duration_ms'] ?? 0,
                    'error_message' => $log['error_message'] ?? null,
                ],
                'completed_at' => $log['completed_at'],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Orchestration not found',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle invalid UUID format or other database errors
            return response()->json([
                'success' => false,
                'error' => 'Orchestration not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get collaboration result', [
                'orchestration_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve result',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
