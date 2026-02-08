<?php

namespace App\Tools\Research;

use App\Services\CaseSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class CaseVectorSearchTool implements ToolInterface
{
    public function __construct(
        protected CaseSearchService $caseSearch
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'case_vector_search',
            'description' => 'Semantic vector search across case documents using embeddings',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query for finding relevant case documents',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (default: 10)',
                        'default' => 10,
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (optional)',
                    ],
                    'min_similarity' => [
                        'type' => 'number',
                        'description' => 'Minimum similarity score (0-1, default: 0.7)',
                        'default' => 0.7,
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, ?AgentMemory $memory = null): string
    {
        $query = $arguments['query'];
        $filters = [
            'limit' => $arguments['limit'] ?? 10,
            'jurisdiction' => $arguments['jurisdiction'] ?? null,
            'min_similarity' => $arguments['min_similarity'] ?? 0.7,
        ];

        try {
            $results = $this->caseSearch->vectorSearch($query, $filters);

            return json_encode([
                'success' => $results['success'] ?? true,
                'search_type' => 'vector',
                'count' => count($results['data'] ?? []),
                'data' => $results['data'] ?? [],
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
