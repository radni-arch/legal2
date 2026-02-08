<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\ContradictionRadarService;
use App\Services\GraphDatabaseService;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ContradictionRadarServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_finds_direct_contradictions_for_node(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'node_id' => '456',
                    'case_number' => 'VSRH-456/2022',
                    'title' => 'Contradicting Decision',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDirectContradictions('123');

        $this->assertCount(1, $alerts);
        $this->assertArrayHasKey('id', $alerts[0]);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_CRITICAL, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('456', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('VSRH-456/2022', $alerts[0]['message']);
        $this->assertFalse($alerts[0]['dismissed']);
        $this->assertArrayHasKey('created_at', $alerts[0]);
    }

    /** @test */
    public function it_returns_empty_array_when_no_contradictions(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDirectContradictions('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_generates_deterministic_alert_ids(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->twice()
            ->andReturn([
                [
                    'node_id' => '456',
                    'case_number' => 'VSRH-456/2022',
                    'title' => 'Contradicting Decision',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts1 = $service->findDirectContradictions('123');
        $alerts2 = $service->findDirectContradictions('123');

        // Same inputs should produce same alert ID
        $this->assertEquals($alerts1[0]['id'], $alerts2[0]['id']);

        // ID should be a valid SHA-256 hash (64 hex characters)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $alerts1[0]['id']);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findDirectContradictions('');
    }

    /** @test */
    public function it_throws_exception_for_whitespace_only_node_id(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findDirectContradictions('   ');
    }

    /** @test */
    public function it_wraps_database_exceptions(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to query contradictions for node 123');

        $service->findDirectContradictions('123');
    }

    /** @test */
    public function it_handles_multiple_contradictions(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                [
                    'node_id' => '456',
                    'case_number' => 'VSRH-456/2022',
                    'title' => 'First Contradiction',
                ],
                [
                    'node_id' => '789',
                    'case_number' => 'VSRH-789/2023',
                    'title' => 'Second Contradiction',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDirectContradictions('123');

        $this->assertCount(2, $alerts);

        // Each alert should have unique ID
        $this->assertNotEquals($alerts[0]['id'], $alerts[1]['id']);

        // Verify both alerts have correct structure
        foreach ($alerts as $alert) {
            $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alert['type']);
            $this->assertEquals(ContradictionRadarService::SEVERITY_CRITICAL, $alert['severity']);
            $this->assertEquals('123', $alert['source_node_id']);
        }
    }

    /** @test */
    public function it_has_weakness_alert_type_constants(): void
    {
        $this->assertEquals('overruled_precedent', ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT);
        $this->assertEquals('distinguished_precedent', ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT);
        $this->assertEquals('weak_citation_chain', ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN);
    }

    // ========================================
    // Superseded Law Citation Tests
    // ========================================

    /** @test */
    public function it_finds_superseded_law_citations(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'old_law_id' => '100',
                    'old_law_number' => 'ZKP Art. 9 (2018)',
                    'new_law_id' => '200',
                    'new_law_number' => 'ZKP Art. 9 (2021)',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findSupersededLawCitations('123');

        $this->assertCount(1, $alerts);
        $this->assertArrayHasKey('id', $alerts[0]);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_SUPERSEDED_LAW, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_CAUTION, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('100', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('superseded', strtolower($alerts[0]['message']));
        $this->assertStringContainsString('ZKP Art. 9 (2018)', $alerts[0]['message']);
        $this->assertStringContainsString('ZKP Art. 9 (2021)', $alerts[0]['message']);
        $this->assertFalse($alerts[0]['dismissed']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_superseded_laws(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findSupersededLawCitations('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_superseded_law_check(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findSupersededLawCitations('');
    }

    // ========================================
    // Outdated Citation Tests
    // ========================================

    /** @test */
    public function it_finds_outdated_citations(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'law_id' => '100',
                    'law_number' => 'ZKP Art. 15',
                    'valid_until' => '2020-01-01',
                    'decision_date' => '2022-05-15',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findOutdatedCitations('123');

        $this->assertCount(1, $alerts);
        $this->assertArrayHasKey('id', $alerts[0]);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_OUTDATED_CITATION, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_CAUTION, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('100', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('valid until', strtolower($alerts[0]['message']));
        $this->assertStringContainsString('ZKP Art. 15', $alerts[0]['message']);
        $this->assertStringContainsString('2020-01-01', $alerts[0]['message']);
        $this->assertFalse($alerts[0]['dismissed']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_outdated_citations(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findOutdatedCitations('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_outdated_citation_check(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findOutdatedCitations('');
    }

    // ========================================
    // scanNode Orchestration Tests
    // ========================================

    /** @test */
    public function it_scans_node_and_combines_all_alerts(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Mock six separate queries for Decision node (3 existing + 3 weakness)
        $mockGraphDb->shouldReceive('runQuery')
            ->times(6)
            ->andReturn(
                [['node_id' => '456', 'case_number' => 'VSRH-456', 'title' => 'Test']], // contradictions
                [['old_law_id' => '100', 'old_law_number' => 'ZKP 9', 'new_law_id' => '200', 'new_law_number' => 'ZKP 9 (new)']], // superseded
                [], // no outdated
                [], // no overruled
                [['cited_decision_id' => '789', 'cited_case_number' => 'Pž-1/2020', 'distinguishing_decision_id' => '999', 'distinguishing_case_number' => 'Rev-1/2023', 'distinguishing_count' => 2]], // distinguished
                []  // no weak chains
            );

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->scanNode('123', 'Decision');

        $this->assertCount(3, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_SUPERSEDED_LAW, $alerts[1]['type']);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $alerts[2]['type']);
    }

    /** @test */
    public function it_only_checks_contradiction_for_non_decision_nodes(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Only expect contradiction query for non-Decision nodes
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->scanNode('123', 'Law');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_scan(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->scanNode('', 'Decision');
    }

    /** @test */
    public function it_throws_exception_for_empty_node_type_in_scan(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node type cannot be empty');

        $service->scanNode('123', '');
    }

    // ========================================
    // Overruled Precedent Tests
    // ========================================

    /** @test */
    public function it_finds_overruled_precedent_citations(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'cited_decision_id' => '456',
                    'cited_case_number' => 'Rev-123/2018',
                    'overruling_decision_id' => '789',
                    'overruling_case_number' => 'Rev-456/2022',
                    'overruling_reason' => 'Outdated interpretation',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findOverruledPrecedentCitations('123');

        $this->assertCount(1, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_CRITICAL, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('456', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('Rev-123/2018', $alerts[0]['message']);
        $this->assertStringContainsString('overruled', strtolower($alerts[0]['message']));
        $this->assertFalse($alerts[0]['dismissed']);
        // Verify metadata
        $this->assertArrayHasKey('metadata', $alerts[0]);
        $this->assertEquals('789', $alerts[0]['metadata']['overruling_decision_id']);
        $this->assertEquals('Rev-456/2022', $alerts[0]['metadata']['overruling_case_number']);
        $this->assertEquals('Outdated interpretation', $alerts[0]['metadata']['reason']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_overruled_precedents(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findOverruledPrecedentCitations('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_overruled_check(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findOverruledPrecedentCitations('');
    }

    // ========================================
    // Distinguished Precedent Tests
    // ========================================

    /** @test */
    public function it_finds_distinguished_precedent_citations(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'cited_decision_id' => '456',
                    'cited_case_number' => 'Pž-789/2019',
                    'distinguishing_decision_id' => '999',
                    'distinguishing_case_number' => 'Rev-100/2023',
                    'distinguishing_count' => 3,
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDistinguishedPrecedentCitations('123');

        $this->assertCount(1, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_WARNING, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('456', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('Pž-789/2019', $alerts[0]['message']);
        $this->assertStringContainsString('distinguished', strtolower($alerts[0]['message']));
        // Verify metadata
        $this->assertArrayHasKey('metadata', $alerts[0]);
        $this->assertEquals('999', $alerts[0]['metadata']['distinguishing_decision_id']);
        $this->assertEquals(3, $alerts[0]['metadata']['distinguishing_count']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_distinguished_precedents(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDistinguishedPrecedentCitations('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_distinguished_check(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findDistinguishedPrecedentCitations('');
    }

    // ========================================
    // Weak Citation Chain Tests
    // ========================================

    /** @test */
    public function it_finds_weak_citation_chains(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->with(Mockery::type('string'), ['nodeId' => '123'])
            ->andReturn([
                [
                    'cited_decision_id' => '456',
                    'cited_case_number' => 'Gž-111/2017',
                    'modification_count' => 2,
                    'latest_modifier_id' => '888',
                    'latest_modifier_case_number' => 'Rev-222/2021',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findWeakCitationChains('123');

        $this->assertCount(1, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN, $alerts[0]['type']);
        $this->assertEquals(ContradictionRadarService::SEVERITY_CAUTION, $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('456', $alerts[0]['related_node_id']);
        $this->assertStringContainsString('Gž-111/2017', $alerts[0]['message']);
        $this->assertStringContainsString('modified', strtolower($alerts[0]['message']));
        // Verify metadata
        $this->assertArrayHasKey('metadata', $alerts[0]);
        $this->assertEquals(2, $alerts[0]['metadata']['modification_count']);
        $this->assertEquals('888', $alerts[0]['metadata']['latest_modifier_id']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_weak_chains(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findWeakCitationChains('123');

        $this->assertEmpty($alerts);
    }

    /** @test */
    public function it_throws_exception_for_empty_node_id_in_weak_chain_check(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node ID cannot be empty');

        $service->findWeakCitationChains('');
    }

    /** @test */
    public function it_scans_node_and_includes_weakness_alerts(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Mock six queries for Decision node (3 existing + 3 weakness)
        $mockGraphDb->shouldReceive('runQuery')
            ->times(6)
            ->andReturn(
                [], // contradictions
                [], // superseded
                [], // outdated
                [['cited_decision_id' => '456', 'cited_case_number' => 'Rev-1/2020', 'overruling_decision_id' => '789', 'overruling_case_number' => 'Rev-2/2023', 'overruling_reason' => null]], // overruled
                [], // distinguished
                []  // weak chains
            );

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->scanNode('123', 'Decision');

        $this->assertCount(1, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alerts[0]['type']);
    }
}
