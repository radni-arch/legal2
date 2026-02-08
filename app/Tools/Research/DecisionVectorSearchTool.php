<?php

namespace App\Tools\Research;

use App\Services\DecisionSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Decision Vector Search Tool
 *
 * Provides semantic search across Croatian court decisions using vector embeddings.
 */
class DecisionVectorSearchTool implements ToolInterface
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
            'name' => 'decision_vector_search',
            'description' => 'Semantic search across Croatian court decisions using vector embeddings. Use this for conceptual or meaning-based queries.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query for finding relevant court decisions',
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
                        'description' => 'Minimum similarity score (0.0-1.0, default: 0.7)',
                        'default' => 0.7,
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
            $filters = [
                'limit' => $arguments['limit'] ?? 10,
                'jurisdiction' => $arguments['jurisdiction'] ?? null,
                'min_similarity' => $arguments['min_similarity'] ?? 0.7,
            ];

            $results = $this->decisionSearch->vectorSearch($query, $filters);

            return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
