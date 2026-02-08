<?php

namespace App\Mcp\Tools;

use App\Services\LawSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Legal Concept Tool
 *
 * Provides legal concept analysis including:
 * - Concept definition lookup
 * - Related concepts discovery
 * - Precedent identification
 * - Doctrinal analysis
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class LegalConceptTool extends Tool
{
    protected string $name = 'legal.concept';

    protected string $title = 'Analyze Legal Concept';

    protected string $description = 'Analyze legal concepts with various operations: define concept, find related concepts, identify precedents, or analyze doctrine.';

    /**
     * Constructor
     */
    public function __construct(
        protected LawSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'concept' => $schema->string()->description('Legal concept to analyze (e.g., "proportionality", "due process", "illegal search")'),
            'operation' => $schema->string()->enum(['define', 'related', 'precedents', 'doctrine'])
                ->default('define')->description('Type of operation: define (get definition), related (find related concepts), precedents (identify relevant cases), doctrine (analyze doctrine)'),
            'jurisdiction' => $schema->string()->default('HR')
                ->description('Jurisdiction code (e.g., "HR" for Croatia, "US" for United States)'),
            'limit' => $schema->integer()->minimum(1)->maximum(50)->default(10)
                ->description('Maximum number of results for related/precedents operations'),
            'include_examples' => $schema->boolean()->default(true)
                ->description('Include practical examples in the response'),
        ];
    }

    public function handle(Request $request): Response
    {
        $concept = $request->get('concept', '');
        $operation = $request->get('operation', 'define');

        if (empty($concept)) {
            return Response::text(json_encode([
                'error' => 'concept is required',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        try {
            // Delegate to service for concept analysis
            $result = $this->searchService->analyzeConcept($concept, $request->all());

            return Response::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return Response::text(json_encode([
                'error' => 'Failed to analyze legal concept',
                'message' => $e->getMessage(),
                'concept' => $concept,
                'operation' => $operation,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function description(): string
    {
        return 'Analyze legal concepts with multiple operations: get definitions, discover related concepts, identify relevant precedents, or analyze doctrinal foundations. Returns structured concept analysis.';
    }
}
