<?php

namespace Tests\Unit\Agents;

use App\Agents\Specialists\PrecedentAnalystAgent;
use App\Models\AgentCollaboration;
use App\Models\LearningOpportunity;
use App\Services\Collaboration\SharedAgentContext;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for PrecedentAnalystAgent Active Learning Integration
 *
 * Sprint 5.1: Learning Opportunity Detection
 *
 * Tests that PrecedentAnalystAgent flags low-confidence applicability scores
 * as learning opportunities for human review.
 */
class PrecedentAnalystAgentLearningTest extends TestCase
{
    use RefreshDatabase;

    protected SharedAgentContext $context;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        // Create AgentCollaboration for context
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'legal_research',
            'problem_statement' => 'Analyze precedents for home search case',
            'status' => 'in_progress',
        ]);

        $this->context = new SharedAgentContext($collaboration);
    }

    /** @test */
    public function it_flags_low_confidence_applicability_scores_as_learning_opportunities()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'analyses' => [
                            [
                                'index' => 0,
                                'applicability_score' => 85,
                                'binding_authority' => 'binding',
                                'key_factors' => ['proportionality', 'necessity'],
                                'favorable' => true,
                                'reasoning' => 'High confidence - clear precedent',
                            ],
                            [
                                'index' => 1,
                                'applicability_score' => 45, // Below threshold
                                'binding_authority' => 'informative',
                                'key_factors' => ['different facts'],
                                'favorable' => false,
                                'reasoning' => 'Uncertain - ambiguous applicability',
                            ],
                            [
                                'index' => 2,
                                'applicability_score' => 52, // Below threshold
                                'binding_authority' => 'persuasive',
                                'key_factors' => ['similar issue'],
                                'favorable' => true,
                                'reasoning' => 'Marginal relevance',
                            ],
                        ],
                    ])]],
                ],
            ]);

        $agent = new PrecedentAnalystAgent($mockOpenAI);

        // Provide research data
        $this->context->write('researched_decisions', [
            ['id' => 'dec-1', 'court' => 'Vrhovni sud', 'case_number' => 'K-123/2024'],
            ['id' => 'dec-2', 'court' => 'Županijski sud', 'case_number' => 'K-456/2024'],
            ['id' => 'dec-3', 'court' => 'Općinski sud', 'case_number' => 'K-789/2024'],
        ]);

        $result = $agent->execute($this->context, ['task' => 'analyze_precedents']);

        // Should have 2 learning opportunities created (applicability_score < 60)
        $this->assertEquals(2, LearningOpportunity::count());

        $opportunities = LearningOpportunity::all();

        // Verify first opportunity (score 45)
        $opp1 = $opportunities->firstWhere('ai_output.applicability_score', 45);
        $this->assertNotNull($opp1);
        $this->assertEquals('precedent_analysis', $opp1->opportunity_type);
        $this->assertEquals('applicability_check', $opp1->source_type);
        $this->assertEquals(0.45, $opp1->confidence_score); // Normalized to 0-1
        $this->assertEquals('pending', $opp1->status);
        $this->assertArrayHasKey('applicability_score', $opp1->ai_output);
        $this->assertEquals(45, $opp1->ai_output['applicability_score']);

        // Verify second opportunity (score 52)
        $opp2 = $opportunities->firstWhere('ai_output.applicability_score', 52);
        $this->assertNotNull($opp2);
        $this->assertEquals(0.52, $opp2->confidence_score);
    }

    /** @test */
    public function it_does_not_flag_high_confidence_applicability_scores()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'analyses' => [
                            [
                                'index' => 0,
                                'applicability_score' => 85,
                                'binding_authority' => 'binding',
                                'key_factors' => ['proportionality'],
                                'favorable' => true,
                                'reasoning' => 'Clear binding precedent',
                            ],
                            [
                                'index' => 1,
                                'applicability_score' => 72,
                                'binding_authority' => 'persuasive',
                                'key_factors' => ['similar facts'],
                                'favorable' => true,
                                'reasoning' => 'Strong persuasive precedent',
                            ],
                        ],
                    ])]],
                ],
            ]);

        $agent = new PrecedentAnalystAgent($mockOpenAI);

        $this->context->write('researched_decisions', [
            ['id' => 'dec-1', 'court' => 'Vrhovni sud'],
            ['id' => 'dec-2', 'court' => 'Županijski sud'],
        ]);

        $result = $agent->execute($this->context, ['task' => 'analyze_precedents']);

        // Should have NO learning opportunities (all scores above threshold)
        $this->assertEquals(0, LearningOpportunity::count());
    }

    /** @test */
    public function it_stores_complete_precedent_data_in_learning_opportunity()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'analyses' => [
                            [
                                'index' => 0,
                                'applicability_score' => 48,
                                'binding_authority' => 'informative',
                                'key_factors' => ['different jurisdiction', 'procedural issue'],
                                'favorable' => null,
                                'reasoning' => 'Uncertain applicability due to jurisdictional differences',
                            ],
                        ],
                    ])]],
                ],
            ]);

        $agent = new PrecedentAnalystAgent($mockOpenAI);

        $this->context->write('researched_decisions', [
            [
                'id' => 'dec-uncertain',
                'court' => 'Općinski sud u Rijeci',
                'case_number' => 'K-999/2023',
                'title' => 'Unclear precedent case',
            ],
        ]);

        $result = $agent->execute($this->context, ['task' => 'analyze_precedents']);

        $opportunity = LearningOpportunity::first();
        $this->assertNotNull($opportunity);

        // Verify all relevant data is stored
        $this->assertEquals('precedent_analysis', $opportunity->opportunity_type);
        $this->assertEquals('applicability_check', $opportunity->source_type);
        $this->assertEquals(48, $opportunity->ai_output['applicability_score']);
        $this->assertEquals('informative', $opportunity->ai_output['binding_authority']);
        $this->assertIsArray($opportunity->ai_output['key_factors']);
        $this->assertCount(2, $opportunity->ai_output['key_factors']);
        $this->assertEquals('dec-uncertain', $opportunity->ai_output['decision_id']);
        $this->assertStringContainsString('Low confidence score: 0.48', $opportunity->uncertainty_reason);
    }

    /** @test */
    public function it_handles_batch_processing_with_mixed_confidence_scores()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'analyses' => [
                            ['index' => 0, 'applicability_score' => 90, 'binding_authority' => 'binding', 'key_factors' => [], 'favorable' => true, 'reasoning' => 'High'],
                            ['index' => 1, 'applicability_score' => 35, 'binding_authority' => 'informative', 'key_factors' => [], 'favorable' => false, 'reasoning' => 'Low'],
                            ['index' => 2, 'applicability_score' => 75, 'binding_authority' => 'persuasive', 'key_factors' => [], 'favorable' => true, 'reasoning' => 'Good'],
                            ['index' => 3, 'applicability_score' => 42, 'binding_authority' => 'informative', 'key_factors' => [], 'favorable' => null, 'reasoning' => 'Uncertain'],
                            ['index' => 4, 'applicability_score' => 88, 'binding_authority' => 'binding', 'key_factors' => [], 'favorable' => true, 'reasoning' => 'Strong'],
                        ],
                    ])]],
                ],
            ]);

        $agent = new PrecedentAnalystAgent($mockOpenAI);

        $this->context->write('researched_decisions', [
            ['id' => 'dec-1'], ['id' => 'dec-2'], ['id' => 'dec-3'], ['id' => 'dec-4'], ['id' => 'dec-5'],
        ]);

        $result = $agent->execute($this->context, ['task' => 'analyze_precedents']);

        // Should have 2 learning opportunities (scores 35 and 42, both < 60)
        $this->assertEquals(2, LearningOpportunity::count());

        $scores = LearningOpportunity::pluck('confidence_score')->sort()->values();
        $this->assertEquals(0.35, $scores[0]);
        $this->assertEquals(0.42, $scores[1]);
    }

    /** @test */
    public function it_prevents_duplicate_learning_opportunities_for_same_precedent()
    {
        // Create existing learning opportunity with matching source_id
        $decisionId = 'dec-duplicate';
        $sourceId = crc32($decisionId);

        LearningOpportunity::create([
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'source_id' => $sourceId, // Match the hash that will be generated
            'ai_output' => ['decision_id' => $decisionId, 'applicability_score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'analyses' => [
                            [
                                'index' => 0,
                                'applicability_score' => 45,
                                'binding_authority' => 'informative',
                                'key_factors' => [],
                                'favorable' => false,
                                'reasoning' => 'Low confidence',
                            ],
                        ],
                    ])]],
                ],
            ]);

        $agent = new PrecedentAnalystAgent($mockOpenAI);

        $this->context->write('researched_decisions', [
            ['id' => 'dec-duplicate', 'court' => 'Test'],
        ]);

        $result = $agent->execute($this->context, ['task' => 'analyze_precedents']);

        // Should still have only 1 learning opportunity (no duplicate)
        $this->assertEquals(1, LearningOpportunity::count());
    }
}
