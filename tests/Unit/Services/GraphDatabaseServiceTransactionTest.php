<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for GraphDatabaseService transaction handling.
 *
 * Note: The transaction success path (callback execution, commit, etc.) is tested
 * in the integration test Neo4jComprehensiveTest::test_transaction_rollback_on_error
 * which uses a real Neo4j connection. Unit testing the transaction method would
 * require mocking the Neo4j client which cannot be easily injected, leading to
 * circular mocks that don't test the real implementation.
 */
class GraphDatabaseServiceTransactionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_when_client_unavailable(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $service = new GraphDatabaseService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Neo4j client is not available');

        $service->transaction(function () {
            return 'should not execute';
        });
    }
}
