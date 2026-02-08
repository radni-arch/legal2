<?php

namespace App\Mcp\Tools;

use App\Services\DecisionSearchService;
use Illuminate\Support\Facades\Validator;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * MCP Tool: Extract citations from a court decision
 *
 * Extracts all legal citations (statutes, case numbers, ECLI identifiers)
 * from a court decision document with detailed categorization.
 */
class DecisionExtractCitationsTool extends BaseTool
{
    public function __construct(
        protected DecisionSearchService $searchService
    ) {}

    public function name(): string
    {
        return 'decision.extract_citations';
    }

    public function description(): string
    {
        return 'Extract all legal citations from a court decision (statutes, cases, ECLI)';
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
            ],
            'required' => ['decision_id'],
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Validate input
        $validator = Validator::make($arguments, [
            'decision_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: '.$validator->errors()->first());
        }

        try {
            $result = $this->searchService->extractCitations($arguments['decision_id']);

            return $this->success([
                'decision_id' => $arguments['decision_id'],
                'citations' => $result['citations'] ?? [],
                'statistics' => $result['statistics'] ?? [],
                'canonical_citations' => $result['canonical_citations'] ?? [],
                'law_numbers' => $result['law_numbers'] ?? [],
                'case_identifiers' => $result['case_identifiers'] ?? [],
            ], 'Citations extracted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to extract citations: '.$e->getMessage());
        }
    }
}
