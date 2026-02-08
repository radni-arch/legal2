<?php

namespace App\Tools\Research;

use App\Services\GraphDatabaseService;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class GraphQueryTool implements ToolInterface
{
    public function __construct(
        protected GraphDatabaseService $graphDatabase
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'graph_query',
            'description' => 'Execute Cypher queries against the Neo4j graph database to explore relationships between laws, cases, and decisions',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'cypher' => [
                        'type' => 'string',
                        'description' => 'Cypher query to execute against the graph database',
                    ],
                    'parameters' => [
                        'type' => 'object',
                        'description' => 'Query parameters for parameterized queries',
                        'default' => [],
                    ],
                ],
                'required' => ['cypher'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, ?AgentMemory $memory = null): string
    {
        $cypher = $arguments['cypher'];
        $parameters = $arguments['parameters'] ?? [];

        try {
            $result = $this->graphDatabase->run($cypher, $parameters);

            // Convert result to array
            $rows = $result->toArray();
            $count = $result->count();

            return json_encode([
                'success' => true,
                'count' => $count,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
