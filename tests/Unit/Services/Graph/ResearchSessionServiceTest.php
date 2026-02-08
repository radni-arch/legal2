<?php

namespace Tests\Unit\Services\Graph;

use App\Models\ResearchSession;
use App\Models\User;
use App\Services\Graph\ResearchSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ResearchSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResearchSessionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResearchSessionService();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    /** @test */
    public function it_creates_new_session_if_none_exists()
    {
        $session = $this->service->getCurrentSession();

        $this->assertInstanceOf(ResearchSession::class, $session);
        $this->assertEquals($this->user->id, $session->user_id);
        $this->assertNull($session->name);
        $this->assertNotNull($session->last_activity_at);
    }

    /** @test */
    public function it_reuses_recent_unnamed_session_within_30_minutes()
    {
        // Create a recent unnamed session
        $existingSession = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => now()->subMinutes(15),
        ]);

        $session = $this->service->getCurrentSession();

        $this->assertEquals($existingSession->id, $session->id);
    }

    /** @test */
    public function it_creates_new_session_if_last_activity_older_than_30_minutes()
    {
        // Create an old unnamed session
        $oldSession = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => now()->subMinutes(35),
        ]);

        $session = $this->service->getCurrentSession();

        $this->assertNotEquals($oldSession->id, $session->id);
        $this->assertEquals($this->user->id, $session->user_id);
    }

    /** @test */
    public function it_does_not_reuse_named_sessions()
    {
        // Create a recent named session (should not be reused)
        ResearchSession::create([
            'user_id' => $this->user->id,
            'name' => 'My Research',
            'last_activity_at' => now()->subMinutes(5),
        ]);

        $session = $this->service->getCurrentSession();

        $this->assertNull($session->name);
    }

    /** @test */
    public function it_caches_current_session_in_memory()
    {
        $session1 = $this->service->getCurrentSession();
        $session2 = $this->service->getCurrentSession();

        $this->assertSame($session1, $session2);
    }

    /** @test */
    public function it_sets_current_session_by_id()
    {
        $session = ResearchSession::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $result = $this->service->setCurrentSession($session->id);

        $this->assertInstanceOf(ResearchSession::class, $result);
        $this->assertEquals($session->id, $result->id);
    }

    /** @test */
    public function it_returns_null_when_setting_nonexistent_session()
    {
        $result = $this->service->setCurrentSession(99999);

        $this->assertNull($result);
    }

    /** @test */
    public function it_tracks_viewed_node()
    {
        $node = [
            'id' => 'node-123',
            'type' => 'Case',
            'properties' => ['title' => 'Test Case'],
        ];

        $this->service->trackViewedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertCount(1, $session->viewed_nodes);
        $this->assertEquals('node-123', $session->viewed_nodes[0]['id']);
        $this->assertEquals('Case', $session->viewed_nodes[0]['type']);
        $this->assertEquals('Test Case', $session->viewed_nodes[0]['label']);
        $this->assertArrayHasKey('viewed_at', $session->viewed_nodes[0]);
    }

    /** @test */
    public function it_prevents_duplicate_viewed_nodes()
    {
        $node = [
            'id' => 'node-123',
            'type' => 'Case',
            'properties' => ['title' => 'Test Case'],
        ];

        $this->service->trackViewedNode($node);
        $this->service->trackViewedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertCount(1, $session->viewed_nodes);
    }

    /** @test */
    public function it_extracts_label_from_case_number_if_no_title()
    {
        $node = [
            'id' => 'node-456',
            'type' => 'Decision',
            'properties' => ['case_number' => 'ABC-2024-001'],
        ];

        $this->service->trackViewedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertEquals('ABC-2024-001', $session->viewed_nodes[0]['label']);
    }

    /** @test */
    public function it_uses_node_id_as_label_if_no_title_or_case_number()
    {
        $node = [
            'id' => 'node-789',
            'type' => 'Entity',
            'properties' => [],
        ];

        $this->service->trackViewedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertEquals('node-789', $session->viewed_nodes[0]['label']);
    }

    /** @test */
    public function it_updates_last_activity_when_tracking_viewed_node()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);

        $node = ['id' => 'node-1', 'properties' => []];
        $this->service->trackViewedNode($node);

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_tracks_pinned_node()
    {
        $node = [
            'id' => 'node-pin-1',
            'type' => 'Law',
            'properties' => ['title' => 'Important Statute'],
        ];

        $this->service->trackPinnedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertCount(1, $session->pinned_nodes);
        $this->assertEquals('node-pin-1', $session->pinned_nodes[0]['id']);
        $this->assertEquals('Law', $session->pinned_nodes[0]['type']);
        $this->assertEquals('Important Statute', $session->pinned_nodes[0]['label']);
        $this->assertArrayHasKey('pinned_at', $session->pinned_nodes[0]);
    }

    /** @test */
    public function it_prevents_duplicate_pinned_nodes()
    {
        $node = [
            'id' => 'node-pin-2',
            'type' => 'Case',
            'properties' => ['title' => 'Pinned Case'],
        ];

        $this->service->trackPinnedNode($node);
        $this->service->trackPinnedNode($node);

        $session = $this->service->getCurrentSession();
        $this->assertCount(1, $session->pinned_nodes);
    }

    /** @test */
    public function it_unpins_node()
    {
        $node1 = ['id' => 'pin-1', 'properties' => ['title' => 'Node 1']];
        $node2 = ['id' => 'pin-2', 'properties' => ['title' => 'Node 2']];

        $this->service->trackPinnedNode($node1);
        $this->service->trackPinnedNode($node2);

        $session = $this->service->getCurrentSession();
        $this->assertCount(2, $session->pinned_nodes);

        $this->service->unpinNode('pin-1');

        $session->refresh();
        $this->assertCount(1, $session->pinned_nodes);
        $this->assertEquals('pin-2', $session->pinned_nodes[0]['id']);
    }

    /** @test */
    public function it_updates_last_activity_when_unpinning()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'pinned_nodes' => [
                ['id' => 'pin-1', 'label' => 'Node 1', 'pinned_at' => now()->toISOString()],
            ],
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);
        $this->service->unpinNode('pin-1');

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_tracks_expanded_node()
    {
        $this->service->trackExpandedNode('expand-1');

        $session = $this->service->getCurrentSession();
        $this->assertContains('expand-1', $session->expanded_nodes);
    }

    /** @test */
    public function it_prevents_duplicate_expanded_nodes()
    {
        $this->service->trackExpandedNode('expand-1');
        $this->service->trackExpandedNode('expand-1');

        $session = $this->service->getCurrentSession();
        $this->assertCount(1, $session->expanded_nodes);
    }

    /** @test */
    public function it_updates_last_activity_when_tracking_expanded_node()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);
        $this->service->trackExpandedNode('expand-1');

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_saves_session_with_name()
    {
        $session = $this->service->getCurrentSession();
        $this->assertNull($session->name);

        $result = $this->service->saveSession('My Research', 'Testing citation networks');

        $this->assertEquals('My Research', $result->name);
        $this->assertEquals('Testing citation networks', $result->description);
        $session->refresh();
        $this->assertEquals('My Research', $session->name);
    }

    /** @test */
    public function it_saves_session_without_description()
    {
        $result = $this->service->saveSession('Quick Session');

        $this->assertEquals('Quick Session', $result->name);
        $this->assertNull($result->description);
    }

    /** @test */
    public function it_updates_last_activity_when_saving_session()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);
        $this->service->saveSession('Named Session');

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_gets_saved_sessions_for_authenticated_user()
    {
        // Create named sessions
        ResearchSession::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Session 1',
            'last_activity_at' => now()->subDay(),
        ]);
        ResearchSession::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Session 2',
            'last_activity_at' => now(),
        ]);

        // Create unnamed session (should not be returned)
        ResearchSession::factory()->create([
            'user_id' => $this->user->id,
            'name' => null,
        ]);

        // Create session for different user
        $otherUser = User::factory()->create();
        ResearchSession::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Session',
        ]);

        $sessions = $this->service->getSavedSessions();

        $this->assertCount(2, $sessions);
        $this->assertEquals('Session 2', $sessions[0]['name']); // Most recent first
    }

    /** @test */
    public function it_gets_saved_sessions_for_specific_user()
    {
        $otherUser = User::factory()->create();
        ResearchSession::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Session',
        ]);

        $sessions = $this->service->getSavedSessions($otherUser->id);

        $this->assertCount(1, $sessions);
        $this->assertEquals('Other User Session', $sessions[0]['name']);
    }

    /** @test */
    public function it_limits_saved_sessions_to_20()
    {
        // Create 25 named sessions
        for ($i = 1; $i <= 25; $i++) {
            ResearchSession::factory()->create([
                'user_id' => $this->user->id,
                'name' => "Session $i",
                'last_activity_at' => now()->subDays($i),
            ]);
        }

        $sessions = $this->service->getSavedSessions();

        $this->assertCount(20, $sessions);
    }

    /** @test */
    public function it_updates_filter_settings()
    {
        $settings = [
            'nodeTypes' => ['Case', 'Law'],
            'relationshipTypes' => ['CITES', 'REFERENCES'],
            'dateRange' => ['start' => '2020-01-01', 'end' => '2024-12-31'],
        ];

        $this->service->updateFilterSettings($settings);

        $session = $this->service->getCurrentSession();
        $this->assertEquals($settings, $session->filter_settings);
    }

    /** @test */
    public function it_updates_last_activity_when_updating_filter_settings()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);
        $this->service->updateFilterSettings(['test' => 'value']);

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_sets_root_node()
    {
        $this->service->setRootNode('root-node-123');

        $session = $this->service->getCurrentSession();
        $this->assertEquals('root-node-123', $session->root_node_id);
    }

    /** @test */
    public function it_updates_last_activity_when_setting_root_node()
    {
        $originalTime = now()->subMinutes(10);
        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => $originalTime,
        ]);

        $this->service->setCurrentSession($session->id);
        $this->service->setRootNode('root-123');

        $session->refresh();
        $this->assertTrue($session->last_activity_at->isAfter($originalTime));
    }

    /** @test */
    public function it_throws_exception_when_getting_session_for_unauthenticated_user()
    {
        Auth::logout();

        $service = new ResearchSessionService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User must be authenticated');
        $service->getCurrentSession();
    }

    /** @test */
    public function it_prevents_accessing_other_users_sessions()
    {
        $otherUser = User::factory()->create();
        $otherSession = ResearchSession::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        // Should return null, not the other user's session
        $result = $this->service->setCurrentSession($otherSession->id);

        $this->assertNull($result);
    }
}
