<?php

namespace Tests\Unit\Models;

use App\Models\ResearchSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_session_with_auto_generated_uuid()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
        ]);

        $this->assertNotNull($session->uuid);
        $this->assertIsString($session->uuid);
        $this->assertEquals(36, strlen($session->uuid)); // UUID v4 length
    }

    /** @test */
    public function it_respects_provided_uuid()
    {
        $user = User::factory()->create();
        $customUuid = '12345678-1234-1234-1234-123456789012';

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'uuid' => $customUuid,
            'name' => 'Test Session',
        ]);

        $this->assertEquals($customUuid, $session->uuid);
    }

    /** @test */
    public function it_casts_viewed_nodes_as_array()
    {
        $user = User::factory()->create();
        $viewedNodes = [
            ['id' => 'node1', 'visited_at' => '2025-01-01'],
            ['id' => 'node2', 'visited_at' => '2025-01-02'],
        ];

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'viewed_nodes' => $viewedNodes,
        ]);

        $session->refresh();

        $this->assertIsArray($session->viewed_nodes);
        $this->assertEquals($viewedNodes, $session->viewed_nodes);
    }

    /** @test */
    public function it_casts_pinned_nodes_as_array()
    {
        $user = User::factory()->create();
        $pinnedNodes = [
            ['id' => 'node1', 'pinned_at' => '2025-01-01'],
        ];

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'pinned_nodes' => $pinnedNodes,
        ]);

        $session->refresh();

        $this->assertIsArray($session->pinned_nodes);
        $this->assertEquals($pinnedNodes, $session->pinned_nodes);
    }

    /** @test */
    public function it_casts_expanded_nodes_as_array()
    {
        $user = User::factory()->create();
        $expandedNodes = ['node1', 'node2', 'node3'];

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'expanded_nodes' => $expandedNodes,
        ]);

        $session->refresh();

        $this->assertIsArray($session->expanded_nodes);
        $this->assertEquals($expandedNodes, $session->expanded_nodes);
    }

    /** @test */
    public function it_casts_alerts_as_array()
    {
        $user = User::factory()->create();
        $alerts = [
            ['type' => 'warning', 'message' => 'Test alert'],
        ];

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'alerts' => $alerts,
        ]);

        $session->refresh();

        $this->assertIsArray($session->alerts);
        $this->assertEquals($alerts, $session->alerts);
    }

    /** @test */
    public function it_casts_filter_settings_as_array()
    {
        $user = User::factory()->create();
        $filterSettings = [
            'type' => 'case',
            'date_range' => ['start' => '2025-01-01', 'end' => '2025-12-31'],
        ];

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'filter_settings' => $filterSettings,
        ]);

        $session->refresh();

        $this->assertIsArray($session->filter_settings);
        $this->assertEquals($filterSettings, $session->filter_settings);
    }

    /** @test */
    public function it_casts_last_activity_at_as_datetime()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'last_activity_at' => '2025-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $session->last_activity_at);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
        ]);

        $this->assertInstanceOf(User::class, $session->user);
        $this->assertEquals($user->id, $session->user->id);
    }

    /** @test */
    public function it_filters_sessions_for_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $session1 = ResearchSession::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Session',
            'last_activity_at' => now()->subHours(2),
        ]);

        $session2 = ResearchSession::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Recent Session',
            'last_activity_at' => now()->subHour(),
        ]);

        $session3 = ResearchSession::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Session',
        ]);

        $user1Sessions = ResearchSession::forUser($user1->id)->get();

        $this->assertCount(2, $user1Sessions);
        $this->assertEquals($session2->id, $user1Sessions->first()->id); // Most recent first
        $this->assertEquals($session1->id, $user1Sessions->last()->id);
    }

    /** @test */
    public function it_orders_active_sessions_by_last_activity()
    {
        $user = User::factory()->create();

        $oldSession = ResearchSession::create([
            'user_id' => $user->id,
            'last_activity_at' => now()->subDays(5),
        ]);

        $recentSession = ResearchSession::create([
            'user_id' => $user->id,
            'last_activity_at' => now()->subDay(),
        ]);

        $activeSessions = ResearchSession::active()->get();

        $this->assertEquals($recentSession->id, $activeSessions->first()->id);
    }

    /** @test */
    public function it_filters_named_sessions()
    {
        $user = User::factory()->create();

        $namedSession = ResearchSession::create([
            'user_id' => $user->id,
            'name' => 'Named Session',
        ]);

        $unnamedSession = ResearchSession::create([
            'user_id' => $user->id,
            'name' => null,
        ]);

        $namedSessions = ResearchSession::named()->get();

        $this->assertCount(1, $namedSessions);
        $this->assertEquals($namedSession->id, $namedSessions->first()->id);
    }

    /** @test */
    public function it_returns_display_name_when_name_exists()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'name' => 'My Research Session',
        ]);

        $this->assertEquals('My Research Session', $session->getDisplayName());
    }

    /** @test */
    public function it_generates_display_name_from_created_at_when_no_name()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
        ]);

        $expectedName = 'Session ' . $session->created_at->format('M d, Y H:i');
        $this->assertEquals($expectedName, $session->getDisplayName());
    }

    /** @test */
    public function it_extracts_viewed_node_ids()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'viewed_nodes' => [
                ['id' => 'node1', 'visited_at' => '2025-01-01'],
                ['id' => 'node2', 'visited_at' => '2025-01-02'],
                ['id' => 'node3', 'visited_at' => '2025-01-03'],
            ],
        ]);

        $nodeIds = $session->getViewedNodeIds();

        $this->assertEquals(['node1', 'node2', 'node3'], $nodeIds);
    }

    /** @test */
    public function it_returns_empty_array_for_empty_viewed_nodes()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'viewed_nodes' => [],
        ]);

        $this->assertEquals([], $session->getViewedNodeIds());
    }

    /** @test */
    public function it_extracts_pinned_node_ids()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'pinned_nodes' => [
                ['id' => 'node1', 'pinned_at' => '2025-01-01'],
                ['id' => 'node2', 'pinned_at' => '2025-01-02'],
            ],
        ]);

        $nodeIds = $session->getPinnedNodeIds();

        $this->assertEquals(['node1', 'node2'], $nodeIds);
    }

    /** @test */
    public function it_returns_empty_array_for_empty_pinned_nodes()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'pinned_nodes' => [],
        ]);

        $this->assertEquals([], $session->getPinnedNodeIds());
    }

    /** @test */
    public function it_counts_viewed_nodes()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'viewed_nodes' => [
                ['id' => 'node1'],
                ['id' => 'node2'],
                ['id' => 'node3'],
            ],
        ]);

        $this->assertEquals(3, $session->getNodeCount());
    }

    /** @test */
    public function it_returns_zero_count_for_empty_viewed_nodes()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'viewed_nodes' => [],
        ]);

        $this->assertEquals(0, $session->getNodeCount());
    }

    /** @test */
    public function it_supports_soft_deletes()
    {
        $user = User::factory()->create();

        $session = ResearchSession::create([
            'user_id' => $user->id,
            'name' => 'Test Session',
        ]);

        $sessionId = $session->id;

        $session->delete();

        $this->assertSoftDeleted('research_sessions', ['id' => $sessionId]);
        $this->assertNotNull($session->fresh()?->deleted_at);
    }
}
