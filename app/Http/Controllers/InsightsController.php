<?php

namespace App\Http\Controllers;

use App\Http\Requests\Insights\GetInsightsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ErrorCode;
use App\Services\AgentToolbox;
use Illuminate\Support\Facades\Log;

/**
 * Controller for querying agent insights from vector memory.
 *
 * Provides API endpoints to retrieve stored insights from past research runs,
 * enabling memory reuse across autonomous agent executions.
 */
class InsightsController extends Controller
{
    public function __construct(
        protected AgentToolbox $toolbox
    ) {}

    /**
     * Retrieve recent insights for a given agent.
     *
     * GET /api/insights/{agentName}
     *
     * Query parameters:
     * - objective: Filter by research objective (partial match)
     * - namespace: Filter by memory namespace (default: research_insights)
     * - source: Filter by source reference
     * - limit: Maximum number of insights to return (default: 10, max: 100)
     * - days: Only include insights from last N days (default: 30)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(GetInsightsRequest $request, string $agentName)
    {
        $validated = $request->validated();

        // Validate agent name format
        if (! preg_match('/^[a-z0-9_]+$/', $agentName)) {
            return ApiResponse::error(
                ErrorCode::VALIDATION_ERROR,
                'Invalid agent name format',
                ['agent_name' => 'Agent name must contain only lowercase letters, numbers, and underscores'],
                400
            );
        }

        try {
            // Build filters array
            $filters = [
                'namespace' => $validated['namespace'] ?? 'research_insights',
                'limit' => min($validated['limit'] ?? 10, 100),
                'days' => $validated['days'] ?? 30,
            ];

            // Add optional filters
            if (isset($validated['objective'])) {
                $filters['objective'] = $validated['objective'];
            }

            if (isset($validated['source'])) {
                $filters['source'] = $validated['source'];
            }

            // Retrieve insights from toolbox
            $result = $this->toolbox->getRecentInsights($agentName, $filters);

            // Check if the operation was successful
            if (! ($result['success'] ?? false)) {
                Log::warning('Failed to retrieve insights', [
                    'agent_name' => $agentName,
                    'filters' => $filters,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                return ApiResponse::error(
                    ErrorCode::AGENT_EXECUTION_FAILED,
                    'Failed to retrieve insights',
                    ['error' => $result['error'] ?? 'Unknown error'],
                    500
                );
            }

            // Return successful response
            return ApiResponse::success([
                'agent_name' => $agentName,
                'filters' => $filters,
                'insights' => $result['insights'] ?? [],
                'count' => $result['count'] ?? 0,
                'message' => $result['message'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Exception while retrieving insights', [
                'agent_name' => $agentName,
                'filters' => $filters ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                ErrorCode::AGENT_EXECUTION_FAILED,
                'An error occurred while retrieving insights',
                ['message' => $e->getMessage()],
                500
            );
        }
    }
}
