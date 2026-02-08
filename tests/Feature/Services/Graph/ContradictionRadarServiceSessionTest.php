<?php

namespace Tests\Feature\Services\Graph;

use App\Models\ResearchSession;
use App\Models\User;
use App\Services\Graph\ContradictionRadarService;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContradictionRadarServiceSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_adds_alerts_to_session(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'alerts' => [],
        ]);

        $alerts = [
            [
                'id' => 'alert-1',
                'type' => 'direct_contradiction',
                'severity' => 'critical',
                'source_node_id' => '123',
                'related_node_id' => '456',
                'message' => 'Test alert',
                'dismissed' => false,
                'created_at' => now()->toISOString(),
            ],
        ];

        $service->addAlertsToSession($session, $alerts);

        $session->refresh();
        $this->assertCount(1, $session->alerts);
        $this->assertEquals('alert-1', $session->alerts[0]['id']);
    }

    /** @test */
    public function it_dismisses_alert_by_id(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'alerts' => [
                ['id' => 'alert-1', 'dismissed' => false, 'type' => 'direct_contradiction'],
                ['id' => 'alert-2', 'dismissed' => false, 'type' => 'superseded_law'],
            ],
        ]);

        $service->dismissAlert($session, 'alert-1');

        $session->refresh();
        $this->assertTrue($session->alerts[0]['dismissed']);
        $this->assertFalse($session->alerts[1]['dismissed']);
    }

    /** @test */
    public function it_skips_duplicate_alerts(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'alerts' => [
                [
                    'id' => 'existing',
                    'source_node_id' => '123',
                    'related_node_id' => '456',
                    'type' => 'direct_contradiction',
                    'dismissed' => false,
                ],
            ],
        ]);

        $newAlerts = [
            [
                'id' => 'new-1',
                'source_node_id' => '123',
                'related_node_id' => '456',
                'type' => 'direct_contradiction',
                'dismissed' => false,
            ], // duplicate (same signature)
            [
                'id' => 'new-2',
                'source_node_id' => '123',
                'related_node_id' => '789',
                'type' => 'direct_contradiction',
                'dismissed' => false,
            ], // new
        ];

        $service->addAlertsToSession($session, $newAlerts);

        $session->refresh();
        // Should have: existing + new-2, not new-1 (duplicate)
        $this->assertCount(2, $session->alerts);
    }

    /** @test */
    public function it_returns_undismissed_alerts(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'alerts' => [
                ['id' => 'alert-1', 'dismissed' => false, 'type' => 'direct_contradiction'],
                ['id' => 'alert-2', 'dismissed' => true, 'type' => 'superseded_law'],
                ['id' => 'alert-3', 'dismissed' => false, 'type' => 'outdated_citation'],
            ],
        ]);

        $undismissed = $service->getActiveAlerts($session);

        $this->assertCount(2, $undismissed);
        $this->assertEquals('alert-1', $undismissed[0]['id']);
        $this->assertEquals('alert-3', $undismissed[1]['id']);
    }

    /** @test */
    public function it_scans_all_session_nodes_on_demand(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Expect 6 queries: 3 per pinned node × 2 pinned Decision nodes
        $mockGraphDb->shouldReceive('runQuery')
            ->times(6)
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'pinned_nodes' => [
                ['id' => '123', 'type' => 'Decision'],
                ['id' => '456', 'type' => 'Decision'],
            ],
            'alerts' => [],
        ]);

        $alerts = $service->scanSession($session);

        $this->assertIsArray($alerts);
        $this->assertEmpty($alerts); // No alerts since queries return empty
    }

    /** @test */
    public function it_scans_session_with_mixed_node_types(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Decision: 3 queries, Law: 1 query (only contradiction check)
        $mockGraphDb->shouldReceive('runQuery')
            ->times(4)
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'pinned_nodes' => [
                ['id' => '123', 'type' => 'Decision'],
                ['id' => '456', 'type' => 'Law'],
            ],
            'alerts' => [],
        ]);

        $alerts = $service->scanSession($session);

        $this->assertIsArray($alerts);
    }

    /** @test */
    public function it_scans_session_and_finds_alerts(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Return a contradiction for the first call
        $mockGraphDb->shouldReceive('runQuery')
            ->times(3)
            ->andReturn(
                [['node_id' => '999', 'case_number' => 'VSRH-999', 'title' => 'Test']], // contradiction found
                [], // no superseded
                [] // no outdated
            );

        $service = new ContradictionRadarService($mockGraphDb);

        $user = User::factory()->create();
        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'pinned_nodes' => [
                ['id' => '123', 'type' => 'Decision'],
            ],
            'alerts' => [],
        ]);

        $alerts = $service->scanSession($session);

        $this->assertCount(1, $alerts);
        $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alerts[0]['type']);

        // Verify alerts were added to session
        $session->refresh();
        $this->assertCount(1, $session->alerts);
    }
}
