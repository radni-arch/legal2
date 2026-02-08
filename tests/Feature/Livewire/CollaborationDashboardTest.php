<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\CollaborationDashboard;
use App\Models\AgentCollaboration;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CollaborationDashboardTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(CollaborationDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.collaboration-dashboard')
            ->assertViewHas('collaborations')
            ->assertViewHas('stats')
            ->assertSet('selectedCollaboration', null)
            ->assertSet('showDetails', false);
    }

    /**
     * Test 2: Dashboard displays empty state when no collaborations exist
     *
     * @test
     */
    public function test_dashboard_displays_empty_state()
    {
        $component = Livewire::test(CollaborationDashboard::class);

        $stats = $component->viewData('stats');

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['completed']);
        $this->assertEquals(0, $stats['in_progress']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['recent_7_days']);
    }

    /**
     * Test 3: Dashboard displays collaborations with pagination
     *
     * @test
     */
    public function test_dashboard_displays_collaborations_with_pagination()
    {
        // Create 25 collaborations to test pagination (20 per page)
        AgentCollaboration::factory()->count(25)->create();

        $component = Livewire::test(CollaborationDashboard::class);

        $collaborations = $component->viewData('collaborations');

        $this->assertCount(20, $collaborations); // Should paginate to 20
        $this->assertEquals(25, $collaborations->total());
    }

    /**
     * Test 4: Statistics are calculated correctly
     *
     * @test
     */
    public function test_statistics_are_calculated_correctly()
    {
        // Create collaborations with different statuses
        AgentCollaboration::factory()->count(5)->create(['status' => 'completed']);
        AgentCollaboration::factory()->count(3)->create(['status' => 'in_progress']);
        AgentCollaboration::factory()->count(2)->create(['status' => 'failed']);

        $component = Livewire::test(CollaborationDashboard::class);

        $stats = $component->viewData('stats');

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(5, $stats['completed']);
        $this->assertEquals(3, $stats['in_progress']);
        $this->assertEquals(2, $stats['failed']);
    }

    /**
     * Test 5: Recent 7 days filter works correctly
     *
     * @test
     */
    public function test_recent_7_days_filter_works()
    {
        // Create recent collaborations
        AgentCollaboration::factory()->count(3)->create([
            'started_at' => now()->subDays(3),
        ]);

        // Create old collaborations
        AgentCollaboration::factory()->count(2)->create([
            'started_at' => now()->subDays(10),
        ]);

        $component = Livewire::test(CollaborationDashboard::class);

        $stats = $component->viewData('stats');

        $this->assertEquals(3, $stats['recent_7_days']);
    }

    /**
     * Test 6: View details method works
     *
     * @test
     */
    public function test_view_details_method_works()
    {
        $collaboration = AgentCollaboration::factory()->create();

        Livewire::test(CollaborationDashboard::class)
            ->assertSet('showDetails', false)
            ->assertSet('selectedCollaboration', null)
            ->call('viewDetails', $collaboration->id)
            ->assertSet('showDetails', true)
            ->assertSet('selectedCollaboration.id', $collaboration->id);
    }

    /**
     * Test 7: Close details method works
     *
     * @test
     */
    public function test_close_details_method_works()
    {
        $collaboration = AgentCollaboration::factory()->create();

        Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', $collaboration->id)
            ->assertSet('showDetails', true)
            ->call('closeDetails')
            ->assertSet('showDetails', false)
            ->assertSet('selectedCollaboration', null);
    }

    /**
     * Test 8: Collaborations are ordered by started_at descending
     *
     * @test
     */
    public function test_collaborations_are_ordered_by_started_at_descending()
    {
        $oldCollab = AgentCollaboration::factory()->create(['started_at' => now()->subDays(10)]);
        $middleCollab = AgentCollaboration::factory()->create(['started_at' => now()->subDays(5)]);
        $recentCollab = AgentCollaboration::factory()->create(['started_at' => now()->subDay()]);

        $component = Livewire::test(CollaborationDashboard::class);

        $collaborations = $component->viewData('collaborations');

        $this->assertEquals($recentCollab->id, $collaborations->first()->id);
        $this->assertEquals($oldCollab->id, $collaborations->last()->id);
    }

    /**
     * Test 9: Collaborations are loaded with executions relationship
     *
     * @test
     */
    public function test_collaborations_loaded_with_executions()
    {
        $collaboration = AgentCollaboration::factory()
            ->hasExecutions(3)
            ->create();

        $component = Livewire::test(CollaborationDashboard::class);

        $collaborations = $component->viewData('collaborations');
        $firstCollaboration = $collaborations->first();

        $this->assertTrue($firstCollaboration->relationLoaded('executions'));
        $this->assertCount(3, $firstCollaboration->executions);
    }

    /**
     * Test 10: View details loads collaboration with executions
     *
     * @test
     */
    public function test_view_details_loads_with_executions()
    {
        $collaboration = AgentCollaboration::factory()
            ->hasExecutions(5)
            ->create();

        $component = Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', $collaboration->id);

        $selectedCollaboration = $component->get('selectedCollaboration');

        $this->assertNotNull($selectedCollaboration);
        $this->assertTrue($selectedCollaboration->relationLoaded('executions'));
        $this->assertCount(5, $selectedCollaboration->executions);
    }

    /**
     * Test 11: Average duration is calculated correctly
     *
     * @test
     */
    public function test_average_duration_calculated_correctly()
    {
        AgentCollaboration::factory()->create([
            'status' => 'completed',
            'duration_seconds' => 100,
        ]);

        AgentCollaboration::factory()->create([
            'status' => 'completed',
            'duration_seconds' => 200,
        ]);

        AgentCollaboration::factory()->create([
            'status' => 'completed',
            'duration_seconds' => 300,
        ]);

        $component = Livewire::test(CollaborationDashboard::class);

        $stats = $component->viewData('stats');

        $this->assertEquals(200, $stats['avg_duration']); // (100 + 200 + 300) / 3
    }

    /**
     * Test 12: Total tokens and cost are summed correctly
     *
     * @test
     */
    public function test_total_tokens_and_cost_summed_correctly()
    {
        AgentCollaboration::factory()->create([
            'tokens_used' => 1000,
            'cost_spent' => 0.50,
        ]);

        AgentCollaboration::factory()->create([
            'tokens_used' => 2000,
            'cost_spent' => 1.00,
        ]);

        AgentCollaboration::factory()->create([
            'tokens_used' => 3000,
            'cost_spent' => 1.50,
        ]);

        $component = Livewire::test(CollaborationDashboard::class);

        $stats = $component->viewData('stats');

        $this->assertEquals(6000, $stats['total_tokens']);
        $this->assertEquals(3.00, $stats['total_cost']);
    }

    /**
     * Test 13: Listener for refreshCollaborations is registered
     *
     * @test
     */
    public function test_listener_for_refresh_collaborations_registered()
    {
        $component = Livewire::test(CollaborationDashboard::class);

        $listeners = $component->instance()->getEventsBeingListenedFor();

        $this->assertContains('refreshCollaborations', $listeners);
    }
}
