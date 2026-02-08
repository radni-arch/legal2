<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Explainability\ReasoningTraceService;
use Illuminate\Http\JsonResponse;

/**
 * Trace Viewer API Controller
 *
 * Sprint 2.4: Basic Reasoning Trace Integration
 *
 * Provides API endpoints for retrieving AI reasoning traces
 * for transparency and debugging purposes.
 */
class TraceViewerController extends Controller
{
    public function __construct(
        protected ReasoningTraceService $traceService
    ) {}

    /**
     * Get full trace tree for a given trace ID
     *
     * GET /api/explainability/trace/{id}
     *
     * Returns the complete hierarchical trace tree with all nested
     * child traces for visualization and analysis.
     *
     * @param  string  $traceId  The root trace ID (UUID)
     */
    public function show(string $traceId): JsonResponse
    {
        try {
            // Build the trace tree
            $traceTree = $this->traceService->buildTraceTree($traceId);

            if ($traceTree === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Trace not found',
                ], 404);
            }

            // Count total traces in tree
            $traceCount = $this->countTracesInTree($traceTree);

            return response()->json([
                'success' => true,
                'trace_id' => $traceId,
                'trace_tree' => $traceTree,
                'trace_count' => $traceCount,
            ]);
        } catch (\Exception $e) {
            // Handle database errors or malformed IDs gracefully
            return response()->json([
                'success' => false,
                'error' => 'Trace not found',
            ], 404);
        }
    }

    /**
     * Count total number of traces in a tree (including root)
     */
    protected function countTracesInTree(array $tree): int
    {
        $count = 1; // Count root

        if (! empty($tree['children'])) {
            foreach ($tree['children'] as $child) {
                $count += $this->countTracesInTree($child);
            }
        }

        return $count;
    }
}
