<?php

namespace App\Tools\Research;

use App\Services\DecisionSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Decision Keyword Search Tool
 *
 * Provides exact keyword/text matching search across Croatian court decisions.
 */
class DecisionKeywordSearchTool implements ToolInterface
{
    public function __construct(
        protected DecisionSearchService $decisionSearch
    ) {}

    /**
     * Get the tool's definition for the LLM (JSON schema compatible).
     */
    public function definition(): array
    {
        return [
            'name' => 'decision_keyword_search',
            'description' => 'Keyword-based search across Croatian court decisions. Use this for exact term matching, case numbers, or specific phrases.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query for keyword matching',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results per page (default: 10)',
                        'default' => 10,
                    ],
                    'page' => [
                        'type' => 'integer',
                        'description' => 'Page number for pagination (default: 1)',
                        'default' => 1,
                    ],
                    'case_number' => [
                        'type' => 'string',
                        'description' => 'Filter by case number',
                    ],
                    'court' => [
                        'type' => 'string',
                        'description' => 'Filter by court name',
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (e.g., constitutional, administrative)',
                    ],
                    'judge' => [
                        'type' => 'string',
                        'description' => 'Filter by judge name',
                    ],
                    'decision_type' => [
                        'type' => 'string',
                        'description' => 'Filter by decision type',
                    ],
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Filter by start date (YYYY-MM-DD)',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'Filter by end date (YYYY-MM-DD)',
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    /**
     * Execute the tool's logic.
     *
     * @param  array  $arguments  Arguments provided by the LLM.
     * @param  AgentContext  $context  The current agent context.
     * @param  AgentMemory  $memory  The agent's memory manager.
     * @return string JSON string representation of the tool's result.
     */
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        try {
            $query = $arguments['query'];

            // Build filters from arguments
            $filters = [
                'limit' => $arguments['limit'] ?? 10,
                'page' => $arguments['page'] ?? 1,
            ];

            // Add optional filters
            foreach (['case_number', 'court', 'jurisdiction', 'judge', 'decision_type', 'date_from', 'date_to'] as $filter) {
                if (isset($arguments[$filter])) {
                    $filters[$filter] = $arguments[$filter];
                }
            }

            $results = $this->decisionSearch->keywordSearch($query, $filters);

            return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
