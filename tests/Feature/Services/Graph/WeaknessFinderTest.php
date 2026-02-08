<?php

namespace Tests\Feature\Services\Graph;

use App\Services\Graph\ContradictionRadarService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for Weakness Finder functionality
 *
 * Tests the integration of weakness detection into the ContradictionRadarService.
 */
class WeaknessFinderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_all_weakness_types_in_single_scan(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Set up comprehensive mock responses for all 6 query types
        $mockGraphDb->shouldReceive('runQuery')
            ->times(6)
            ->andReturn(
                [['node_id' => '999', 'case_number' => 'VSRH-999', 'title' => 'Contradiction']], // contradictions
                [], // superseded
                [], // outdated
                [['cited_decision_id' => '100', 'cited_case_number' => 'Rev-100/2018', 'overruling_decision_id' => '200', 'overruling_case_number' => 'Rev-200/2022', 'overruling_reason' => 'Bad law']], // overruled
                [['cited_decision_id' => '300', 'cited_case_number' => 'Pž-300/2019', 'distinguishing_decision_id' => '400', 'distinguishing_case_number' => 'Rev-400/2023', 'distinguishing_count' => 2]], // distinguished
                [['cited_decision_id' => '500', 'cited_case_number' => 'Gž-500/2017', 'modification_count' => 1, 'latest_modifier_id' => '600', 'latest_modifier_case_number' => 'Rev-600/2021']] // weak chains
            );

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->scanNode('test-decision-123', 'Decision');

        // Should have 4 alerts total (1 contradiction + 3 weakness)
        $this->assertCount(4, $alerts);

        // Verify alert types
        $alertTypes = array_column($alerts, 'type');
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN, $alertTypes);
    }

    /** @test */
    public function weakness_alerts_have_actionable_messages(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                [
                    'cited_decision_id' => '456',
                    'cited_case_number' => 'Rev-123/2018',
                    'overruling_decision_id' => '789',
                    'overruling_case_number' => 'Rev-456/2022',
                    'overruling_reason' => null,
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->findOverruledPrecedentCitations('test-123');

        $this->assertCount(1, $alerts);

        // Message should be actionable - tells user WHAT is wrong and WHY
        $message = $alerts[0]['message'];
        $this->assertStringContainsString('Rev-123/2018', $message);
        $this->assertStringContainsString('overruled', strtolower($message));
        $this->assertStringContainsString('Rev-456/2022', $message);
    }

    /** @test */
    public function weakness_alerts_have_correct_severities(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Test overruled - should be CRITICAL
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                ['cited_decision_id' => '1', 'cited_case_number' => 'X-1', 'overruling_decision_id' => '2', 'overruling_case_number' => 'X-2', 'overruling_reason' => null],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->findOverruledPrecedentCitations('123');
        $this->assertEquals(ContradictionRadarService::SEVERITY_CRITICAL, $alerts[0]['severity']);

        // Test distinguished - should be WARNING
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                ['cited_decision_id' => '1', 'cited_case_number' => 'X-1', 'distinguishing_decision_id' => '2', 'distinguishing_case_number' => 'X-2', 'distinguishing_count' => 1],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->findDistinguishedPrecedentCitations('123');
        $this->assertEquals(ContradictionRadarService::SEVERITY_WARNING, $alerts[0]['severity']);

        // Test weak chains - should be CAUTION
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                ['cited_decision_id' => '1', 'cited_case_number' => 'X-1', 'modification_count' => 1, 'latest_modifier_id' => '2', 'latest_modifier_case_number' => 'X-2'],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->findWeakCitationChains('123');
        $this->assertEquals(ContradictionRadarService::SEVERITY_CAUTION, $alerts[0]['severity']);
    }
}
