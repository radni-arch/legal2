<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AgentPerformanceDashboard;
use App\Models\LearningOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD Tests for AgentPerformanceDashboard Livewire Component
 *
 * Sprint 5.5: Agent Performance Dashboards
 *
 * Tests dashboard component for displaying agent performance metrics,
 * time-series charts, comparison charts, and filtering capabilities.
 *
 * Following strict TDD: Tests written FIRST, will fail until implementation.
 */
class AgentPerformanceDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(AgentPerformanceDashboard::class)
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_total_runs_metric()
    {
        $this->actingAs($this->user);

        // Create learning opportunities (representing agent runs)
        LearningOpportunity::factory()->count(15)->create([
            'opportunity_type' => 'decision_discovery',
        ]);

        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('15')
            ->assertSee('Total Runs');
    }

    /** @test */
    public function it_calculates_success_rate()
    {
        $this->actingAs($this->user);

        // Create 8 successful runs (reviewed) and 2 pending
        LearningOpportunity::factory()->count(8)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'reviewed',
            'confidence_score' => 0.85, // High confidence = success
        ]);

        LearningOpportunity::factory()->count(2)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'pending',
            'confidence_score' => 0.45, // Low confidence
        ]);

        // Success rate: 8/10 = 80%
        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('80%')
            ->assertSee('Success Rate');
    }

    /** @test */
    public function it_displays_average_confidence_score()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->create([
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.70,
        ]);

        LearningOpportunity::factory()->create([
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.80,
        ]);

        LearningOpportunity::factory()->create([
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.90,
        ]);

        // Average: (0.70 + 0.80 + 0.90) / 3 = 0.80
        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('0.80')
            ->assertSee('Average Confidence');
    }

    /** @test */
    public function it_counts_learning_opportunities_generated()
    {
        $this->actingAs($this->user);

        // Create learning opportunities
        LearningOpportunity::factory()->count(12)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'pending', // Generated but not yet reviewed
        ]);

        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('12')
            ->assertSee('Learning Opportunities');
    }

    /** @test */
    public function it_calculates_feedback_incorporation_rate()
    {
        $this->actingAs($this->user);

        // Create 7 incorporated opportunities
        LearningOpportunity::factory()->count(7)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'reviewed',
            'incorporated_at' => now(),
        ]);

        // Create 3 reviewed but not incorporated
        LearningOpportunity::factory()->count(3)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'reviewed',
            'incorporated_at' => null,
        ]);

        // Incorporation rate: 7/10 = 70%
        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('70%')
            ->assertSee('Feedback Incorporation');
    }

    /** @test */
    public function it_filters_by_agent_type()
    {
        $this->actingAs($this->user);

        // Create opportunities for different agent types
        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'decision_discovery',
        ]);

        LearningOpportunity::factory()->count(3)->create([
            'opportunity_type' => 'precedent_analysis',
        ]);

        $component = Livewire::test(AgentPerformanceDashboard::class)
            ->set('filterAgentType', 'decision_discovery')
            ->assertSee('5')
            ->assertSee('Total Runs');

        // Change filter
        $component->set('filterAgentType', 'precedent_analysis')
            ->assertSee('3')
            ->assertSee('Total Runs');
    }

    /** @test */
    public function it_filters_by_date_range()
    {
        $this->actingAs($this->user);

        // Create opportunities at different dates
        LearningOpportunity::factory()->count(4)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(5),
        ]);

        LearningOpportunity::factory()->count(6)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(15),
        ]);

        // Filter last 7 days
        Livewire::test(AgentPerformanceDashboard::class)
            ->set('filterDateFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('filterDateTo', now()->format('Y-m-d'))
            ->assertSee('4')
            ->assertSee('Total Runs');
    }

    /** @test */
    public function it_displays_time_series_chart_data()
    {
        $this->actingAs($this->user);

        // Create opportunities over time
        LearningOpportunity::factory()->count(2)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(10),
            'confidence_score' => 0.65,
        ]);

        LearningOpportunity::factory()->count(3)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(5),
            'confidence_score' => 0.75,
        ]);

        LearningOpportunity::factory()->count(2)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(1),
            'confidence_score' => 0.85,
        ]);

        $component = Livewire::test(AgentPerformanceDashboard::class);

        // Verify time series data is present
        $timeSeriesData = $component->viewData('timeSeriesData');
        $this->assertNotEmpty($timeSeriesData);
        $this->assertArrayHasKey('labels', $timeSeriesData);
        $this->assertArrayHasKey('datasets', $timeSeriesData);
    }

    /** @test */
    public function it_displays_comparison_chart_before_vs_after()
    {
        $this->actingAs($this->user);

        // Create opportunities before active learning (older, lower confidence)
        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(30),
            'confidence_score' => 0.55,
            'incorporated_at' => null,
        ]);

        // Create opportunities after active learning (recent, higher confidence)
        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(5),
            'confidence_score' => 0.78,
            'incorporated_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(AgentPerformanceDashboard::class);

        // Verify comparison data shows improvement
        $comparisonData = $component->viewData('comparisonData');
        $this->assertNotEmpty($comparisonData);
        $this->assertArrayHasKey('before', $comparisonData);
        $this->assertArrayHasKey('after', $comparisonData);
        $this->assertGreaterThan($comparisonData['before']['avg_confidence'], $comparisonData['after']['avg_confidence']);
    }

    /** @test */
    public function it_refreshes_dashboard_data()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('0'); // Initially 0 runs

        // Create new opportunities
        LearningOpportunity::factory()->count(3)->create([
            'opportunity_type' => 'decision_discovery',
        ]);

        $component->call('refreshDashboard')
            ->assertSee('3'); // Should update
    }

    /** @test */
    public function it_shows_breakdown_by_agent_type()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(8)->create([
            'opportunity_type' => 'decision_discovery',
        ]);

        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'precedent_analysis',
        ]);

        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('decision_discovery')
            ->assertSee('8')
            ->assertSee('precedent_analysis')
            ->assertSee('5')
            ->assertSee('Agent Type Breakdown');
    }

    /** @test */
    public function it_handles_empty_state_gracefully()
    {
        $this->actingAs($this->user);

        // No learning opportunities
        Livewire::test(AgentPerformanceDashboard::class)
            ->assertSee('0')
            ->assertSee('No data available');
    }

    /** @test */
    public function it_calculates_improvement_percentage()
    {
        $this->actingAs($this->user);

        // Old data: avg confidence 0.60
        LearningOpportunity::factory()->count(10)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(30),
            'confidence_score' => 0.60,
        ]);

        // New data: avg confidence 0.75
        LearningOpportunity::factory()->count(10)->create([
            'opportunity_type' => 'decision_discovery',
            'created_at' => now()->subDays(5),
            'confidence_score' => 0.75,
        ]);

        // Improvement: (0.75 - 0.60) / 0.60 * 100 = 25%
        $component = Livewire::test(AgentPerformanceDashboard::class);

        $improvementData = $component->viewData('improvementMetrics');
        $this->assertArrayHasKey('confidence_improvement', $improvementData);
        $this->assertGreaterThan(20, $improvementData['confidence_improvement']);
    }

    /** @test */
    public function it_exports_dashboard_data()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'decision_discovery',
        ]);

        $component = Livewire::test(AgentPerformanceDashboard::class);

        $exportData = $component->call('getExportData')->viewData('exportData');

        $this->assertNotEmpty($exportData);
        $this->assertArrayHasKey('metrics', $exportData);
        $this->assertArrayHasKey('time_series', $exportData);
        $this->assertArrayHasKey('comparison', $exportData);
    }
}
