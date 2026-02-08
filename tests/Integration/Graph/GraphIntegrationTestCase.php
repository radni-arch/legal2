<?php

namespace Tests\Integration\Graph;

use Tests\TestCase;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Config;

abstract class GraphIntegrationTestCase extends TestCase
{
    protected GraphDatabaseService $graph;
    protected static bool $containerStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test Neo4j connection
        Config::set('neo4j.connections.bolt.host', 'localhost');
        Config::set('neo4j.connections.bolt.port', 7688);
        Config::set('neo4j.connections.bolt.username', 'neo4j');
        Config::set('neo4j.connections.bolt.user', 'neo4j');
        Config::set('neo4j.connections.bolt.password', 'testpassword');

        $this->graph = app(GraphDatabaseService::class);

        // Clean test database before each test
        $this->cleanDatabase();
    }

    protected function cleanDatabase(): void
    {
        try {
            $this->graph->run('MATCH (n) DETACH DELETE n');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Neo4j test container not available: ' . $e->getMessage());
        }
    }

    protected function assertNodeExists(string $label, array $properties): void
    {
        $whereClause = collect($properties)
            ->map(fn($v, $k) => "n.{$k} = \${$k}")
            ->implode(' AND ');

        $result = $this->graph->run(
            "MATCH (n:{$label}) WHERE {$whereClause} RETURN count(n) as count",
            $properties
        );

        $count = $result->first()->get('count');

        $this->assertGreaterThan(0, $count,
            "Node {$label} with properties " . json_encode($properties) . " not found");
    }

    protected function assertRelationshipExists(
        string $fromLabel,
        string $fromId,
        string $relType,
        string $toLabel,
        string $toId
    ): void {
        $result = $this->graph->run(
            "MATCH (a:{$fromLabel} {id: \$fromId})-[r:{$relType}]->(b:{$toLabel} {id: \$toId})
             RETURN count(r) as count",
            ['fromId' => $fromId, 'toId' => $toId]
        );

        $count = $result->first()->get('count');

        $this->assertGreaterThan(0, $count,
            "Relationship {$fromLabel}({$fromId})-[{$relType}]->{$toLabel}({$toId}) not found");
    }
}
