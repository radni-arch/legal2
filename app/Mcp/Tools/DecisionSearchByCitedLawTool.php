<?php

namespace App\Mcp\Tools;

use App\Services\DecisionSearchService;
use Illuminate\Support\Facades\Validator;

/**
 * MCP Tool: Search decisions by cited law
 *
 * Finds court decisions that cite a specific law and article
 */
class DecisionSearchByCitedLawTool extends BaseTool
{
    public function __construct(
        protected DecisionSearchService $searchService
    ) {}

    public function name(): string
    {
        return 'decision.search_by_cited_law';
    }

    public function description(): string
    {
        return 'Search court decisions that cite a specific law and article';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'law_identifier' => [
                    'type' => 'string',
                    'description' => 'Law abbreviation or number (e.g., "ZPP", "NN 53/91")',
                ],
                'article_number' => [
                    'type' => 'string',
                    'description' => 'Optional article number (e.g., "110")',
                ],
                'filters' => [
                    'type' => 'object',
                    'description' => 'Optional filters',
                    'properties' => [
                        'court' => [
                            'type' => 'string',
                            'description' => 'Filter by court name',
                        ],
                        'jurisdiction' => [
                            'type' => 'string',
                            'description' => 'Filter by jurisdiction',
                        ],
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Filter by decision date (from)',
                            'format' => 'date',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Filter by decision date (to)',
                            'format' => 'date',
                        ],
                    ],
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default: 50)',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
            'required' => ['law_identifier'],
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Validate input
        $validator = Validator::make($arguments, [
            'law_identifier' => 'required|string',
            'article_number' => 'sometimes|string',
            'filters' => 'sometimes|array',
            'filters.court' => 'sometimes|string',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $options = array_merge(
                $arguments['filters'] ?? [],
                ['limit' => $arguments['limit'] ?? 50]
            );

            $result = $this->searchService->searchByCitedLaw(
                lawIdentifier: $arguments['law_identifier'],
                articleNumber: $arguments['article_number'] ?? null,
                options: $options
            );

            return $this->success([
                'query' => $result['query'],
                'total_results' => $result['total_results'],
                'results' => $result['results'],
            ], 'Decisions found');
        } catch (\Exception $e) {
            return $this->error('Failed to search decisions by cited law: '.$e->getMessage());
        }
    }
}
