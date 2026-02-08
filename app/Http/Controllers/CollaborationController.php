<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collaboration\CollaborateRequest;
use App\Http\Requests\Collaboration\RecentCollaborationsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Collaboration\LegalTeamOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CollaborationController extends Controller
{
    public function __construct(
        protected LegalTeamOrchestrator $orchestrator
    ) {}

    /**
     * Solve a legal problem using multi-agent collaboration
     *
     * POST /api/collaboration/solve
     *
     * Body:
     * {
     *   "problem": "Legal problem statement",
     *   "problem_type": "employment|contract|property|family|criminal|general" (optional),
     *   "context": {...} (optional),
     *   "agents": ["research_specialist", "precedent_analyst", ...] (optional, defaults to all),
     *   "execution_mode": "sequential|parallel" (optional, default: sequential)
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "collaboration_id": "uuid",
     *   "session_id": "collab_xxxx",
     *   "problem_statement": "...",
     *   "execution_plan": {...},
     *   "agent_outputs": {...},
     *   "final_result": {
     *     "synthesis": "Executive summary...",
     *     "research_findings": {...},
     *     "precedent_analysis": {...},
     *     "legal_strategy": {...},
     *     "risk_assessment": {...}
     *   },
     *   "metadata": {
     *     "tokens_used": 12345,
     *     "cost_spent": 1.85,
     *     "duration_seconds": 45
     *   }
     * }
     */
    public function solve(CollaborateRequest $request): JsonResponse
    {
        $requestId = 'collab_req_'.Str::uuid();

        try {
            $validated = $request->validated();

            Log::info('CollaborationController - Solve request', [
                'request_id' => $requestId,
                'problem_length' => strlen($validated['problem']),
                'problem_type' => $validated['problem_type'] ?? 'auto',
            ]);

            $result = $this->orchestrator->solveProblem(
                $validated['problem'],
                [
                    'problem_type' => $validated['problem_type'] ?? null,
                    'context' => $validated['context'] ?? [],
                    'agents' => $validated['agents'] ?? null,
                    'execution_mode' => $validated['execution_mode'] ?? 'sequential',
                ]
            );

            if ($result['success']) {
                Log::info('CollaborationController - Solve completed', [
                    'request_id' => $requestId,
                    'collaboration_id' => $result['collaboration_id'],
                    'duration_seconds' => $result['metadata']['duration_seconds'] ?? null,
                ]);

                return ApiResponse::success($result);
            } else {
                Log::error('CollaborationController - Solve failed', [
                    'request_id' => $requestId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                return response()->json($result, 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('CollaborationController - Validation error', [
                'request_id' => $requestId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'validation_errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('CollaborationController - Unexpected error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get collaboration by ID
     *
     * GET /api/collaboration/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $collaboration = $this->orchestrator->getCollaboration($id);

            if (! $collaboration) {
                return response()->json([
                    'success' => false,
                    'error' => 'Collaboration not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'collaboration' => $collaboration,
            ]);

        } catch (\Exception $e) {
            Log::error('CollaborationController - Show error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent collaborations
     *
     * GET /api/collaboration/recent?limit=10
     */
    public function recent(RecentCollaborationsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $limit = $validated['limit'] ?? 10;

            $collaborations = $this->orchestrator->getRecentCollaborations($limit);

            return response()->json([
                'success' => true,
                'collaborations' => $collaborations,
                'count' => count($collaborations),
            ]);

        } catch (\Exception $e) {
            Log::error('CollaborationController - Recent error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get collaboration statistics
     *
     * GET /api/collaboration/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_collaborations' => \App\Models\AgentCollaboration::count(),
                'completed' => \App\Models\AgentCollaboration::completed()->count(),
                'in_progress' => \App\Models\AgentCollaboration::inProgress()->count(),
                'failed' => \App\Models\AgentCollaboration::failed()->count(),
                'total_tokens_used' => \App\Models\AgentCollaboration::sum('tokens_used'),
                'total_cost_spent' => \App\Models\AgentCollaboration::sum('cost_spent'),
                'avg_duration_seconds' => \App\Models\AgentCollaboration::completed()
                    ->avg('duration_seconds'),
                'recent_7_days' => \App\Models\AgentCollaboration::recent(7)->count(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('CollaborationController - Stats error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
