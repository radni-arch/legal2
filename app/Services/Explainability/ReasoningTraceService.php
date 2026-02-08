<?php

namespace App\Services\Explainability;

use App\Models\AiReasoningTrace;

/**
 * Reasoning Trace Service
 *
 * Provides explainability for AI agent decisions by logging reasoning traces.
 * Supports hierarchical trace trees for complex multi-step reasoning.
 *
 * Sprint 1.2: ReasoningTraceService Foundation
 *
 * Features:
 * - Start and end reasoning traces
 * - Support nested traces (unlimited depth)
 * - Retrieve full trace trees with recursive queries
 * - Build hierarchical tree structures
 * - Performance optimized for large trace sets
 */
class ReasoningTraceService
{
    /**
     * Start a new reasoning trace.
     *
     * Creates a new trace record to track an AI reasoning step.
     * Supports nesting by providing a parent trace ID.
     *
     * @param  string  $operation  The operation being performed
     * @param  array|null  $input  Input data for the operation
     * @param  string|null  $parentTraceId  Optional parent trace ID for nesting
     * @param  string|null  $agentType  Type of agent performing the operation
     * @param  string|null  $stepType  Type of reasoning step
     * @return string The trace ID (UUID) of the created trace
     */
    public function startTrace(
        string $operation,
        ?array $input = null,
        ?string $parentTraceId = null,
        ?string $agentType = null,
        ?string $stepType = null
    ): string {
        $trace = AiReasoningTrace::create([
            'parent_trace_id' => $parentTraceId,
            'agent_type' => $agentType,
            'step_type' => $stepType,
            'operation' => $operation,
            'input_data' => $input,
        ]);

        return $trace->trace_id;
    }

    /**
     * End a reasoning trace with output and analysis.
     *
     * Updates the trace with output data, reasoning explanation,
     * confidence score, and performance metrics.
     *
     * @param  string  $traceId  The trace ID to update
     * @param  array|null  $output  Output data from the operation
     * @param  string|null  $reasoning  Explanation of the reasoning process
     * @param  float|null  $confidence  Confidence score (0-1)
     * @param  int|null  $tokensUsed  Number of tokens used (for LLM calls)
     * @param  int|null  $durationMs  Duration in milliseconds
     * @return bool True if successful, false if trace not found
     */
    public function endTrace(
        string $traceId,
        ?array $output = null,
        ?string $reasoning = null,
        ?float $confidence = null,
        ?int $tokensUsed = null,
        ?int $durationMs = null
    ): bool {
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();

        if (! $trace) {
            return false;
        }

        $trace->update([
            'output_data' => $output,
            'reasoning' => $reasoning,
            'confidence' => $confidence,
            'tokens_used' => $tokensUsed,
            'duration_ms' => $durationMs,
        ]);

        return true;
    }

    /**
     * Get the full trace tree starting from a root trace.
     *
     * Uses recursive CTE query to efficiently retrieve all traces
     * in the tree, ordered chronologically.
     *
     * @param  string  $rootTraceId  The root trace ID
     * @return array Array of traces in chronological order
     */
    public function getFullTrace(string $rootTraceId): array
    {
        // Use the model's static method for recursive CTE query
        $results = AiReasoningTrace::getTraceTree($rootTraceId);

        // Convert to array format
        return $results->toArray();
    }

    /**
     * Build a hierarchical tree structure from a root trace.
     *
     * Converts flat trace list into nested tree structure
     * where each trace contains its children.
     *
     * @param  string  $rootTraceId  The root trace ID
     * @return array|null Tree structure with nested children, or null if not found
     */
    public function buildTraceTree(string $rootTraceId): ?array
    {
        // Get all traces in the tree
        $traces = $this->getFullTrace($rootTraceId);

        if (empty($traces)) {
            return null;
        }

        // Convert to indexed array for faster lookups
        $tracesById = [];
        foreach ($traces as $trace) {
            $tracesById[$trace['trace_id']] = $trace;
            $tracesById[$trace['trace_id']]['children'] = [];
        }

        // Build tree structure
        $root = null;
        foreach ($tracesById as $traceId => $trace) {
            if ($trace['parent_trace_id']) {
                // Add as child to parent
                if (isset($tracesById[$trace['parent_trace_id']])) {
                    $tracesById[$trace['parent_trace_id']]['children'][] = &$tracesById[$traceId];
                }
            } else {
                // This is the root
                $root = &$tracesById[$traceId];
            }
        }

        return $root;
    }
}
