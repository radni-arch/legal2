<?php

namespace Tests\Feature\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Models\ResearchSession;
use App\Models\User;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ResearchSessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_tracks_viewed_nodes_for_authenticated_users()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')->andReturn([]);
        $graphMock->shouldReceive('getEvidenceForDecision')->andReturn([]);
        $graphMock->shouldReceive('getDateEventsForDecision')->andReturn([]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->dispatch('node-selected', node: ['id' => 'test-node', 'type' => 'Decision']);

        $session = ResearchSession::where('user_id', $this->user->id)->first();

        $this->assertNotNull($session);
        $this->assertCount(1, $session->viewed_nodes);
        $this->assertEquals('test-node', $session->viewed_nodes[0]['id']);
    }

    /** @test */
    public function it_saves_session_with_name()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->call('saveSession', 'My Research', 'About contracts')
            ->assertDispatched('session-saved');

        $session = ResearchSession::where('user_id', $this->user->id)
            ->where('name', 'My Research')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals('About contracts', $session->description);
    }

    /** @test */
    public function it_loads_saved_session()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn([
            'nodes' => [['id' => 'root-node', 'type' => 'Decision']],
            'edges' => [],
        ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'name' => 'Saved Session',
            'root_node_id' => 'root-node',
            'pinned_nodes' => [['id' => 'pinned-1', 'type' => 'Law']],
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->call('loadSession', $session->id)
            ->assertSet('sessionId', $session->id)
            ->assertDispatched('session-loaded');
    }

    /** @test */
    public function it_does_not_track_for_guests()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')->andReturn([]);
        $graphMock->shouldReceive('getEvidenceForDecision')->andReturn([]);
        $graphMock->shouldReceive('getDateEventsForDecision')->andReturn([]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        // No actingAs - guest user

        Livewire::test(ForceGraphController::class)
            ->dispatch('node-selected', node: ['id' => 'test-node', 'type' => 'Decision']);

        $this->assertEquals(0, ResearchSession::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
