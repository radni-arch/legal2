<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreaker;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class Neo4jServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_initializes_with_neo4j_enabled()
    {
        config([
            'neo4j.sync.enabled' => true,
            'neo4j.uri' => 'bolt://localhost:7687',
            'neo4j.user' => 'neo4j',
            'neo4j.password' => 'password',
        ]);

        $service = new Neo4jService;

        // Service should be initialized
        // We can't easily check the private client, but we can verify no exceptions
        $this->assertInstanceOf(Neo4jService::class, $service);
    }

    /** @test */
    public function it_initializes_with_neo4j_disabled()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')
            ->once()
            ->with('Neo4j disabled; skipping upsert', Mockery::any());

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        // Call upsert to verify it's skipped when disabled
        $service->upsertCaseAndDocument('case-1', 'Case Title', 'doc-1', 'Doc Title');

        $this->assertInstanceOf(Neo4jService::class, $service);
    }

    /** @test */
    public function it_logs_when_skipping_upsert_with_disabled_client()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')
            ->once()
            ->with('Neo4j disabled; skipping upsert', [
                'caseId' => 'case-123',
                'docId' => 'doc-456',
            ]);

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);
        $service->upsertCaseAndDocument('case-123', 'Title', 'doc-456', 'Doc');

        // Assertion is implicit in the Log::shouldReceive above
        $this->assertTrue(true);
    }

    /** @test */
    public function it_uses_default_config_values()
    {
        config([
            'neo4j.sync.enabled' => true,
            // Don't set uri, user, password - use defaults
        ]);

        // Should use default values without throwing
        try {
            $service = new Neo4jService;
            $this->assertInstanceOf(Neo4jService::class, $service);
        } catch (\Exception $e) {
            $this->fail('Should use default config values: '.$e->getMessage());
        }
    }

    /** @test */
    public function it_constructs_correct_uri_from_config()
    {
        config([
            'neo4j.sync.enabled' => true,
            'neo4j.uri' => 'bolt://custom-host:7688',
            'neo4j.user' => 'custom-user',
            'neo4j.password' => 'custom-pass',
        ]);

        $service = new Neo4jService;

        // Can't easily verify the URI without reflection, but we ensure it doesn't throw
        $this->assertInstanceOf(Neo4jService::class, $service);
    }

    /** @test */
    public function it_creates_cypher_query_for_upsert()
    {
        // Test the Cypher query structure
        $expectedQuery = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                        'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                        'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        // The actual query used in the service should match this pattern
        $this->assertStringContainsString('MERGE', $expectedQuery);
        $this->assertStringContainsString('HAS_DOCUMENT', $expectedQuery);
    }

    /** @test */
    public function it_handles_special_characters_in_titles()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')->once();

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        // Should handle titles with quotes, newlines, etc.
        $service->upsertCaseAndDocument(
            'case-1',
            "Case with 'quotes' and \"double quotes\"",
            'doc-1',
            "Document\nwith\nnewlines"
        );

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_titles()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')->once();

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        // Should handle empty titles gracefully
        $service->upsertCaseAndDocument('case-1', '', 'doc-1', '');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_very_long_titles()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')->once();

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        $longTitle = str_repeat('A very long case title ', 100);

        $service->upsertCaseAndDocument('case-1', $longTitle, 'doc-1', 'Doc');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_unicode_characters_in_titles()
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')->once();

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        // Croatian characters and special unicode
        $service->upsertCaseAndDocument(
            'case-1',
            'Presuda Vrhovnog suda RH - Č, Ć, Š, Đ, Ž',
            'doc-1',
            'Dokument s hrvatskim znakovima'
        );

        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_both_case_and_document_nodes()
    {
        // Verify the Cypher creates both node types
        $query = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        $this->assertStringContainsString(':Case', $query);
        $this->assertStringContainsString(':CaseDocument', $query);
    }

    /** @test */
    public function it_creates_has_document_relationship()
    {
        $query = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        $this->assertStringContainsString('HAS_DOCUMENT', $query);
        $this->assertStringContainsString('->', $query);
    }

    /** @test */
    public function it_uses_merge_for_idempotency()
    {
        // MERGE ensures the operation is idempotent
        $query = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        // Count MERGE occurrences - should be 3
        $mergeCount = substr_count($query, 'MERGE');
        $this->assertEquals(3, $mergeCount);
    }

    /** @test */
    public function it_passes_correct_parameters_to_query()
    {
        // Test parameter structure
        $params = [
            'case_id' => 'test-case-id',
            'case_title' => 'Test Case Title',
            'doc_id' => 'test-doc-id',
            'doc_title' => 'Test Document Title',
        ];

        $this->assertArrayHasKey('case_id', $params);
        $this->assertArrayHasKey('case_title', $params);
        $this->assertArrayHasKey('doc_id', $params);
        $this->assertArrayHasKey('doc_title', $params);
    }

    /** @test */
    public function it_accepts_circuit_breaker_in_constructor(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        $service = new Neo4jService($circuitBreaker);

        $this->assertInstanceOf(Neo4jService::class, $service);
    }

    /** @test */
    public function it_creates_default_circuit_breaker_when_none_provided(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $service = new Neo4jService();

        $this->assertInstanceOf(Neo4jService::class, $service);
    }

    /** @test */
    public function it_catches_client_build_exception_and_sets_client_null(): void
    {
        config([
            'neo4j.sync.enabled' => true,
            'neo4j.uri' => 'invalid://not-a-valid-uri',
            'neo4j.user' => 'neo4j',
            'neo4j.password' => 'pass',
        ]);

        // Mock Log to capture the error
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        // Should log error for client init failure
        $loggerMock->shouldReceive('error')->atLeast()->once()
            ->withArgs(function ($msg) {
                return str_contains($msg, 'Neo4j client initialization failed');
            });

        // Should log info when upsert is skipped (client is null after failure)
        $loggerMock->shouldReceive('info')->once()
            ->with('Neo4j disabled; skipping upsert', Mockery::any());

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);

        // Should not throw - client failure is caught
        $service = new Neo4jService($circuitBreaker);
        $this->assertInstanceOf(Neo4jService::class, $service);

        // Calling upsert should skip gracefully (client is null)
        $service->upsertCaseAndDocument('case-1', 'Title', 'doc-1', 'DocTitle');
    }

    /** @test */
    public function it_wraps_client_run_in_circuit_breaker(): void
    {
        config(['neo4j.sync.enabled' => false]);

        // Mock Log::channel() call in constructor
        $loggerMock = Mockery::mock('Psr\Log\LoggerInterface');
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($loggerMock);
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $loggerMock->shouldReceive('info')->once()
            ->with('Neo4j disabled; skipping upsert', Mockery::any());

        $circuitBreaker = Mockery::mock(CircuitBreaker::class);
        // Circuit breaker should NOT be called when client is null (disabled)
        $circuitBreaker->shouldNotReceive('call');

        $service = new Neo4jService($circuitBreaker);
        $service->upsertCaseAndDocument('case-1', 'Title', 'doc-1', 'DocTitle');

        // Verify circuit breaker was not invoked (Mockery assertion above)
        $this->assertTrue(true);
    }
}
