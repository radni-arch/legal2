<?php

namespace App\Tools\Research;

use App\Services\CaseSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class CaseSearchTool implements ToolInterface
{
    public function __construct(
        protected CaseSearchService $caseSearch
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'case_search',
            'description' => 'Search legal cases by case number, title, client, opponent, or other criteria',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query to match against case fields',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (default: 10)',
                        'default' => 10,
                    ],
                    'page' => [
                        'type' => 'integer',
                        'description' => 'Page number for pagination (default: 1)',
                        'default' => 1,
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (optional)',
                    ],
                    'court' => [
                        'type' => 'string',
                        'description' => 'Filter by court name (optional)',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by case status (optional)',
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
            'page' => $arguments['page'] ?? 1,
            'jurisdiction' => $arguments['jurisdiction'] ?? null,
            'court' => $arguments['court'] ?? null,
            'status' => $arguments['status'] ?? null,
        ];

        try {
            $results = $this->caseSearch->searchCases($query, $filters);

            return json_encode([
                'success' => $results['success'] ?? true,
                'search_type' => 'cases',
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
