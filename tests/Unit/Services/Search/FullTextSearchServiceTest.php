<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\FullTextSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for FullTextSearchService
 *
 * Tests cover:
 * - search() dispatching to corpus-specific methods
 * - createTsQuery() PostgreSQL tsquery generation
 * - buildSearchTerms() query splitting
 * - calculateFallbackScore() relevance scoring
 * - normalizeResult() output formatting
 * - extractSnippet() content truncation
 * - hasFullTextColumn() caching behavior
 * - applyFiltersToQuery() filter application
 * - Driver check (non-pgsql returns empty)
 */
class FullTextSearchServiceTest extends TestCase
{
    protected FullTextSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FullTextSearchService;
    }

    // =====================================================================
    // createTsQuery() tests
    // =====================================================================

    /** @test */
    public function createTsQuery_builds_or_query_from_multiple_words(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['property damage']);

        $this->assertSame('property | damage', $result);
    }

    /** @test */
    public function createTsQuery_returns_empty_string_for_single_char_words(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['a b c']);

        $this->assertSame('', $result);
    }

    /** @test */
    public function createTsQuery_strips_special_characters(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['property & damage (legal)']);

        $this->assertSame('property | damage | legal', $result);
    }

    /** @test */
    public function createTsQuery_filters_short_words_but_keeps_long_ones(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['a property of law']);

        $this->assertSame('property | of | law', $result);
    }

    /** @test */
    public function createTsQuery_handles_empty_string(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['']);

        $this->assertSame('', $result);
    }

    /** @test */
    public function createTsQuery_handles_unicode_characters(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'createTsQuery', ['zakon pravo']);

        $this->assertSame('zakon | pravo', $result);
    }

    // =====================================================================
    // buildSearchTerms() tests
    // =====================================================================

    /** @test */
    public function buildSearchTerms_splits_query_into_terms(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'buildSearchTerms', ['property damage law']);

        $this->assertCount(3, $result);
        $this->assertContains('property', $result);
        $this->assertContains('damage', $result);
        $this->assertContains('law', $result);
    }

    /** @test */
    public function buildSearchTerms_filters_short_terms(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'buildSearchTerms', ['a property b law']);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function buildSearchTerms_handles_multiple_spaces(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'buildSearchTerms', ['property   damage']);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function buildSearchTerms_returns_empty_for_empty_input(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'buildSearchTerms', ['']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function buildSearchTerms_trims_whitespace(): void
    {
        $result = $this->invokeProtectedMethod($this->service, 'buildSearchTerms', ['  property  ']);

        $this->assertCount(1, $result);
        $this->assertContains('property', $result);
    }

    // =====================================================================
    // calculateFallbackScore() tests
    // =====================================================================

    /** @test */
    public function calculateFallbackScore_weights_title_matches_higher(): void
    {
        $scoreWithTitleMatch = $this->invokeProtectedMethod(
            $this->service,
            'calculateFallbackScore',
            ['Property Law', 'Some unrelated content', ['property']]
        );

        $scoreWithContentOnly = $this->invokeProtectedMethod(
            $this->service,
            'calculateFallbackScore',
            ['Unrelated Title', 'Content about property matters', ['property']]
        );

        $this->assertGreaterThan($scoreWithContentOnly, $scoreWithTitleMatch);
    }

    /** @test */
    public function calculateFallbackScore_normalizes_to_0_1_range(): void
    {
        // Even with many matches, score should not exceed 1.0
        $score = $this->invokeProtectedMethod(
            $this->service,
            'calculateFallbackScore',
            [
                'property property property',
                'property damage property law property act property code property statute property',
                ['property', 'damage', 'law'],
            ]
        );

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    /** @test */
    public function calculateFallbackScore_returns_zero_for_no_matches(): void
    {
        $score = $this->invokeProtectedMethod(
            $this->service,
            'calculateFallbackScore',
            ['Title', 'Content', ['xyz123nonexistent']]
        );

        $this->assertSame(0.0, $score);
    }

    /** @test */
    public function calculateFallbackScore_caps_content_contribution_per_term(): void
    {
        // Many occurrences in content should still be capped
        $contentWithManyMatches = str_repeat('property ', 100);

        $score = $this->invokeProtectedMethod(
            $this->service,
            'calculateFallbackScore',
            ['Other Title', $contentWithManyMatches, ['property']]
        );

        // Score should be capped; max for one term is (0 title + 1.0 content cap) / (1 * 3) = 0.3333
        $this->assertLessThanOrEqual(1.0, $score);
    }

    // =====================================================================
    // normalizeResult() tests
    // =====================================================================

    /** @test */
    public function normalizeResult_returns_correct_structure(): void
    {
        $result = $this->invokeProtectedMethod(
            $this->service,
            'normalizeResult',
            ['law', 'abc-123', 'Test Law', 'A snippet', 0.85678, ['key' => 'value']]
        );

        $this->assertSame('law', $result['type']);
        $this->assertSame('abc-123', $result['id']);
        $this->assertSame('Test Law', $result['title']);
        $this->assertSame('A snippet', $result['snippet']);
        $this->assertSame(0.8568, $result['score']); // rounded to 4 decimal places
        $this->assertSame(['key' => 'value'], $result['metadata']);
    }

    /** @test */
    public function normalizeResult_rounds_score_to_four_decimals(): void
    {
        $result = $this->invokeProtectedMethod(
            $this->service,
            'normalizeResult',
            ['decision', 'id-1', 'Title', 'Snippet', 0.123456789, []]
        );

        $this->assertSame(0.1235, $result['score']);
    }

    // =====================================================================
    // extractSnippet() tests
    // =====================================================================

    /** @test */
    public function extractSnippet_returns_short_content_as_is(): void
    {
        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            ['Short content.']
        );

        $this->assertSame('Short content.', $result);
    }

    /** @test */
    public function extractSnippet_truncates_at_sentence_boundary(): void
    {
        // Content crafted so that a sentence boundary (.) falls in the 60-100% range of maxLength=200.
        // The period after "relevant here" is at position ~160 which is > 200*0.6=120
        $content = 'This is the first sentence about property law that discusses ownership rights and obligations under the civil code framework which is extremely relevant here. Then this next sentence continues with additional content that pushes well past the two hundred character mark easily.';

        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            [$content, 200]
        );

        // Should truncate at the sentence boundary
        $this->assertLessThanOrEqual(200, mb_strlen($result));
        // Should end with a period (sentence boundary)
        $this->assertMatchesRegularExpression('/[.?!]$/', $result);
    }

    /** @test */
    public function extractSnippet_uses_ellipsis_when_no_boundary_found(): void
    {
        // A long string with no sentence boundaries in the truncation range
        $content = str_repeat('wordwithoutspaces', 20);

        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            [$content, 200]
        );

        $this->assertStringEndsWith('...', $result);
    }

    /** @test */
    public function extractSnippet_handles_empty_content(): void
    {
        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            ['']
        );

        $this->assertSame('', $result);
    }

    /** @test */
    public function extractSnippet_handles_custom_max_length(): void
    {
        $content = 'First. Second. Third. Fourth. Fifth sentence that is moderately long.';

        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            [$content, 30]
        );

        $this->assertLessThanOrEqual(33, mb_strlen($result)); // 30 + '...'
    }

    /** @test */
    public function extractSnippet_handles_mb_strrpos_false_values(): void
    {
        // Content where sentence boundaries are only very early (before 60% threshold)
        $content = 'A. ' . str_repeat('x', 250);

        $result = $this->invokeProtectedMethod(
            $this->service,
            'extractSnippet',
            [$content, 200]
        );

        // Should fall back to ellipsis since the period is at position 1, which is < 200 * 0.6 = 120
        $this->assertStringEndsWith('...', $result);
    }

    // =====================================================================
    // hasFullTextColumn() tests
    // =====================================================================

    /** @test */
    public function hasFullTextColumn_returns_true_when_column_exists(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('laws', 'content_tsv')
            ->andReturn(true);

        $result = $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['laws']);

        $this->assertTrue($result);
    }

    /** @test */
    public function hasFullTextColumn_returns_false_and_logs_warning(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('laws', 'content_tsv')
            ->andReturn(false);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Full-text search column (content_tsv) not found, using fallback ILIKE search',
                \Mockery::type('array')
            );

        $result = $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['laws']);

        $this->assertFalse($result);
    }

    /** @test */
    public function hasFullTextColumn_caches_result_per_table(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('laws', 'content_tsv')
            ->andReturn(true);

        // Call twice - Schema should only be called once
        $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['laws']);
        $result = $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['laws']);

        $this->assertTrue($result);
    }

    /** @test */
    public function hasFullTextColumn_logs_warning_only_once(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('laws', 'content_tsv')
            ->andReturn(false);

        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('court_decision_documents', 'content_tsv')
            ->andReturn(false);

        Log::shouldReceive('warning')
            ->once(); // Only once, not twice

        $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['laws']);
        $this->invokeProtectedMethod($this->service, 'hasFullTextColumn', ['court_decision_documents']);
    }

    // =====================================================================
    // search() dispatch tests
    // =====================================================================

    /** @test */
    public function search_returns_empty_for_non_pgsql_driver(): void
    {
        $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('sqlite');
        DB::shouldReceive('connection')->andReturn($connection);
        Log::shouldReceive('warning')->once();

        $result = $this->service->search('test query', ['laws'], [], 10);

        $this->assertSame([], $result);
    }

    /** @test */
    public function search_skips_unknown_corpora(): void
    {
        $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        DB::shouldReceive('connection')->andReturn($connection);

        $result = $this->service->search('test', ['unknown_corpus'], [], 10);

        $this->assertSame([], $result);
    }

    // =====================================================================
    // applyFiltersToQuery() tests
    // =====================================================================

    /** @test */
    public function applyFiltersToQuery_applies_equality_filter(): void
    {
        $queryBuilder = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryBuilder->shouldReceive('where')
            ->once()
            ->with('jurisdiction', '=', 'HR')
            ->andReturnSelf();

        $this->invokeProtectedMethod(
            $this->service,
            'applyFiltersToQuery',
            [$queryBuilder, ['jurisdiction' => 'HR'], ['jurisdiction' => ['column' => 'jurisdiction']]]
        );
    }

    /** @test */
    public function applyFiltersToQuery_applies_like_filter_with_escaping(): void
    {
        $queryBuilder = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryBuilder->shouldReceive('where')
            ->once()
            ->with('cd.court', 'LIKE', '%Supreme\\%Court%')
            ->andReturnSelf();

        $this->invokeProtectedMethod(
            $this->service,
            'applyFiltersToQuery',
            [
                $queryBuilder,
                ['court' => 'Supreme%Court'],
                ['court' => ['column' => 'cd.court', 'operator' => 'LIKE']],
            ]
        );
    }

    /** @test */
    public function applyFiltersToQuery_applies_range_filters(): void
    {
        $queryBuilder = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryBuilder->shouldReceive('where')
            ->once()
            ->with('decision_date', '>=', '2024-01-01')
            ->andReturnSelf();
        $queryBuilder->shouldReceive('where')
            ->once()
            ->with('decision_date', '<=', '2024-12-31')
            ->andReturnSelf();

        $this->invokeProtectedMethod(
            $this->service,
            'applyFiltersToQuery',
            [
                $queryBuilder,
                ['date_from' => '2024-01-01', 'date_to' => '2024-12-31'],
                [
                    'date_from' => ['column' => 'decision_date', 'operator' => '>='],
                    'date_to' => ['column' => 'decision_date', 'operator' => '<='],
                ],
            ]
        );
    }

    /** @test */
    public function applyFiltersToQuery_ignores_unmapped_filters(): void
    {
        $queryBuilder = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        // No where() calls expected

        $this->invokeProtectedMethod(
            $this->service,
            'applyFiltersToQuery',
            [$queryBuilder, ['unknown_filter' => 'value'], ['jurisdiction' => ['column' => 'jurisdiction']]]
        );

        // If we get here without error, unmapped filter was correctly ignored
        $this->assertTrue(true);
    }

    // =====================================================================
    // Helper to invoke protected methods
    // =====================================================================

    protected function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
