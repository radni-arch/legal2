<?php

namespace App\Mcp\Tools;

use App\Services\DecisionSearchService;
use Illuminate\Support\Facades\Validator;

/**
 * MCP Tool: Comprehensive citation analysis for a decision
 *
 * Provides complete citation analysis including:
 * - Extracted citations
 * - Citation statistics
 * - Graph-based citation context
 * - Similar citation patterns
 */
class DecisionAnalyzeCitationsTool extends BaseTool
{
    public function __construct(
        protected DecisionSearchService $searchService
    ) {}

    public function name(): string
    {
        return 'decision.analyze_citations';
    }

    public function description(): string
    {
        return 'Get comprehensive citation analysis for a court decision';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decision_id' => [
                    'type' => 'string',
                    'description' => 'Court decision document ID (ULID)',
                ],
            ],
            'required' => ['decision_id'],
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Validate input
        $validator = Validator::make($arguments, [
            'decision_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $result = $this->searchService->analyzeCitations($arguments['decision_id']);

            if (! $result['success']) {
                return $this->error($result['error'] ?? 'Failed to analyze citations');
            }

            return $this->success([
                'decision_id' => $arguments['decision_id'],
                'citations' => $result['citations'] ?? [],
                'statistics' => $result['statistics'] ?? [],
                'canonical_citations' => $result['canonical_citations'] ?? [],
                'graph_context' => $result['graph_context'] ?? null,
                'similar_citation_patterns' => $result['similar_citation_patterns'] ?? [],
            ], 'Citation analysis completed');
        } catch (\Exception $e) {
            return $this->error('Failed to analyze citations: '.$e->getMessage());
        }
    }
}
