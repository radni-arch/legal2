<?php

namespace App\Mcp\Tools;

use App\Services\LawSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class LawSearchTool extends Tool
{
    protected string $name = 'law.search';

    protected string $title = 'Search Laws';

    protected string $description = 'Search laws by query with optional filters (jurisdiction, country, language, law_number, tags). Supports keyword, vector, and hybrid search. Returns paginated results with minimal payload.';

    /**
     * Constructor
     */
    public function __construct(
        protected LawSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free text search query for law title or content'),
            'search_type' => $schema->string()->enum(['keyword', 'vector', 'hybrid'])
                ->default('keyword')->description('Type of search to perform: keyword (exact/LIKE matching), vector (semantic similarity), or hybrid (combination of both)'),
            'doc_id' => $schema->string()->description('Filter by specific document ID'),
            'law_number' => $schema->string()->description('Filter by specific law number'),
            'jurisdiction' => $schema->string()->description('Filter by jurisdiction (e.g., "federal", "state")'),
            'country' => $schema->string()->description('Filter by country code (e.g., "HR", "US")'),
            'language' => $schema->string()->description('Filter by language code (e.g., "hr", "en")'),
            'tags' => $schema->string()->description('Comma-separated tags to filter by'),
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
        return 'Search laws with optional filters. Supports keyword, vector (semantic), and hybrid search types. Returns paginated results with law metadata.';
    }
}
