<?php

namespace App\Mcp\Tools;

use App\Services\FactExtractionService;
use Illuminate\Support\Facades\Validator;

/**
 * MCP Tool: Compare legal facts between two court decisions
 *
 * Compares extracted facts between decisions to identify similarities
 * in parties, issues, legal grounds, and holdings.
 */
class DecisionCompareFactsTool extends BaseTool
{
    public function __construct(
        protected FactExtractionService $factService
    ) {}

    public function name(): string
    {
        return 'decision.compare_facts';
    }

    public function description(): string
    {
        return 'Compare legal facts between two court decisions';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decision_id_1' => [
                    'type' => 'string',
                    'description' => 'First court decision document ID (ULID)',
                ],
                'decision_id_2' => [
                    'type' => 'string',
                    'description' => 'Second court decision document ID (ULID)',
                ],
            ],
            'required' => ['decision_id_1', 'decision_id_2'],
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Validate input
        $validator = Validator::make($arguments, [
            'decision_id_1' => 'required|string',
            'decision_id_2' => 'required|string|different:decision_id_1',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $result = $this->factService->compareDecisionFacts(
                $arguments['decision_id_1'],
                $arguments['decision_id_2']
            );

            if (! $result['success']) {
                return $this->error($result['error'] ?? 'Failed to compare facts');
            }

            return $this->success([
                'decision1' => $result['decision1'],
                'decision2' => $result['decision2'],
                'basic_similarity' => $result['basic_similarity'],
                'complex_similarity' => $result['complex_similarity'],
            ], 'Facts compared successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to compare facts: '.$e->getMessage());
        }
    }
}
