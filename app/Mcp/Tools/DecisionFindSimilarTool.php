<?php

namespace App\Mcp\Tools;

use App\Services\DecisionSearchService;
use Illuminate\Support\Facades\Validator;

/**
 * MCP Tool: Find similar court decisions
 *
 * Finds similar decisions using multiple similarity methods:
 * - Content similarity (semantic embeddings)
 * - Citation pattern similarity
 * - Graph relationship similarity
 */
class DecisionFindSimilarTool extends BaseTool
{
    public function __construct(
        protected DecisionSearchService $searchService
    ) {}

    public function name(): string
    {
        return 'decision.find_similar';
    }

    public function description(): string
    {
        return 'Find similar court decisions using content, citations, and graph relationships';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decision_id' => [
                    'type' => 'string',
                    'description' => 'Court decision document ID (ULID)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default: 20)',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'threshold' => [
                    'type' => 'number',
                    'description' => 'Similarity threshold for vector search (0-1, default: 0.75)',
                    'default' => 0.75,
                    'minimum' => 0,
                    'maximum' => 1,
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
            ],
            'required' => ['decision_id'],
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Validate input
        $validator = Validator::make($arguments, [
            'decision_id' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
            'filters' => 'sometimes|array',
            'filters.court' => 'sometimes|string',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $options = [
                'limit' => $arguments['limit'] ?? 20,
                'threshold' => $arguments['threshold'] ?? 0.75,
                'filters' => $arguments['filters'] ?? [],
            ];

            $result = $this->searchService->findSimilar($arguments['decision_id'], $options);

            if (! $result['success']) {
                return $this->error($result['error'] ?? 'Failed to find similar decisions');
            }

            return $this->success([
                'source_decision' => $result['source_decision'],
                'total_results' => $result['total_results'],
                'results' => $result['results'],
                'similarity_methods' => $result['similarity_methods'],
            ], 'Similar decisions found');
        } catch (\Exception $e) {
            return $this->error('Failed to find similar decisions: '.$e->getMessage());
        }
    }
}
