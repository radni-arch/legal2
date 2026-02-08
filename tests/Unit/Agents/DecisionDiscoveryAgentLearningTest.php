<?php

namespace Tests\Unit\Agents;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\LearningOpportunity;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for DecisionDiscoveryAgent Active Learning Integration
 *
 * Sprint 5.1: Learning Opportunity Detection
 *
 * Tests that DecisionDiscoveryAgent flags low-confidence decision scores
 * as learning opportunities for human review.
 */
class DecisionDiscoveryAgentLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();
    }

    /** @test */
    public function it_flags_low_confidence_decision_scores_as_learning_opportunities()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => ['dec-1', 'dec-2', 'dec-3'],
                'count' => 3,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(3)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 1,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // QueryRewriter + scoring
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'High confidence'],
                            ['id' => 'dec-2', 'score' => 45, 'reasoning' => 'Uncertain relevance'], // Below threshold
                            ['id' => 'dec-3', 'score' => 55, 'reasoning' => 'Marginal relevance'], // Below threshold
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setRelevanceThreshold(70); // Threshold at 70

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'test topic');

        // Should have 2 learning opportunities created (dec-2 and dec-3)
        $this->assertEquals(2, LearningOpportunity::count());

        $opportunities = LearningOpportunity::all();

        // Verify first opportunity (dec-2 with score 45)
        $opp1 = $opportunities->firstWhere('ai_output.score', 45);
        $this->assertNotNull($opp1);
        $this->assertEquals('decision_discovery', $opp1->opportunity_type);
        $this->assertEquals('decision_score', $opp1->source_type);
        $this->assertEquals(0.45, $opp1->confidence_score); // Normalized to 0-1
        $this->assertEquals('pending', $opp1->status);
        $this->assertArrayHasKey('score', $opp1->ai_output);
        $this->assertEquals(45, $opp1->ai_output['score']);

        // Verify second opportunity (dec-3 with score 55)
        $opp2 = $opportunities->firstWhere('ai_output.score', 55);
        $this->assertNotNull($opp2);
        $this->assertEquals(0.55, $opp2->confidence_score); // Normalized to 0-1
    }

    /** @test */
    public function it_does_not_flag_high_confidence_decision_scores()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => ['dec-1', 'dec-2'],
                'count' => 2,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->times(2)
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);
        $mockIngest->shouldReceive('ingestByIds')
            ->once()
            ->andReturn([
                'inserted' => 2,
                'errors' => 0,
                'skipped' => 0,
            ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice() // QueryRewriter + scoring
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'High confidence'],
                            ['id' => 'dec-2', 'score' => 92, 'reasoning' => 'Very relevant'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'test topic');

        // Should have NO learning opportunities (all scores above threshold)
        $this->assertEquals(0, LearningOpportunity::count());
    }

    /** @test */
    public function it_stores_complete_decision_data_in_learning_opportunity()
    {
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => ['dec-low-conf'],
                'count' => 1,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->once()
            ->andReturn([
                'title' => 'Uncertain Decision',
                'court' => 'Općinski sud',
                'date' => '2024-06-15',
                'type' => 'Rješenje',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            [
                                'id' => 'dec-low-conf',
                                'score' => 52,
                                'reasoning' => 'Ambiguous relevance to topic',
                            ],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'contract disputes');

        $opportunity = LearningOpportunity::first();
        $this->assertNotNull($opportunity);

        // Verify all relevant data is stored
        $this->assertEquals('decision_discovery', $opportunity->opportunity_type);
        $this->assertEquals('dec-low-conf', $opportunity->ai_output['id']);
        $this->assertEquals(52, $opportunity->ai_output['score']);
        $this->assertEquals('Ambiguous relevance to topic', $opportunity->ai_output['reasoning']);
        $this->assertEquals('contract disputes', $opportunity->ai_output['topic']);
        $this->assertStringContainsString('Low confidence score: 0.52', $opportunity->uncertainty_reason);
    }

    /** @test */
    public function it_prevents_duplicate_learning_opportunities_for_same_decision()
    {
        // Create existing learning opportunity with matching source_id
        $decisionId = 'dec-duplicate';
        $sourceId = crc32($decisionId);

        LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => $sourceId, // Match the hash that will be generated
            'ai_output' => ['id' => $decisionId, 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/test',
                'ids' => ['dec-duplicate'],
                'count' => 1,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->once()
            ->andReturn([
                'title' => 'Test Decision',
                'court' => 'Vrhovni sud',
                'date' => '2024-01-01',
            ]);

        $mockIngest = Mockery::mock(OdlukeIngestService::class);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'scores' => [
                            ['id' => 'dec-duplicate', 'score' => 45, 'reasoning' => 'Low confidence'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new DecisionDiscoveryAgent($mockClient, $mockIngest, $mockOpenAI);
        $agent->setRelevanceThreshold(70);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('discoverForTopic');
        $method->setAccessible(true);

        $result = $method->invoke($agent, 'test topic');

        // Should still have only 1 learning opportunity (no duplicate)
        $this->assertEquals(1, LearningOpportunity::count());
    }
}
