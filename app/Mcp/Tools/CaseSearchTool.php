<?php

namespace App\Mcp\Tools;

use App\Services\CaseSearchService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class CaseSearchTool extends Tool
{
    protected string $name = 'case.search';

    protected string $title = 'Search Cases [PRIVATE]';

    protected string $description = 'Search legal cases and their documents. PRIVATE: Requires authentication. Supports keyword and vector search. Search by case_id and/or query text within case documents.';

    /**
     * Constructor
     */
    public function __construct(
        protected CaseSearchService $searchService
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free text search within case documents and metadata'),
            'search_type' => $schema->string()->enum(['keyword', 'vector', 'cases', 'documents'])
                ->default('cases')->description('Type of search: keyword/vector (for documents with embeddings), cases (LegalCase model), or documents (CaseDocument model)'),
            'case_id' => $schema->string()->description('Filter by specific case ID (ULID)'),
            'case_number' => $schema->string()->description('Filter by case number'),
            'client_name' => $schema->string()->description('Filter by client name'),
            'opponent_name' => $schema->string()->description('Filter by opponent name'),
            'court' => $schema->string()->description('Filter by court name'),
            'jurisdiction' => $schema->string()->description('Filter by jurisdiction'),
            'status' => $schema->string()->description('Filter by case status'),
            'tags' => $schema->string()->description('Comma-separated tags to filter by'),
            'search_documents' => $schema->boolean()->default(true)
                ->description('Search within case documents (default: true) - deprecated, use search_type instead'),
            'include_content' => $schema->boolean()->default(false)
                ->description('Include document content in results (can be large)'),
            'limit' => $schema->integer()->minimum(1)->maximum(100)->default(10)
                ->description('Number of results per page'),
            'page' => $schema->integer()->minimum(1)->default(1)->description('Page number for pagination'),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        $searchType = $arguments['search_type'] ?? 'cases';
        $query = $arguments['query'] ?? '';

        // Handle backward compatibility for search_documents flag
        if (! isset($arguments['search_type']) && isset($arguments['search_documents'])) {
            $searchType = $arguments['search_documents'] ? 'documents' : 'cases';
        }

        // Use service for all search types
        if ($searchType === 'vector') {
            // Vector search on case documents
            $result = $this->searchService->search($query, [
                'search_type' => 'vector',
                'filters' => $arguments,
            ]);
        } elseif ($searchType === 'documents') {
            // Keyword search on case documents
            $result = $this->searchService->searchDocuments($query, $arguments);
        } elseif ($searchType === 'cases') {
            // Search legal cases
            $result = $this->searchService->searchCases($query, $arguments);
        } elseif ($searchType === 'keyword') {
            // Default to documents for keyword search
            $result = $this->searchService->searchDocuments($query, $arguments);
        } else {
            $result = [
                'success' => false,
                'error' => 'Invalid search_type. Use: vector, keyword, cases, or documents',
            ];
        }

        return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function description(): string
    {
        return '[PRIVATE] Search legal cases and documents. Supports vector (semantic) and keyword search. Requires authentication.';
    }
}
