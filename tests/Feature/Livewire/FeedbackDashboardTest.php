<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\FeedbackDashboard;
use App\Models\LearningOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD Tests for FeedbackDashboard Livewire Component
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Tests the dashboard component for displaying feedback statistics.
 */
class FeedbackDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(FeedbackDashboard::class)
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_total_opportunities_count()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(5)->create(['status' => 'pending']);
        LearningOpportunity::factory()->count(3)->create(['status' => 'reviewed']);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('8') // Total count
            ->assertSee('Total Opportunities');
    }

    /** @test */
    public function it_displays_pending_opportunities_count()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(7)->create(['status' => 'pending']);
        LearningOpportunity::factory()->count(3)->create(['status' => 'reviewed']);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('7')
            ->assertSee('Pending Review');
    }

    /** @test */
    public function it_displays_reviewed_opportunities_count()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(4)->create(['status' => 'pending']);
        LearningOpportunity::factory()->count(6)->create(['status' => 'reviewed']);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('6')
            ->assertSee('Reviewed');
    }

    /** @test */
    public function it_calculates_feedback_completion_rate()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(2)->create(['status' => 'pending']);
        LearningOpportunity::factory()->count(8)->create(['status' => 'reviewed']);

        // 8 reviewed / 10 total = 80%
        Livewire::test(FeedbackDashboard::class)
            ->assertSee('80%')
            ->assertSee('Completion Rate');
    }

    /** @test */
    public function it_handles_zero_opportunities_gracefully()
    {
        $this->actingAs($this->user);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('0')
            ->assertSee('0%'); // 0% completion rate
    }

    /** @test */
    public function it_displays_breakdown_by_opportunity_type()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->count(5)->create([
            'opportunity_type' => 'decision_discovery',
            'status' => 'pending',
        ]);

        LearningOpportunity::factory()->count(3)->create([
            'opportunity_type' => 'precedent_analysis',
            'status' => 'pending',
        ]);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('decision_discovery')
            ->assertSee('5')
            ->assertSee('precedent_analysis')
            ->assertSee('3');
    }

    /** @test */
    public function it_displays_average_confidence_score()
    {
        $this->actingAs($this->user);

        LearningOpportunity::factory()->create(['confidence_score' => 0.30]);
        LearningOpportunity::factory()->create(['confidence_score' => 0.50]);
        LearningOpportunity::factory()->create(['confidence_score' => 0.40]);

        // Average: (0.30 + 0.50 + 0.40) / 3 = 0.40
        Livewire::test(FeedbackDashboard::class)
            ->assertSee('0.40')
            ->assertSee('Average Confidence');
    }

    /** @test */
    public function it_displays_recent_feedback_activity()
    {
        $this->actingAs($this->user);

        $recent = LearningOpportunity::factory()->create([
            'status' => 'reviewed',
            'reviewed_at' => now()->subHours(2),
            'reviewed_by' => $this->user->id,
        ]);

        $old = LearningOpportunity::factory()->create([
            'status' => 'reviewed',
            'reviewed_at' => now()->subDays(10),
            'reviewed_by' => $this->user->id,
        ]);

        Livewire::test(FeedbackDashboard::class)
            ->assertSee('2 hours ago') // Recent activity
            ->assertDontSee('10 days ago'); // Should limit to recent items
    }

    /** @test */
    public function it_refreshes_statistics()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(FeedbackDashboard::class)
            ->assertSee('0'); // Initially 0

        // Create new opportunity
        LearningOpportunity::factory()->create(['status' => 'pending']);

        $component->call('refreshStats')
            ->assertSee('1'); // Should update
    }
}
