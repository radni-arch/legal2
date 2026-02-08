<?php

namespace Tests\Unit\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Models\AgentRun;
use App\Services\Research\QualityAssessorService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Comprehensive test suite for QualityAssessorService
 *
 * Tests all public and protected methods with various scenarios:
 * - Overall quality assessment
 * - Completeness checking
 * - Iteration evaluation
 * - Final output synthesis
 * - Helper methods (assessment parsing, fallback logic)
 */
class QualityAssessorServiceTest extends TestCase
{
    protected ChatServiceInterface $mockChat;

    protected AnswerEvaluatorInterface $mockEvaluator;

    protected QualityAssessorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

        $this->mockChat = Mockery::mock(ChatServiceInterface::class);
        $this->mockEvaluator = Mockery::mock(AnswerEvaluatorInterface::class);
        $this->service = new QualityAssessorService($this->mockChat, $this->mockEvaluator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // assess() method tests
    // ========================================

    /** @test */
    public function test_assess_basic()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.85, "completeness_score": 0.80, "citation_quality": 0.90, "relevance_score": 0.88, "strengths": ["Well researched"], "weaknesses": [], "recommendations": []}']],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $result = $this->service->assess(
            'What is Article 93?',
            'Article 93 requires notice',
            ['insights' => [], 'iterations' => 3, 'sources_count' => 5]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('completeness_score', $result);
        $this->assertArrayHasKey('citation_quality', $result);
        $this->assertArrayHasKey('relevance_score', $result);
        $this->assertArrayHasKey('strengths', $result);
        $this->assertArrayHasKey('weaknesses', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /** @test */
    public function test_assess_returns_quality_scores()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.92, "completeness_score": 0.88, "citation_quality": 0.95, "relevance_score": 0.90, "strengths": [], "weaknesses": [], "recommendations": []}']],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $result = $this->service->assess('query', 'answer', ['insights' => [], 'iterations' => 1, 'sources_count' => 1]);

