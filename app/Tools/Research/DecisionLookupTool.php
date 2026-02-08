<?php

namespace App\Tools\Research;

use App\Services\DecisionSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Decision Lookup Tool
 *
 * Lookup specific court decisions by case number, court, date range, and other criteria.
 */
class DecisionLookupTool implements ToolInterface
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
            'name' => 'decision_lookup',
            'description' => 'Lookup Croatian court decisions by specific criteria such as case number, court, jurisdiction, or date range. Use this when you have specific decision identifiers.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'case_number' => [
                        'type' => 'string',
                        'description' => 'Case number to lookup (e.g., U-I-123/2024)',
                    ],
                    'court' => [
                        'type' => 'string',
                        'description' => 'Filter by court name (e.g., Ustavni sud, Vrhovni sud)',
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (e.g., constitutional, civil, criminal)',
                    ],
                    'from_date' => [
                        'type' => 'string',
                        'description' => 'Start date for decision date range (YYYY-MM-DD)',
                    ],
                    'to_date' => [
                        'type' => 'string',
                        'description' => 'End date for decision date range (YYYY-MM-DD)',
                    ],
                    'decision_type' => [
                        'type' => 'string',
                        'description' => 'Filter by decision type',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (default: 20)',
                        'default' => 20,
                    ],
                ],
                'required' => [],
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
            // Build criteria from arguments
            $criteria = [];

            foreach (['case_number', 'court', 'jurisdiction', 'from_date', 'to_date', 'decision_type', 'limit'] as $field) {
                if (isset($arguments[$field])) {
                    $criteria[$field] = $arguments[$field];
                }
            }

            // Set default limit if not provided
            if (!isset($criteria['limit'])) {
                $criteria['limit'] = 20;
            }

            $results = $this->decisionSearch->lookupByCriteria($criteria);

            return json_encode([
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'criteria' => $criteria,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
