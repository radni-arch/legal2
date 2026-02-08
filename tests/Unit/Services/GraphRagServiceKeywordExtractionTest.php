<?php

namespace Tests\Unit\Services;

use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphRagService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * Tests for keyword extraction functionality
 *
 * NOTE: GraphRagService is now a deprecated wrapper that delegates to GraphRagOrchestrator.
 * These tests verify that keyword extraction in the orchestrator works correctly.
 * The extractAndLinkKeywords method is now on GraphRagOrchestrator, not GraphRagService.
 */
class GraphRagServiceKeywordExtractionTest extends TestCase
{
    protected $orchestratorMock;

    protected GraphRagOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure for testing
        Config::set('keywords.use_hybrid', true);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 3);
        Config::set('keywords.log_extraction', false);

        // Create a real orchestrator instance for testing the extractKeywords method
        // We'll test the protected extractKeywords method via reflection
        $this->orchestrator = $this->app->make(GraphRagOrchestrator::class);
    }

    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that extractKeywords (legacy method) extracts and boosts legal terms
     *
     * @test
     */
    public function it_extracts_and_boosts_legal_terms()
    {
        $content = 'Ugovor o radu regulira prava između poslodavca i radnika. Zakon je jasan.';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Should return array with keywords and weights
        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);

        // Legal terms should be present (those with > 3 chars)
        // 'ugovor' has 6 chars, 'zakon' has 5 chars, 'pravo/prava' has 5 chars
        $this->assertArrayHasKey('ugovor', $keywords);
    }

    /**
     * Test that extractKeywords filters out stopwords
     *
     * @test
     */
    public function it_filters_stopwords()
    {
        $content = 'je su biti ima da za na u i ili te se';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Should return empty array (all stopwords)
        $this->assertEmpty($keywords);
    }

    /**
     * Test that extractKeywords respects max keywords limit
     *
     * @test
     */
    public function it_respects_max_keywords_limit()
    {
        $content = str_repeat('Zakon o ugovoru presuda sudski postupak tužba parnica odvjetnik sudac ', 10);

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 5);

        // Should return at most 5 keywords
        $this->assertLessThanOrEqual(5, count($keywords));
    }

    /**
     * Test that extractKeywords normalizes weights to max 1.0
     *
     * @test
     */
    public function it_normalizes_weights()
    {
        $content = 'ugovor ugovor ugovor presuda tužba';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Weights should be normalized (max weight = 1.0)
        foreach ($keywords as $weight) {
            $this->assertLessThanOrEqual(1.0, $weight);
            $this->assertGreaterThan(0, $weight);
        }
    }

    /**
     * Test that extractKeywords filters short words (3 chars or less)
     *
     * @test
     */
    public function it_filters_short_words()
    {
        // "sud" is 3 chars, should be filtered out
        // "zakon" is 5 chars, should be included
        $content = 'sud zakon sud sud zakon';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // 'sud' should NOT be in keywords (only 3 chars)
        $this->assertArrayNotHasKey('sud', $keywords);
        // 'zakon' should be in keywords (5 chars)
        $this->assertArrayHasKey('zakon', $keywords);
    }

    /**
     * Test that extractKeywords handles empty content
     *
     * @test
     */
    public function it_handles_empty_content()
    {
        $content = '';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords);
    }

    /**
     * Test that extractKeywords boosts legal terms higher than regular words
     *
     * @test
     */
    public function it_boosts_legal_terms_higher()
    {
        // 'ugovor' is a legal term with 2.5 boost
        // 'kuća' is a regular word with no boost
        $content = 'ugovor kuća ugovor kuća kuća kuća';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Both should be present
        $this->assertArrayHasKey('ugovor', $keywords);
        $this->assertArrayHasKey('kuća', $keywords);

        // 'ugovor' should have higher weight due to legal term boost
        // 'ugovor' appears 2 times with 2.5 boost = 5.0 effective frequency
        // 'kuća' appears 4 times with no boost = 4.0 effective frequency
        // So ugovor should have higher weight
        $this->assertGreaterThanOrEqual($keywords['kuća'], $keywords['ugovor']);
    }

    /**
     * Test that extractKeywords is case-insensitive
     *
     * @test
     */
    public function it_is_case_insensitive()
    {
        $content = 'UGOVOR Ugovor ugovor';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        // Should have 'ugovor' (lowercase) with combined count
        $this->assertArrayHasKey('ugovor', $keywords);
        // Should only have one entry (all cases combined)
        $this->assertCount(1, $keywords);
    }

    /**
     * Test that extractKeywords returns float weights
     *
     * @test
     */
    public function it_returns_float_weights()
    {
        $content = 'zakon presuda tužitelj';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->orchestrator, $content, 10);

        foreach ($keywords as $word => $weight) {
            $this->assertIsString($word);
            $this->assertIsFloat($weight);
        }
    }
}
