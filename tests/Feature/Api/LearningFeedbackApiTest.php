<?php

namespace Tests\Feature\Api;

use App\Models\LearningOpportunity;
use App\Models\User;
use App\Notifications\LearningFeedbackSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TDD Tests for Learning Feedback API
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Tests the API endpoint for attorneys to submit feedback on learning opportunities.
 */
class LearningFeedbackApiTest extends TestCase
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

        // Create authenticated user
        $this->user = User::factory()->create([
            'email' => 'attorney@example.com',
            'name' => 'Test Attorney',
        ]);

        // Fake notifications to prevent actual emails
        Notification::fake();
        Mail::fake();
    }

    /** @test */
    public function it_submits_feedback_successfully()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => ['id' => 'dec-1', 'score' => 45, 'reasoning' => 'Uncertain relevance'],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                'human_label' => [
                    'correct_score' => 75,
                    'reasoning' => 'Actually very relevant to contract law',
                    'decision' => 'should_ingest',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Feedback submitted successfully',
        ]);

        // Verify opportunity updated
        $opportunity->refresh();
        $this->assertEquals('reviewed', $opportunity->status);
        $this->assertEquals($this->user->id, $opportunity->reviewed_by);
        $this->assertNotNull($opportunity->reviewed_at);
        $this->assertEquals(75, $opportunity->human_label['correct_score']);
        $this->assertEquals('should_ingest', $opportunity->human_label['decision']);
    }

    /** @test */
    public function it_sends_email_notification_when_feedback_submitted()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'source_id' => 67890,
            'ai_output' => ['decision_id' => 'dec-2', 'applicability_score' => 52],
            'confidence_score' => 0.52,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                'human_label' => [
                    'correct_applicability' => 85,
                    'reasoning' => 'Highly applicable despite low AI confidence',
                ],
            ]);

        // Verify notification was sent
        Notification::assertSentTo(
            [$this->user],
            LearningFeedbackSubmitted::class,
            function ($notification, $channels) use ($opportunity) {
                return $notification->opportunity->id === $opportunity->id;
            }
        );
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 11111,
            'ai_output' => ['id' => 'dec-3', 'score' => 40],
            'confidence_score' => 0.40,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                // Missing human_label
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['human_label']);
    }

    /** @test */
    public function it_validates_human_label_is_array()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 22222,
            'ai_output' => ['id' => 'dec-4', 'score' => 50],
            'confidence_score' => 0.50,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                'human_label' => 'invalid string',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['human_label']);
    }

    /** @test */
    public function it_prevents_feedback_on_already_reviewed_opportunity()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 33333,
            'ai_output' => ['id' => 'dec-5', 'score' => 55],
            'confidence_score' => 0.55,
            'status' => 'reviewed', // Already reviewed
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['score' => 70],
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                'human_label' => ['score' => 80],
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This learning opportunity has already been reviewed',
        ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_opportunity()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/learning/feedback/99999', [
                'human_label' => ['score' => 75],
            ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 44444,
            'ai_output' => ['id' => 'dec-6', 'score' => 48],
            'confidence_score' => 0.48,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/learning/feedback/{$opportunity->id}", [
            'human_label' => ['score' => 75],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_updated_opportunity_in_response()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 55555,
            'ai_output' => ['id' => 'dec-7', 'score' => 42],
            'confidence_score' => 0.42,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/learning/feedback/{$opportunity->id}", [
                'human_label' => [
                    'correct_score' => 80,
                    'notes' => 'Very relevant case',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'opportunity' => [
                'id',
                'status',
                'reviewed_at',
                'reviewed_by',
                'human_label',
            ],
        ]);

        $this->assertEquals('reviewed', $response->json('opportunity.status'));
        $this->assertEquals(80, $response->json('opportunity.human_label.correct_score'));
    }
}
