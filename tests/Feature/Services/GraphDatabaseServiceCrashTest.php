<?php

namespace Tests\Feature\Services;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GraphDatabaseServiceCrashTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test that transaction() throws RuntimeException when Neo4j client is null
     */
    public function test_transaction_throws_exception_when_client_is_null(): void
    {
        // Force Neo4j to be unavailable by using invalid configuration
        config([
            'database.connections.neo4j.host' => 'invalid-host-that-does-not-exist',
            'database.connections.neo4j.port' => 99999,
        ]);

        // Create service - this should result in null client
        $service = new GraphDatabaseService;

        // Expect RuntimeException when trying to execute a transaction
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Neo4j client is not available. Cannot execute transaction.');

        $service->transaction(function ($tsx) {
            return $tsx->run('RETURN 1');
        });
    }

    /**
     * Test that storeDecisionInGraph() returns gracefully when Neo4j is unavailable
     */
    public function test_store_decision_in_graph_returns_gracefully_when_client_is_null(): void
    {
        // Force Neo4j to be unavailable
        config([
            'database.connections.neo4j.host' => 'invalid-host-that-does-not-exist',
            'database.connections.neo4j.port' => 99999,
        ]);

        // Capture logs
        Log::shouldReceive('warning')
            ->once()
            ->with('Neo4j is not available. Skipping graph storage for decision.', \Mockery::on(function ($context) {
                return isset($context['doc_id']) && $context['doc_id'] === 'test-doc-123'
                    && isset($context['ecli']) && $context['ecli'] === 'ECLI:HR:VSRH:2024:123';
            }));

        // Create service
        $service = new GraphDatabaseService;

        // This should not throw - should return gracefully with warning log
        $meta = [
            'ecli' => 'ECLI:HR:VSRH:2024:123',
            'case_number' => 'Rev-1234/2024',
            'court' => 'Vrhovni sud Republike Hrvatske',
        ];

        $service->storeDecisionInGraph($meta, 'test-doc-123');

        // If we get here without exception, test passes
        $this->assertTrue(true);
    }

    /**
     * Test that storeDecisionInGraph() logs warning with correct context
     */
    public function test_store_decision_logs_warning_with_context(): void
    {
        // Force Neo4j to be unavailable
        config([
            'database.connections.neo4j.host' => 'invalid-host',
            'database.connections.neo4j.port' => 99999,
        ]);

        // Capture and verify warning log
        Log::shouldReceive('warning')
            ->once()
            ->with('Neo4j is not available. Skipping graph storage for decision.', [
                'doc_id' => 'doc-456',
                'ecli' => 'ECLI:HR:VSRH:2024:456',
            ]);

        $service = new GraphDatabaseService;

        $meta = [
            'ecli' => 'ECLI:HR:VSRH:2024:456',
            'case_number' => 'Rev-456/2024',
        ];

        $service->storeDecisionInGraph($meta, 'doc-456');

        $this->assertTrue(true);
    }

    /**
     * Test that storeDecisionInGraph() handles missing ECLI gracefully
     */
    public function test_store_decision_handles_missing_ecli_gracefully(): void
    {
        // Force Neo4j to be unavailable
        config([
            'database.connections.neo4j.host' => 'invalid-host',
            'database.connections.neo4j.port' => 99999,
        ]);

        // Should log warning with null ECLI
        Log::shouldReceive('warning')
            ->once()
            ->with('Neo4j is not available. Skipping graph storage for decision.', [
                'doc_id' => 'doc-789',
                'ecli' => null,
            ]);

        $service = new GraphDatabaseService;

        // Meta without ECLI
        $meta = [
            'case_number' => 'Rev-789/2024',
            'court' => 'Some Court',
        ];

        $service->storeDecisionInGraph($meta, 'doc-789');

        $this->assertTrue(true);
    }

    /**
     * Test that multiple calls to storeDecisionInGraph() all return gracefully
     */
    public function test_multiple_store_calls_all_return_gracefully(): void
    {
        // Force Neo4j to be unavailable
        config([
            'database.connections.neo4j.host' => 'invalid-host',
            'database.connections.neo4j.port' => 99999,
        ]);

        // Expect multiple warning logs
        Log::shouldReceive('warning')->times(3);

        $service = new GraphDatabaseService;

        // Call multiple times - all should return gracefully
        for ($i = 1; $i <= 3; $i++) {
            $service->storeDecisionInGraph(
                ['ecli' => "ECLI:HR:VSRH:2024:{$i}", 'case_number' => "Rev-{$i}/2024"],
                "doc-{$i}"
            );
        }

        // If we get here, all calls returned gracefully
        $this->assertTrue(true);
    }
}
