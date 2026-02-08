<?php

namespace App\Mcp\Tools;

use App\Services\DecisionCitationService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Citation Network Tool
 *
 * Provides citation network analysis including:
 * - Citation graph traversal
 * - Authority scoring
 * - Citation patterns
 * - Influence mapping
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class CitationNetworkTool extends Tool
{
    protected string $name = 'citation.network';

    protected string $title = 'Analyze Citation Network';

    protected string $description = 'Analyze citation networks with various operations: traverse citation graph, calculate authority scores, analyze citation patterns, or map influence.';

    /**
     * Constructor
     */
    public function __construct(
        protected DecisionCitationService $citationService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision_id' => $schema->string()->description('Decision ID to analyze in the citation network'),
            'operation' => $schema->string()->enum(['graph', 'authority', 'patterns', 'influence'])
                ->default('graph')->description('Type of operation: graph (citation graph traversal), authority (authority scoring), patterns (citation patterns), influence (influence mapping)'),
            'depth' => $schema->integer()->minimum(1)->maximum(5)->default(2)
                ->description('Depth for graph traversal (number of citation levels)'),
            'limit' => $schema->integer()->minimum(1)->maximum(100)->default(10)
                ->description('Maximum number of results for influence operation'),
            'direction' => $schema->string()->enum(['forward', 'backward', 'both'])
                ->default('both')->description('Citation direction: forward (this cites), backward (cited by), both'),
        ];
    }

    public function handle(Request $request): Response
    {
        $decisionId = $request->get('decision_id', '');
        $operation = $request->get('operation', 'graph');

        if (empty($decisionId)) {
            return Response::text(json_encode([
                'error' => 'decision_id is required',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        try {
            // Delegate to service for citation analysis
            $result = $this->citationService->analyzeCitations($decisionId, $request->all());

            return Response::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return Response::text(json_encode([
                'error' => 'Failed to analyze citation network',
                'message' => $e->getMessage(),
                'decision_id' => $decisionId,
                'operation' => $operation,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function description(): string
    {
        return 'Analyze citation networks with multiple operations: traverse citation graphs, calculate authority scores, identify citation patterns, or map influence propagation. Returns structured network analysis.';
    }
}
