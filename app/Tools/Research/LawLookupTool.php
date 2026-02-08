<?php

namespace App\Tools\Research;

use App\Services\LawSearchService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawLookupTool implements ToolInterface
{
    public function __construct(
        protected LawSearchService $lawSearch
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'law_lookup',
            'description' => 'Lookup a specific Croatian law by its official number (e.g., "NN 152/08"). Returns complete law information including all chunks.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'law_number' => [
                        'type' => 'string',
                        'description' => 'Official law number (e.g., "NN 152/08" for Narodne novine)',
                    ],
                    'jurisdiction' => [
                        'type' => 'string',
                        'description' => 'Filter by jurisdiction (optional)',
                    ],
                ],
                'required' => ['law_number'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $lawNumber = $arguments['law_number'];
        $jurisdiction = $arguments['jurisdiction'] ?? null;

        try {
            $result = $this->lawSearch->lookupByNumber($lawNumber, $jurisdiction);

            return json_encode([
                'success' => true,
                'lookup_type' => 'law_number',
                'data' => $result,
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
