<?php

namespace Tests\Feature\Services\Research;

use App\Contracts\Research\AnswerEvaluatorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for AnswerEvaluatorService
 *
 * These tests verify that AnswerEvaluatorService properly integrates with:
 * - OpenAI Chat API (mocked)
 * - Real evaluation and parsing logic
 * - Insight extraction with LLM
 *
 * No database required - focuses on service integration.
 */
class AnswerEvaluatorIntegrationTest extends TestCase
{
    protected AnswerEvaluatorInterface $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Use real AnswerEvaluator implementation
        $this->evaluator = app(AnswerEvaluatorInterface::class);
    }

    protected function tearDown(): void
    {
        // Clean up cache after tests
        Cache::flush();

        parent::tearDown();
    }

    /**
     * Test answer evaluation pipeline
     *
     * Flow: Query + answer + sources → OpenAI evaluation → Parse score + gaps → Return
     *
     * This test verifies the complete integration between:
     * - AnswerEvaluatorService
     * - OpenAIChatService (HTTP mocked)
     * - Evaluation parsing and validation
     *
     * @test
     */
    public function test_answer_evaluator_uses_openai_and_returns_quality_score(): void
    {
        // Arrange: Mock OpenAI HTTP response with evaluation data
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-eval-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'quality_score' => 0.87,
                                'has_citations' => true,
                                'is_relevant' => true,
                                'is_actionable' => true,
                                'feedback' => 'Good answer with proper citations. Could expand on penalties.',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 50,
                    'total_tokens' => 200,
                ],
            ], 200),
        ]);

        // Arrange: Prepare test data
        $query = 'What is criminal liability under Croatian law?';
        $answer = 'Criminal liability requires both actus reus and mens rea. Article 10 of the Croatian Criminal Code (Kazneni zakon, NN 125/11) establishes that criminal liability arises when a person commits an act defined as a criminal offense with intent or negligence.';
        $sources = [
            [
                'type' => 'law',
                'corpus' => 'law',
                'content' => 'Kazneni zakon Article 10: Criminal liability exists when...',
                'title' => 'Kazneni zakon',
            ],
            [
                'type' => 'decision',
                'corpus' => 'court_decisions',
                'content' => 'Supreme Court decision on criminal liability...',
                'title' => 'Supreme Court Decision Gž-1234/2023',
            ],
        ];

        // Act: Evaluate the answer
        $evaluation = $this->evaluator->evaluate($query, $answer, $sources);

        // Assert: Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com')
                && str_contains($request->url(), '/chat/completions');
        });

        // Assert: Verify evaluation structure
        $this->assertIsArray($evaluation);
        $this->assertArrayHasKey('quality_score', $evaluation);
        $this->assertArrayHasKey('has_citations', $evaluation);
        $this->assertArrayHasKey('is_relevant', $evaluation);
        $this->assertArrayHasKey('is_actionable', $evaluation);
        $this->assertArrayHasKey('feedback', $evaluation);

        // Assert: Verify evaluation values
        $this->assertEquals(0.87, $evaluation['quality_score']);
        $this->assertTrue($evaluation['has_citations']);
        $this->assertTrue($evaluation['is_relevant']);
        $this->assertTrue($evaluation['is_actionable']);
        $this->assertIsString($evaluation['feedback']);
        $this->assertNotEmpty($evaluation['feedback']);
    }

    /**
     * Test insight extraction with OpenAI
     *
     * Verifies that extractInsight() properly calls OpenAI and extracts legal insights.
     *
     * @test
     */
    public function test_extract_insight_uses_openai_and_returns_insight(): void
    {
        // Arrange: Mock OpenAI response with insight
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Article 10 of the Croatian Criminal Code (NN 125/11) establishes that criminal liability requires both actus reus (criminal act) and mens rea (criminal intent or negligence).',
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        // Arrange: Prepare search result
        $result = [
            'laws' => [
                [
                    'title' => 'Kazneni zakon',
                    'law_number' => 'NN 125/11',
                    'content' => 'Article 10: Criminal liability exists when a person commits an act defined as a criminal offense with the required mental state...',
                ],
            ],
        ];

        $objective = 'What are the requirements for criminal liability?';

        // Act: Extract insight
        $insight = $this->evaluator->extractInsight($result, $objective);

        // Assert: Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com');
        });

        // Assert: Verify insight
        $this->assertNotNull($insight);
        $this->assertIsString($insight);
        $this->assertStringContainsString('Article 10', $insight);
        $this->assertStringContainsString('Criminal Code', $insight);
        $this->assertStringContainsString('NN 125/11', $insight);
    }

    /**
     * Test insight extraction returns null for irrelevant results
     *
     * @test
     */
    public function test_extract_insight_returns_null_for_irrelevant_results(): void
    {
        // Arrange: Mock OpenAI to return 'null' for irrelevant results
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'null']],
                ],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);

        // Arrange: Irrelevant search result
        $result = [
            'laws' => [
                [
                    'title' => 'Traffic Law',
                    'law_number' => 'NN 50/20',
                    'content' => 'Article 5: Speed limits on highways...',
                ],
            ],
        ];

        $objective = 'What are criminal liability requirements?';

        // Act: Extract insight
        $insight = $this->evaluator->extractInsight($result, $objective);

        // Assert: Should return null for irrelevant results
        $this->assertNull($insight);
    }

    /**
     * Test source relevance scoring
     *
     * Verifies that scoreRelevance() properly scores and sorts sources.
     *
     * @test
     */
    public function test_score_relevance_scores_and_sorts_sources(): void
    {
        // Arrange: Prepare sources with varying relevance
        $query = 'criminal liability requirements';
        $sources = [
            [
                'type' => 'law',
                'content' => 'Traffic regulations and speed limits on highways',
                'title' => 'Traffic Law',
            ],
            [
                'type' => 'law',
                'content' => 'Criminal liability requires intent and criminal act under Article 10',
                'title' => 'Criminal Code',
            ],
            [
                'type' => 'decision',
                'content' => 'Housing contract dispute resolution procedures',
                'title' => 'Civil Court Decision',
            ],
        ];

        // Act: Score relevance
        $scoredSources = $this->evaluator->scoreRelevance($query, $sources);

        // Assert: Verify all sources have scores
        $this->assertCount(3, $scoredSources);
        foreach ($scoredSources as $source) {
            $this->assertArrayHasKey('relevance_score', $source);
            $this->assertIsFloat($source['relevance_score']);
            $this->assertGreaterThanOrEqual(0.0, $source['relevance_score']);
            $this->assertLessThanOrEqual(1.0, $source['relevance_score']);
        }

        // Assert: Verify sources are sorted by relevance (highest first)
        $this->assertGreaterThanOrEqual(
            $scoredSources[1]['relevance_score'],
            $scoredSources[0]['relevance_score'],
            'Sources should be sorted by relevance score (highest first)'
        );
        $this->assertGreaterThanOrEqual(
            $scoredSources[2]['relevance_score'],
            $scoredSources[1]['relevance_score'],
            'Sources should be sorted by relevance score (highest first)'
        );

        // Assert: Criminal Code should have highest relevance
        $this->assertEquals('Criminal Code', $scoredSources[0]['title']);
    }

    /**
     * Test result formatting for extraction
     *
     * @test
     */
    public function test_format_results_for_extraction_handles_multiple_types(): void
    {
        // Arrange: Prepare complex result with multiple types
        $result = [
            'laws' => [
                [
                    'title' => 'Criminal Code',
                    'law_number' => 'NN 125/11',
                    'content' => 'Article 10: Criminal liability exists when...',
                ],
            ],
            'decisions' => [
                [
                    'title' => 'Supreme Court Decision',
                    'court' => 'Vrhovni sud RH',
                    'case_number' => 'Gž-1234/2023',
                    'decision_date' => '2023-05-15',
                ],
            ],
            'cases' => [
                [
                    'title' => 'Criminal Case 123',
                    'case_number' => 'K-123/2023',
                    'status' => 'Active',
                ],
            ],
        ];

        // Act: Format results
        $formatted = $this->evaluator->formatResultsForExtraction($result);

        // Assert: Verify formatted output contains all sections
        $this->assertIsString($formatted);
        $this->assertStringContainsString('## Laws Found:', $formatted);
        $this->assertStringContainsString('Criminal Code', $formatted);
        $this->assertStringContainsString('NN 125/11', $formatted);
        $this->assertStringContainsString('## Court Decisions Found:', $formatted);
        $this->assertStringContainsString('Supreme Court Decision', $formatted);
        $this->assertStringContainsString('Gž-1234/2023', $formatted);
        $this->assertStringContainsString('## Legal Cases Found:', $formatted);
        $this->assertStringContainsString('Criminal Case 123', $formatted);
    }

    /**
     * Test simple insight extraction fallback
     *
     * Verifies that extractSimpleInsight() works without LLM when LLM fails.
     *
     * @test
     */
    public function test_extract_simple_insight_works_without_llm(): void
    {
        // Arrange: Prepare result
        $result = [
            'laws' => [
                ['title' => 'Croatian Criminal Code', 'law_number' => 'NN 125/11'],
            ],
        ];

        // Act: Extract simple insight (no HTTP call should be made)
        $insight = $this->evaluator->extractSimpleInsight($result);

        // Assert: No HTTP calls
        Http::assertNothingSent();

        // Assert: Verify insight
        $this->assertNotNull($insight);
        $this->assertIsString($insight);
        $this->assertStringContainsString('Croatian Criminal Code', $insight);
        $this->assertStringContainsString('NN 125/11', $insight);
    }

    /**
     * Test evaluation with empty answer
     *
     * @test
     */
    public function test_evaluate_empty_answer_returns_zero_score(): void
    {
        // No HTTP mock needed - should not call API for empty answer

        // Act: Evaluate empty answer
        $evaluation = $this->evaluator->evaluate('query', '', []);

        // Assert: No HTTP calls for empty answer
        Http::assertNothingSent();

        // Assert: Zero score
        $this->assertEquals(0.0, $evaluation['quality_score']);
        $this->assertFalse($evaluation['has_citations']);
        $this->assertFalse($evaluation['is_relevant']);
        $this->assertFalse($evaluation['is_actionable']);
        $this->assertEquals('Answer is empty', $evaluation['feedback']);
    }

    /**
     * Test evaluation handles LLM errors gracefully with fallback
     *
     * @test
     */
    public function test_evaluate_handles_llm_errors_with_fallback(): void
    {
        // Arrange: Mock API to fail
        Http::fake([
            'api.openai.com/*' => Http::response(null, 500),
        ]);

        // Arrange: Prepare data
        $query = 'criminal liability';
        $answer = 'Article 10 of Croatian Criminal Code (NN 125/11) requires intent for criminal liability.';
        $sources = [
            ['type' => 'law', 'content' => 'Some content'],
        ];

        // Act: Evaluate (should use fallback)
        $evaluation = $this->evaluator->evaluate($query, $answer, $sources);

        // Assert: Should still return valid structure (fallback)
        $this->assertIsArray($evaluation);
        $this->assertArrayHasKey('quality_score', $evaluation);
        $this->assertArrayHasKey('has_citations', $evaluation);
        $this->assertArrayHasKey('feedback', $evaluation);

        // Assert: Fallback should detect citations
        $this->assertTrue($evaluation['has_citations']);
        $this->assertGreaterThan(0, $evaluation['quality_score']);
        $this->assertEquals('Evaluated using fallback heuristics', $evaluation['feedback']);
    }
}
