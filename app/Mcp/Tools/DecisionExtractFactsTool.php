<?php

namespace App\Mcp\Tools;

use App\Services\FactExtractionService;
use Illuminate\Support\Facades\Validator;

/**
 * MCP Tool: Extract legal facts from a court decision
 *
 * Extracts structured legal facts including parties, issues, holdings,
 * arguments, evidence, and procedural history.
 */
class DecisionExtractFactsTool extends BaseTool
{
    public function __construct(
        protected FactExtractionService $factService
    ) {}

    public function name(): string
    {
        return 'decision.extract_facts';
    }

    public function description(): string
    {
        return 'Extract legal facts from a court decision (parties, issues, holdings, arguments, evidence)';
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
                'use_llm' => [
                    'type' => 'boolean',
                    'description' => 'Use LLM for complex fact extraction (default: true)',
                    'default' => true,
                ],
                'use_cache' => [
                    'type' => 'boolean',
                    'description' => 'Enable caching (default: true)',
                    'default' => true,
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
            'use_llm' => 'sometimes|boolean',
            'use_cache' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $options = [
                'use_llm' => $arguments['use_llm'] ?? true,
                'use_cache' => $arguments['use_cache'] ?? true,
            ];

            $result = $this->factService->extractFacts($arguments['decision_id'], $options);

            if (! $result['success']) {
                return $this->error($result['error'] ?? 'Failed to extract facts');
            }

            return $this->success([
                'decision_id' => $arguments['decision_id'],
                'basic_facts' => $result['basic_facts'],
                'complex_facts' => $result['complex_facts'] ?? null,
                'extraction_method' => $result['extraction_method'],
                'from_cache' => $result['from_cache'],
                'performance' => $result['performance'],
            ], 'Facts extracted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to extract facts: '.$e->getMessage());
        }
    }
}
