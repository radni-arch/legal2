<?php

namespace Tests\Unit\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Services\Research\AnswerEvaluatorService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Comprehensive test suite for AnswerEvaluatorService
 *
 * Tests all public and protected methods with various scenarios:
 * - Answer evaluation (quality scoring)
 * - Source relevance scoring
 * - Insight extraction (LLM and fallback)
 * - Result formatting
 * - Helper methods (citation detection, relevance checking)
 */
class AnswerEvaluatorServiceTest extends TestCase
{
    protected ChatServiceInterface $mockChat;

    protected AnswerEvaluatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

        $this->mockChat = Mockery::mock(ChatServiceInterface::class);
        $this->service = new AnswerEvaluatorService($this->mockChat);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // evaluate() method tests
    // ========================================

    /** @test */
    public function test_evaluate_basic()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.8, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Good answer"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $result = $this->service->evaluate('What is Article 93?', 'Article 93 requires notice', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('has_citations', $result);
        $this->assertArrayHasKey('is_relevant', $result);
        $this->assertArrayHasKey('is_actionable', $result);
        $this->assertArrayHasKey('feedback', $result);
    }

    /** @test */
    public function test_evaluate_returns_quality_score()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.85, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Excellent"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $result = $this->service->evaluate('query', 'answer', []);

        $this->assertEquals(0.85, $result['quality_score']);
    }

    /** @test */
    public function test_evaluate_returns_gaps()
    {
        // The evaluate method returns feedback, not gaps directly
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.5, "has_citations": false, "is_relevant": true, "is_actionable": false, "feedback": "Missing citations and not actionable"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $result = $this->service->evaluate('query', 'answer', []);

        $this->assertStringContainsString('Missing citations', $result['feedback']);
    }

    /** @test */
    public function test_evaluate_high_quality_answer()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.95, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Excellent answer with proper citations"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $answer = 'Article 93 of the Croatian Labor Law (NN 93/14) requires employers to provide written notice.';
        $result = $this->service->evaluate('What is Article 93?', $answer, []);

        $this->assertGreaterThanOrEqual(0.9, $result['quality_score']);
        $this->assertTrue($result['has_citations']);
        $this->assertTrue($result['is_relevant']);
        $this->assertTrue($result['is_actionable']);
    }

    /** @test */
    public function test_evaluate_low_quality_answer()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.2, "has_citations": false, "is_relevant": false, "is_actionable": false, "feedback": "Too vague, no citations"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $answer = 'Found some law about employment';
        $result = $this->service->evaluate('What is Article 93?', $answer, []);

        $this->assertLessThanOrEqual(0.3, $result['quality_score']);
    }

    /** @test */
    public function test_evaluate_empty_answer()
    {
        // Should not call LLM for empty answer
        $this->mockChat->shouldNotReceive('chat');

        $result = $this->service->evaluate('query', '', []);

        $this->assertEquals(0.0, $result['quality_score']);
        $this->assertFalse($result['has_citations']);
        $this->assertFalse($result['is_relevant']);
        $this->assertFalse($result['is_actionable']);
        $this->assertEquals('Answer is empty', $result['feedback']);
    }

    /** @test */
    public function test_evaluate_with_no_sources()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.6, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Good but lacks source backing"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $result = $this->service->evaluate('query', 'Article 93 requires notice', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality_score', $result);
    }

    /** @test */
    public function test_evaluate_with_citations()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{"quality_score": 0.9, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Excellent with citations"}']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $answer = 'Article 93 of Croatian Labor Law (NN 93/14) requires 2 weeks notice.';
        $result = $this->service->evaluate('query', $answer, []);

        $this->assertTrue($result['has_citations']);
    }

    /** @test */
    public function test_evaluate_llm_failure_uses_fallback()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $answer = 'Article 93 of Croatian Labor Law (NN 93/14) requires notice.';
        $result = $this->service->evaluate('query about Article 93', $answer, [
            ['type' => 'law', 'content' => 'Some law content'],
        ]);

        // Should still return valid structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertEquals('Evaluated using fallback heuristics', $result['feedback']);
    }

    // ========================================
    // scoreRelevance() method tests
    // ========================================

    /** @test */
    public function test_score_relevance_basic()
    {
        $sources = [
            ['type' => 'law', 'content' => 'Labor law about employment termination notice requirements'],
            ['type' => 'decision', 'content' => 'Decision about housing contracts'],
        ];

        $result = $this->service->scoreRelevance('employment termination notice', $sources);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('relevance_score', $result[0]);
        $this->assertArrayHasKey('relevance_score', $result[1]);
    }

    /** @test */
    public function test_score_relevance_high()
    {
        $sources = [
            ['type' => 'law', 'content' => 'Labor law employment termination notice requirements period'],
        ];

        $result = $this->service->scoreRelevance('employment termination notice', $sources);

        $this->assertGreaterThan(0.7, $result[0]['relevance_score']);
    }

    /** @test */
    public function test_score_relevance_low()
    {
        $sources = [
            ['type' => 'law', 'content' => 'Tax law about income reporting'],
        ];

        $result = $this->service->scoreRelevance('employment termination notice', $sources);

        $this->assertLessThan(0.7, $result[0]['relevance_score']);
    }

    /** @test */
    public function test_score_relevance_empty_sources()
    {
        $result = $this->service->scoreRelevance('query', []);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_score_relevance_sorts_by_score()
    {
        $sources = [
            ['type' => 'law', 'content' => 'Tax law about income'],
            ['type' => 'law', 'content' => 'Labor employment termination notice requirements'],
            ['type' => 'law', 'content' => 'Housing contracts'],
        ];

        $result = $this->service->scoreRelevance('employment termination notice', $sources);

        // Should be sorted by relevance (highest first)
        $this->assertGreaterThanOrEqual($result[1]['relevance_score'], $result[0]['relevance_score']);
        $this->assertGreaterThanOrEqual($result[2]['relevance_score'], $result[1]['relevance_score']);
    }

    // ========================================
    // extractInsight() method tests
    // ========================================

    /** @test */
    public function test_extract_insight_basic()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Article 93 requires 2 weeks notice (NN 93/14).']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $result = [
            'laws' => [
                ['title' => 'Labor Law', 'law_number' => 'NN 93/14', 'content' => 'Article 93 text...'],
            ],
        ];

        $insight = $this->service->extractInsight($result, 'What notice period is required?');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Article 93', $insight);
    }

    /** @test */
    public function test_extract_insight_from_laws()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Labor Law Article 93 (NN 93/14) requires notice.']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $result = [
            'laws' => [
                [
                    'title' => 'Croatian Labor Law',
                    'law_number' => 'NN 93/14',
                    'content' => 'Article 93: Employers must provide written notice...',
                ],
            ],
        ];

        $insight = $this->service->extractInsight($result, 'termination notice requirements');

        $this->assertNotNull($insight);
    }

    /** @test */
    public function test_extract_insight_from_decisions()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Supreme Court held that notice is required.']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $result = [
            'decisions' => [
                [
                    'title' => 'Decision 123',
                    'court' => 'Supreme Court',
                    'case_number' => 'Gž-1234/2023',
                    'decision_date' => '2023-05-15',
                ],
            ],
        ];

        $insight = $this->service->extractInsight($result, 'court precedent on notice');

        $this->assertNotNull($insight);
    }

    /** @test */
    public function test_extract_insight_empty_result()
    {
        // Should not call LLM for empty result
        $this->mockChat->shouldNotReceive('chat');

        $insight = $this->service->extractInsight([], 'objective');

        $this->assertNull($insight);
    }

    /** @test */
    public function test_extract_insight_returns_null_when_not_relevant()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'null']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $result = [
            'laws' => [
                ['title' => 'Tax Law', 'law_number' => 'NN 1/20', 'content' => 'About taxes...'],
            ],
        ];

        $insight = $this->service->extractInsight($result, 'employment termination');

        $this->assertNull($insight);
    }

    /** @test */
    public function test_extract_insight_llm_failure_uses_fallback()
    {
        $this->mockChat->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $result = [
            'laws' => [
                ['title' => 'Labor Law', 'law_number' => 'NN 93/14', 'content' => 'Article 93...'],
            ],
        ];

        $insight = $this->service->extractInsight($result, 'objective');

        // Should use fallback
        $this->assertNotNull($insight);
        $this->assertStringContainsString('Labor Law', $insight);
    }

    // ========================================
    // extractSimpleInsight() method tests
    // ========================================

    /** @test */
    public function test_extract_simple_insight_from_laws()
    {
        $result = [
            'laws' => [
                ['title' => 'Croatian Labor Law', 'law_number' => 'NN 93/14'],
            ],
        ];

        $insight = $this->service->extractSimpleInsight($result);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Croatian Labor Law', $insight);
        $this->assertStringContainsString('NN 93/14', $insight);
    }

    /** @test */
    public function test_extract_simple_insight_from_decisions()
    {
        $result = [
            'decisions' => [
                ['title' => 'Decision 123', 'court' => 'Supreme Court of Croatia'],
            ],
        ];

        $insight = $this->service->extractSimpleInsight($result);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Decision 123', $insight);
        $this->assertStringContainsString('Supreme Court', $insight);
    }

    /** @test */
    public function test_extract_simple_insight_from_graph()
    {
        $result = [
            'rows' => [
                ['node' => 'Law1'],
                ['node' => 'Law2'],
                ['node' => 'Law3'],
            ],
        ];

        $insight = $this->service->extractSimpleInsight($result);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('3 related entities', $insight);
    }

    /** @test */
    public function test_extract_simple_insight_empty_result()
    {
        $insight = $this->service->extractSimpleInsight([]);

        $this->assertNull($insight);
    }

    /** @test */
    public function test_extract_simple_insight_no_relevant_data()
    {
        $result = [
            'some_other_key' => ['data'],
        ];

        $insight = $this->service->extractSimpleInsight($result);

        $this->assertNull($insight);
    }

    // ========================================
    // formatResultsForExtraction() method tests
    // ========================================

    /** @test */
    public function test_format_results_with_laws()
    {
        $result = [
            'laws' => [
                [
                    'title' => 'Croatian Labor Law',
                    'law_number' => 'NN 93/14',
                    'content' => 'Article 93 text about notice requirements and procedures...',
                ],
            ],
        ];

        $formatted = $this->service->formatResultsForExtraction($result);

        $this->assertStringContainsString('## Laws Found:', $formatted);
        $this->assertStringContainsString('Croatian Labor Law', $formatted);
        $this->assertStringContainsString('NN 93/14', $formatted);
    }

    /** @test */
    public function test_format_results_with_decisions()
    {
        $result = [
            'decisions' => [
                [
                    'title' => 'Supreme Court Decision',
                    'court' => 'Vrhovni sud RH',
                    'case_number' => 'Gž-1234/2023',
                    'decision_date' => '2023-05-15',
                ],
            ],
        ];

        $formatted = $this->service->formatResultsForExtraction($result);

        $this->assertStringContainsString('## Court Decisions Found:', $formatted);
        $this->assertStringContainsString('Supreme Court Decision', $formatted);
        $this->assertStringContainsString('Vrhovni sud RH', $formatted);
        $this->assertStringContainsString('Gž-1234/2023', $formatted);
    }

    /** @test */
    public function test_format_results_with_cases()
    {
        $result = [
            'cases' => [
                [
                    'title' => 'Employment Dispute Case',
                    'case_number' => 'K-123/2023',
                    'status' => 'Active',
                ],
            ],
        ];

        $formatted = $this->service->formatResultsForExtraction($result);

        $this->assertStringContainsString('## Legal Cases Found:', $formatted);
        $this->assertStringContainsString('Employment Dispute Case', $formatted);
        $this->assertStringContainsString('K-123/2023', $formatted);
    }

    /** @test */
    public function test_format_results_with_graph()
    {
        $result = [
            'rows' => [
                ['node' => 'Law1', 'relationship' => 'CITES'],
                ['node' => 'Law2', 'relationship' => 'REFERENCES'],
            ],
        ];

        $formatted = $this->service->formatResultsForExtraction($result);

        $this->assertStringContainsString('## Related Entities (Graph):', $formatted);
        $this->assertStringContainsString('2 related entities found', $formatted);
    }

    /** @test */
    public function test_format_results_empty()
    {
        $formatted = $this->service->formatResultsForExtraction([]);

        $this->assertEquals('No results to format', $formatted);
    }

    /** @test */
    public function test_format_results_with_multiple_types()
    {
        $result = [
            'laws' => [
                ['title' => 'Law1', 'law_number' => 'NN 1/20', 'content' => 'Content...'],
            ],
            'decisions' => [
                ['title' => 'Decision1', 'court' => 'Court1', 'case_number' => 'C1', 'decision_date' => '2023-01-01'],
            ],
            'cases' => [
                ['title' => 'Case1', 'case_number' => 'K1', 'status' => 'Active'],
            ],
        ];

        $formatted = $this->service->formatResultsForExtraction($result);

        $this->assertStringContainsString('## Laws Found:', $formatted);
        $this->assertStringContainsString('## Court Decisions Found:', $formatted);
        $this->assertStringContainsString('## Legal Cases Found:', $formatted);
    }

    // ========================================
    // Helper method tests (using reflection)
    // ========================================

    /** @test */
    public function test_detect_citations()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'detectCitations');
        $method->setAccessible(true);

        // Test Croatian case numbers
        $this->assertTrue($method->invoke($this->service, 'Supreme Court in Gž-1234/2023 held...'));

        // Test NN citations
        $this->assertTrue($method->invoke($this->service, 'Labor Law (NN 93/14) requires...'));

        // Test article references
        $this->assertTrue($method->invoke($this->service, 'Članak 93 states that...'));

        // Test law references
        $this->assertTrue($method->invoke($this->service, 'ZKP 123 provides...'));

        // Test no citations
        $this->assertFalse($method->invoke($this->service, 'Some text without any citations'));
    }

    /** @test */
    public function test_check_relevance()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'checkRelevance');
        $method->setAccessible(true);

        // High relevance (keyword overlap)
        $this->assertTrue($method->invoke(
            $this->service,
            'employment termination notice requirements',
            'The employment law requires notice for termination procedures'
        ));

        // Low relevance (no overlap)
        $this->assertFalse($method->invoke(
            $this->service,
            'employment termination notice',
            'tax income reporting procedures'
        ));
    }

    /** @test */
    public function test_extract_keywords()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->service, 'employment termination notice requirements');

        $this->assertContains('employment', $keywords);
        $this->assertContains('termination', $keywords);
        $this->assertContains('notice', $keywords);
        $this->assertContains('requirements', $keywords);

        // Should not contain short words or stop words
        $this->assertNotContains('i', $keywords);
        $this->assertNotContains('u', $keywords);
    }

    /** @test */
    public function test_calculate_relevance_score()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'calculateRelevanceScore');
        $method->setAccessible(true);

        // High relevance
        $score = $method->invoke($this->service, 'employment termination notice', [
            'type' => 'law',
            'content' => 'Labor law about employment termination notice requirements period',
        ]);
        $this->assertGreaterThan(0.7, $score);

        // Low relevance
        $score = $method->invoke($this->service, 'employment termination', [
            'type' => 'law',
            'content' => 'Tax law about income reporting',
        ]);
        $this->assertLessThan(0.7, $score);

        // No content
        $score = $method->invoke($this->service, 'employment', [
            'type' => 'law',
            'content' => '',
        ]);
        $this->assertEquals(0.3, $score);
    }

    /** @test */
    public function test_fallback_evaluation()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'fallbackEvaluation');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            'employment termination notice',
            'Article 93 of Labor Law (NN 93/14) requires notice for employment termination',
            [['type' => 'law', 'content' => 'Some content']]
        );

        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('has_citations', $result);
        $this->assertArrayHasKey('is_relevant', $result);
        $this->assertArrayHasKey('is_actionable', $result);
        $this->assertArrayHasKey('feedback', $result);

        $this->assertTrue($result['has_citations']);
        $this->assertTrue($result['is_relevant']);
    }

    /** @test */
    public function test_parse_evaluation()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'parseEvaluation');
        $method->setAccessible(true);

        $json = '{"quality_score": 0.85, "has_citations": true, "is_relevant": true, "is_actionable": true, "feedback": "Good answer"}';
        $result = $method->invoke($this->service, $json);

        $this->assertEquals(0.85, $result['quality_score']);
        $this->assertTrue($result['has_citations']);
        $this->assertTrue($result['is_relevant']);
        $this->assertTrue($result['is_actionable']);
        $this->assertEquals('Good answer', $result['feedback']);
    }

    /** @test */
    public function test_parse_evaluation_with_invalid_json()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'parseEvaluation');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'not a json');

        // Should return fallback structure
        $this->assertEquals(0.5, $result['quality_score']);
        $this->assertFalse($result['has_citations']);
        $this->assertFalse($result['is_relevant']);
        $this->assertFalse($result['is_actionable']);
    }

    /** @test */
    public function test_build_evaluation_prompt()
    {
        $method = new \ReflectionMethod(AnswerEvaluatorService::class, 'buildEvaluationPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke(
            $this->service,
            'What is Article 93?',
            'Article 93 requires notice',
            [
                ['type' => 'law'],
                ['type' => 'decision'],
            ]
        );

        $this->assertStringContainsString('What is Article 93?', $prompt);
        $this->assertStringContainsString('Article 93 requires notice', $prompt);
        $this->assertStringContainsString('2 sources available', $prompt);
    }
}