        $this->assertEquals(0.92, $result['quality_score']);
        $this->assertEquals(0.88, $result['completeness_score']);
        $this->assertEquals(0.95, $result['citation_quality']);
        $this->assertEquals(0.90, $result['relevance_score']);
    }

    /** @test */
    public function test_assess_high_quality_research()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.95, "completeness_score": 0.92, "citation_quality": 0.98, "relevance_score": 0.94, "strengths": ["Excellent citations", "Comprehensive"], "weaknesses": [], "recommendations": []}']],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $result = $this->service->assess(
            'Research employment law',
            'Article 93 of Croatian Labor Law (NN 93/14) requires 2 weeks notice...',
            ['insights' => ['insight1', 'insight2', 'insight3'], 'iterations' => 5, 'sources_count' => 15]
        );

        $this->assertGreaterThanOrEqual(0.90, $result['quality_score']);
        $this->assertNotEmpty($result['strengths']);
    }

    /** @test */
    public function test_assess_low_quality_research()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.35, "completeness_score": 0.40, "citation_quality": 0.20, "relevance_score": 0.30, "strengths": [], "weaknesses": ["No citations", "Incomplete"], "recommendations": ["Add citations"]}']],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $result = $this->service->assess(
            'What is the law?',
            'Some law about something',
            ['insights' => [], 'iterations' => 1, 'sources_count' => 1]
        );

        $this->assertLessThanOrEqual(0.50, $result['quality_score']);
        $this->assertNotEmpty($result['weaknesses']);
        $this->assertNotEmpty($result['recommendations']);
    }

    /** @test */
    public function test_assess_empty_answer()
    {
        // Should not call LLM for empty answer
        $this->mockChat->shouldNotReceive('chat');

        $result = $this->service->assess('query', '', ['insights' => [], 'iterations' => 1, 'sources_count' => 0]);

        $this->assertEquals(0.0, $result['quality_score']);
        $this->assertEquals(0.0, $result['completeness_score']);
        $this->assertEquals(0.0, $result['citation_quality']);
        $this->assertEquals(0.0, $result['relevance_score']);
        $this->assertContains('No answer provided', $result['weaknesses']);
    }

    /** @test */
    public function test_assess_with_many_insights()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.88, "completeness_score": 0.85, "citation_quality": 0.90, "relevance_score": 0.87, "strengths": ["Many insights"], "weaknesses": [], "recommendations": []}']],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $insights = array_fill(0, 8, 'Legal insight with citation');
        $result = $this->service->assess(
            'Research question',
            'Detailed answer...',
            ['insights' => $insights, 'iterations' => 5, 'sources_count' => 12]
        );

        $this->assertGreaterThan(0.80, $result['quality_score']);
    }

    /** @test */
    public function test_assess_llm_failure_uses_fallback()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $result = $this->service->assess(
            'Query about law',
            'Article 93 of Croatian Labor Law (NN 93/14) requires notice.',
            ['insights' => ['insight1', 'insight2'], 'iterations' => 3, 'sources_count' => 5]
        );

        // Should still return valid structure with fallback scores
        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertGreaterThan(0, $result['quality_score']); // Should have some score from fallback
    }

    // ========================================
    // isComplete() method tests
    // ========================================

    /** @test */
    public function test_is_complete_when_quality_and_completeness_meet_threshold()
    {
        $assessment = [
            'quality_score' => 0.90,
            'completeness_score' => 0.85,
        ];

        $this->assertTrue($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_is_not_complete_when_quality_below_threshold()
    {
        $assessment = [
            'quality_score' => 0.70,
            'completeness_score' => 0.85,
        ];

        $this->assertFalse($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_is_not_complete_when_completeness_below_threshold()
    {
        $assessment = [
            'quality_score' => 0.90,
            'completeness_score' => 0.70,
        ];

        $this->assertFalse($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_is_not_complete_when_both_below_threshold()
    {
        $assessment = [
            'quality_score' => 0.70,
            'completeness_score' => 0.70,
        ];

        $this->assertFalse($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_is_complete_at_exact_threshold()
    {
        $assessment = [
            'quality_score' => 0.85,
            'completeness_score' => 0.80,
        ];

        $this->assertTrue($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_is_complete_with_custom_thresholds()
    {
        $this->service->setQualityThreshold(0.70);
        $this->service->setCompletenessThreshold(0.60);

        $assessment = [
            'quality_score' => 0.75,
            'completeness_score' => 0.65,
        ];

        $this->assertTrue($this->service->isComplete($assessment));
    }

    // ========================================
    // evaluateIteration() method tests
    // ========================================

    /** @test */
    public function test_evaluate_iteration_basic()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research employment law',
            'current_iteration' => 1,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->once()
            ->andReturn('Legal insight found');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => ['laws' => []]],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertArrayHasKey('insights_count', $result);
        $this->assertArrayHasKey('should_stop', $result);
    }

    /** @test */
    public function test_evaluate_iteration_extracts_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research law',
            'current_iteration' => 2,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->times(2)
            ->andReturn('Insight 1', 'Insight 2');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => ['laws' => []]],
                ['success' => true, 'result' => ['decisions' => []]],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertCount(2, $result['insights']);
        $this->assertEquals(2, $result['insights_count']);
    }

    /** @test */
    public function test_evaluate_iteration_should_stop_with_enough_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 3,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->times(3)
            ->andReturn('Insight 1', 'Insight 2', 'Insight 3');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => []],
                ['success' => true, 'result' => []],
                ['success' => true, 'result' => []],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertTrue($result['should_stop']);
    }

    /** @test */
    public function test_evaluate_iteration_should_not_stop_with_few_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 3,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->times(2)
            ->andReturn('Insight 1', 'Insight 2');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => []],
                ['success' => true, 'result' => []],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertFalse($result['should_stop']);
    }

    /** @test */
    public function test_evaluate_iteration_should_stop_at_max_iterations()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 10,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->once()
            ->andReturn('Insight 1');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => []],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertTrue($result['should_stop']);
    }

    /** @test */
    public function test_evaluate_iteration_skips_failed_actions()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 1,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->once()
            ->andReturn('Insight from successful action');

        $iteration = [
            'actions' => [
                ['success' => false, 'result' => []],
                ['success' => true, 'result' => []],
                ['success' => false, 'result' => []],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertCount(1, $result['insights']);
    }

    /** @test */
    public function test_evaluate_iteration_filters_null_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 1,
            'max_iterations' => 10,
        ]);

        $this->mockEvaluator->shouldReceive('extractInsight')
            ->times(3)
            ->andReturn('Insight 1', null, 'Insight 2');

        $iteration = [
            'actions' => [
                ['success' => true, 'result' => []],
                ['success' => true, 'result' => []],
                ['success' => true, 'result' => []],
            ],
        ];

        $result = $this->service->evaluateIteration($run, $iteration);

        $this->assertCount(2, $result['insights']);
        $this->assertEquals(2, $result['insights_count']);
    }

    // ========================================
    // synthesizeFinalOutput() method tests
    // ========================================

    /** @test */
    public function test_synthesize_final_output_basic()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research employment law',
            'current_iteration' => 3,
            'elapsed_seconds' => 45,
            'tokens_used' => 5000,
            'cost_spent' => 0.15,
        ]);
        $run->iterations = [];

        $output = $this->service->synthesizeFinalOutput($run);

        $this->assertStringContainsString('Research Report: Research employment law', $output);
        $this->assertStringContainsString('Completed 3 research iterations', $output);
        $this->assertStringContainsString('Elapsed time: 45 seconds', $output);
        $this->assertStringContainsString('Tokens used: 5000', $output);
        $this->assertStringContainsString('Cost: $0.15', $output);
    }

    /** @test */
    public function test_synthesize_final_output_with_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research law',
            'current_iteration' => 2,
            'elapsed_seconds' => 30,
        ]);
        $run->iterations = [
            ['evaluation' => ['insights' => ['Insight 1', 'Insight 2']]],
            ['evaluation' => ['insights' => ['Insight 3']]],
        ];

        $output = $this->service->synthesizeFinalOutput($run);

        $this->assertStringContainsString('1. Insight 1', $output);
        $this->assertStringContainsString('2. Insight 2', $output);
        $this->assertStringContainsString('3. Insight 3', $output);
    }

    /** @test */
    public function test_synthesize_final_output_with_no_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 1,
            'elapsed_seconds' => 10,
        ]);
        $run->iterations = [
            ['evaluation' => ['insights' => []]],
        ];

        $output = $this->service->synthesizeFinalOutput($run);

        $this->assertStringContainsString('No significant findings were discovered', $output);
    }

    /** @test */
    public function test_synthesize_final_output_deduplicates_insights()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Research',
            'current_iteration' => 2,
            'elapsed_seconds' => 20,
        ]);
        $run->iterations = [
            ['evaluation' => ['insights' => ['Same insight', 'Different insight']]],
            ['evaluation' => ['insights' => ['Same insight', 'Another insight']]],
        ];

        $output = $this->service->synthesizeFinalOutput($run);

        // Should only appear once
        $this->assertEquals(1, substr_count($output, 'Same insight'));
    }

    /** @test */
    public function test_synthesize_final_output_includes_metadata()
    {
        $run = new AgentRun([
            'id' => 1,
            'objective' => 'Test',
            'current_iteration' => 5,
            'elapsed_seconds' => 120,
            'tokens_used' => 10000,
            'cost_spent' => 0.50,
        ]);
        $run->iterations = [];

        $output = $this->service->synthesizeFinalOutput($run);

        $this->assertStringContainsString('Total iterations: 5', $output);
        $this->assertStringContainsString('Elapsed time: 120 seconds', $output);
        $this->assertStringContainsString('Tokens used: 10000', $output);
        $this->assertStringContainsString('Cost: $0.50', $output);
    }

    // ========================================
    // Helper method tests (using reflection)
    // ========================================

    /** @test */
    public function test_parse_assessment()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'parseAssessment');
        $method->setAccessible(true);

        $json = '{"quality_score": 0.87, "completeness_score": 0.82, "citation_quality": 0.91, "relevance_score": 0.85, "strengths": ["Good"], "weaknesses": ["Minor"], "recommendations": ["Improve"]}';
        $result = $method->invoke($this->service, $json);

        $this->assertEquals(0.87, $result['quality_score']);
        $this->assertEquals(0.82, $result['completeness_score']);
        $this->assertEquals(0.91, $result['citation_quality']);
        $this->assertEquals(0.85, $result['relevance_score']);
        $this->assertEquals(['Good'], $result['strengths']);
        $this->assertEquals(['Minor'], $result['weaknesses']);
        $this->assertEquals(['Improve'], $result['recommendations']);
    }

    /** @test */
    public function test_parse_assessment_with_invalid_json()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'parseAssessment');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'not valid json');

        // Should return fallback structure
        $this->assertEquals(0.5, $result['quality_score']);
        $this->assertEquals(0.5, $result['completeness_score']);
        $this->assertContains('Failed to parse assessment response', $result['weaknesses']);
    }

    /** @test */
    public function test_fallback_assessment()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'fallbackAssessment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            'employment law notice requirements',
            'Article 93 of Croatian Labor Law (NN 93/14) requires 2 weeks notice for employment termination.',
            ['insights' => ['Insight 1', 'Insight 2', 'Insight 3'], 'iterations' => 3, 'sources_count' => 8]
        );

        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('completeness_score', $result);
        $this->assertArrayHasKey('citation_quality', $result);
        $this->assertArrayHasKey('relevance_score', $result);

        // Should have good scores due to citations and insights
        $this->assertGreaterThan(0.6, $result['quality_score']);
        $this->assertGreaterThan(0.6, $result['citation_quality']);
    }

    /** @test */
    public function test_fallback_assessment_with_citations()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'fallbackAssessment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            'query',
            'Answer with NN 93/14 citation and Članak 10',
            ['insights' => [], 'iterations' => 1, 'sources_count' => 1]
        );

        $this->assertGreaterThan(0.6, $result['citation_quality']);
        $this->assertContains('Contains legal citations', $result['strengths']);
    }

    /** @test */
    public function test_fallback_assessment_without_citations()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'fallbackAssessment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            'query',
            'Answer with no citations at all',
            ['insights' => [], 'iterations' => 1, 'sources_count' => 1]
        );

        $this->assertLessThan(0.5, $result['citation_quality']);
        $this->assertContains('Missing legal citations', $result['weaknesses']);
    }

    /** @test */
    public function test_build_assessment_prompt()
    {
        $method = new \ReflectionMethod(QualityAssessorService::class, 'buildAssessmentPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke(
            $this->service,
            'What is Article 93?',
            'Article 93 requires notice',
            ['insights' => ['Insight 1', 'Insight 2'], 'iterations' => 3, 'sources_count' => 5]
        );

        $this->assertStringContainsString('What is Article 93?', $prompt);
        $this->assertStringContainsString('Article 93 requires notice', $prompt);
        $this->assertStringContainsString('Iterations completed: 3', $prompt);
        $this->assertStringContainsString('Sources consulted: 5', $prompt);
        $this->assertStringContainsString('Insights extracted: 2', $prompt);
        $this->assertStringContainsString('1. Insight 1', $prompt);
        $this->assertStringContainsString('2. Insight 2', $prompt);
    }

    /** @test */
    public function test_set_quality_threshold()
    {
        $this->service->setQualityThreshold(0.75);

        $assessment = ['quality_score' => 0.80, 'completeness_score' => 0.85];
        $this->assertTrue($this->service->isComplete($assessment));

        $assessment = ['quality_score' => 0.70, 'completeness_score' => 0.85];
        $this->assertFalse($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_set_completeness_threshold()
    {
        $this->service->setCompletenessThreshold(0.70);

        $assessment = ['quality_score' => 0.90, 'completeness_score' => 0.75];
        $this->assertTrue($this->service->isComplete($assessment));

        $assessment = ['quality_score' => 0.90, 'completeness_score' => 0.65];
        $this->assertFalse($this->service->isComplete($assessment));
    }

    /** @test */
    public function test_thresholds_clamped_to_valid_range()
    {
        $this->service->setQualityThreshold(1.5); // Too high
        $assessment = ['quality_score' => 1.0, 'completeness_score' => 0.85];
        $this->assertTrue($this->service->isComplete($assessment));

        $this->service->setQualityThreshold(-0.5); // Too low
        $assessment = ['quality_score' => 0.01, 'completeness_score' => 0.85];
        $this->assertTrue($this->service->isComplete($assessment));
    }
}
