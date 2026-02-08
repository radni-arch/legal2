<?php

namespace App\Mcp\Tools;

use App\Services\DecisionSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class DecisionSearchTool extends Tool
{
    protected string $name = 'decision.search';

    protected string $title = 'Search Court Decisions';

    protected string $description = 'Search court decisions by query with optional filters (case_number, court, jurisdiction, judge, decision_type, ecli). Supports keyword, vector, and hybrid search. Returns paginated results with minimal payload.';

    /**
     * Constructor
     */
    public function __construct(
        protected DecisionSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free text search query for decision title, description, or case number'),
            'search_type' => $schema->string()->enum(['keyword', 'vector', 'hybrid'])
                ->default('keyword')->description('Type of search to perform: keyword (exact/LIKE matching), vector (semantic similarity), or hybrid (combination of both)'),
            'case_number' => $schema->string()->description('Filter by specific case number'),
            'court' => $schema->string()->description('Filter by court name'),
            'jurisdiction' => $schema->string()->description('Filter by jurisdiction'),
            'judge' => $schema->string()->description('Filter by judge name'),
            'decision_type' => $schema->string()->description('Filter by decision type (e.g., "Presuda", "Rješenje")'),
            'register' => $schema->string()->description('Filter by court register'),
            'ecli' => $schema->string()->description('Filter by ECLI identifier'),
            'finality' => $schema->string()->description('Filter by finality status'),
            'tags' => $schema->string()->description('Comma-separated tags to filter by'),
            'date_from' => $schema->string()->description('Filter decisions from this date (YYYY-MM-DD)'),
            'date_to' => $schema->string()->description('Filter decisions until this date (YYYY-MM-DD)'),
            'limit' => $schema->integer()->minimum(1)->maximum(100)->default(10)
                ->description('Number of results per page'),
            'page' => $schema->integer()->minimum(1)->default(1)->description('Page number for pagination'),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        $searchType = $arguments['search_type'] ?? 'keyword';
        $query = $arguments['query'] ?? '';

        if ($searchType === 'vector' || $searchType === 'hybrid') {
            // Use service for vector/hybrid search
            $result = $this->searchService->search($query, [
                'search_type' => $searchType,
                'filters' => $arguments,
            ]);

            return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // For keyword search, delegate to service
        $result = $this->searchService->keywordSearch($query, $arguments);

        return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function description(): string
    {
        return 'Search court decisions with optional filters. Supports keyword, vector (semantic), and hybrid search types. Returns paginated results with decision metadata.';
    }
}
