<?php

namespace App\Tools\Research;

use App\Services\LawSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawKeywordSearchTool implements ToolInterface
{
    public function __construct(
        protected LawSearchService $lawSearch
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'law_keyword_search',
            'description' => 'Keyword-based search across Croatian laws. Returns laws matching the query terms in title or content.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query for finding relevant law provisions',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum results to return (default: 10)',
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (optional)',
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 10;
        $jurisdiction = $arguments['jurisdiction'] ?? null;

        try {
            $results = $this->lawSearch->keywordSearch($query, [
                'limit' => $limit,
                'jurisdiction' => $jurisdiction,
            ]);

            return json_encode([
                'success' => true,
                'search_type' => 'keyword',
                'count' => count($results['data'] ?? []),
                'data' => $results['data'] ?? [],
                'pagination' => $results['pagination'] ?? null,
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
