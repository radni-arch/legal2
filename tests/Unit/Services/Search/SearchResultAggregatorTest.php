<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\SearchResultAggregator;
use Tests\TestCase;

/**
 * Tests for SearchResultAggregator
 */
class SearchResultAggregatorTest extends TestCase
{
    protected SearchResultAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new SearchResultAggregator;
    }

    /** @test */
    public function it_aggregates_results_basic()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.9, 'content' => 'Law 1'],
                ['id' => 'law-2', 'score' => 0.8, 'content' => 'Law 2'],
            ],
            'cases' => [
                ['id' => 'case-1', 'score' => 0.85, 'content' => 'Case 1'],
            ],
        ];

        $result = $this->aggregator->aggregate($corpusResults);

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_sorts_results_by_score_descending()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.7, 'content' => 'Law 1'],
                ['id' => 'law-2', 'score' => 0.9, 'content' => 'Law 2'],
            ],
            'cases' => [
                ['id' => 'case-1', 'score' => 0.8, 'content' => 'Case 1'],
            ],
        ];

        $result = $this->aggregator->aggregateAndSort($corpusResults);

        $this->assertEquals('law-2', $result[0]['id']); // 0.9
        $this->assertEquals('case-1', $result[1]['id']); // 0.8
        $this->assertEquals('law-1', $result[2]['id']); // 0.7
    }

    /** @test */
    public function it_applies_corpus_weights_to_scores()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.8, 'content' => 'Law 1'],
            ],
            'cases' => [
                ['id' => 'case-1', 'score' => 0.8, 'content' => 'Case 1'],
            ],
        ];

        $weights = [
            'laws' => 1.2,
            'cases' => 0.9,
        ];

        $result = $this->aggregator->aggregate($corpusResults, $weights);

        // Law: 0.8 * 1.2 = 0.96, Case: 0.8 * 0.9 = 0.72
        // aggregate() preserves corpus order (laws first, then cases)
        $this->assertEquals('law-1', $result[0]['id']);
        $this->assertEqualsWithDelta(0.96, $result[0]['score'], 0.0001);
        $this->assertEquals('case-1', $result[1]['id']);
        $this->assertEqualsWithDelta(0.72, $result[1]['score'], 0.0001);
    }

    /** @test */
    public function it_preserves_original_score_when_applying_weights()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.8, 'content' => 'Law 1'],
            ],
        ];

        $weights = ['laws' => 1.5];

        $result = $this->aggregator->aggregate($corpusResults, $weights);

        $this->assertEquals(0.8, $result[0]['raw_score']);
        $this->assertEqualsWithDelta(1.2, $result[0]['score'], 0.0001); // 0.8 * 1.5
        $this->assertEquals(1.5, $result[0]['corpus_weight']);
    }

    /** @test */
    public function it_uses_default_weight_of_one_when_not_specified()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.8, 'content' => 'Law 1'],
            ],
        ];

        $result = $this->aggregator->aggregate($corpusResults);

        $this->assertEqualsWithDelta(0.8, $result[0]['score'], 0.0001);
        $this->assertEquals(1.0, $result[0]['corpus_weight']);
    }

    /** @test */
    public function it_handles_empty_arrays_in_corpora()
    {
        $corpusResults = [
            'laws' => [],
            'cases' => [],
        ];

        $result = $this->aggregator->aggregate($corpusResults);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_results_without_scores()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.0, 'content' => 'Law 1'],
            ],
        ];

        $result = $this->aggregator->aggregate($corpusResults);

        $this->assertCount(1, $result);
        $this->assertEquals(0.0, $result[0]['score']);
    }

    /** @test */
    public function it_generates_statistics_for_aggregated_results()
    {
        $aggregated = [
            ['id' => 'law-1', 'corpus' => 'laws', 'weighted_score' => 0.9],
            ['id' => 'law-2', 'corpus' => 'laws', 'weighted_score' => 0.8],
            ['id' => 'case-1', 'corpus' => 'cases', 'weighted_score' => 0.85],
        ];

        $stats = $this->aggregator->getStatistics($aggregated);

        $this->assertArrayHasKey('laws', $stats);
        $this->assertArrayHasKey('cases', $stats);
        $this->assertEquals(2, $stats['laws']['count']);
        $this->assertEquals(1, $stats['cases']['count']);
        $this->assertEqualsWithDelta(0.85, $stats['laws']['avg_score'], 0.0001);
        $this->assertEqualsWithDelta(0.9, $stats['laws']['max_score'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $stats['laws']['min_score'], 0.0001);
    }

    /** @test */
    public function it_handles_mixed_weighted_and_unweighted_results()
    {
        $corpusResults = [
            'laws' => [
                ['id' => 'law-1', 'score' => 0.9],
                ['id' => 'law-2', 'score' => 0.7],
            ],
            'cases' => [
                ['id' => 'case-1', 'score' => 0.8],
            ],
        ];

        $weights = ['laws' => 1.1]; // Only weight laws, not cases

        $result = $this->aggregator->aggregateAndSort($corpusResults, $weights);

        // law-1: 0.9 * 1.1 = 0.99
        // case-1: 0.8 * 1.0 = 0.8
        // law-2: 0.7 * 1.1 = 0.77
        $this->assertEquals('law-1', $result[0]['id']);
        $this->assertEquals('case-1', $result[1]['id']);
        $this->assertEquals('law-2', $result[2]['id']);
    }

    /**
     * Test 1: It aggregates results from multiple corpora
     *
     * @test
     */
    public function it_aggregates_results_from_multiple_corpora()
    {
        // Arrange: Create results from multiple corpora
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
                ['id' => 'law2', 'score' => 0.8, 'type' => 'law'],
            ],
            'decisions' => [
                ['id' => 'dec1', 'score' => 0.85, 'type' => 'decision'],
            ],
            'cases' => [
                ['id' => 'case1', 'score' => 0.75, 'type' => 'case'],
            ],
        ];

        // Act: Aggregate results
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Should have all results merged
        $this->assertCount(4, $results);
        $this->assertIsArray($results);
    }

    /**
     * Test 2: It applies corpus weights to scores
     *
     * @test
     */
    public function it_applies_corpus_weights()
    {
        // Arrange: Create results with different weights
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.8, 'type' => 'law'],
            ],
            'decisions' => [
                ['id' => 'dec1', 'score' => 0.8, 'type' => 'decision'],
            ],
        ];

        $weights = [
            'laws' => 2.0,
            'decisions' => 0.5,
        ];

        // Act: Aggregate with weights
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: Scores should be weighted
        $lawResult = collect($results)->firstWhere('id', 'law1');
        $decisionResult = collect($results)->firstWhere('id', 'dec1');

        $this->assertEquals(1.6, $lawResult['score']); // 0.8 * 2.0
        $this->assertEquals(0.4, $decisionResult['score']); // 0.8 * 0.5
    }

    /**
     * Test 3: It preserves raw scores
     *
     * @test
     */
    public function it_preserves_raw_scores()
    {
        // Arrange: Create results
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.85, 'type' => 'law'],
            ],
        ];

        $weights = ['laws' => 1.5];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: Should preserve raw_score
        $this->assertArrayHasKey('raw_score', $results[0]);
        $this->assertEquals(0.85, $results[0]['raw_score']);
        $this->assertEquals(0.85 * 1.5, $results[0]['score']);
    }

    /**
     * Test 4: It adds corpus_weight metadata
     *
     * @test
     */
    public function it_adds_corpus_weight_metadata()
    {
        // Arrange: Create results
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
            ],
        ];

        $weights = ['laws' => 2.5];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: Should include corpus_weight
        $this->assertArrayHasKey('corpus_weight', $results[0]);
        $this->assertEquals(2.5, $results[0]['corpus_weight']);
    }

    /**
     * Test 5: It uses default weight of 1.0 for missing weights
     *
     * @test
     */
    public function it_uses_default_weight_for_missing_weights()
    {
        // Arrange: Create results without specifying weights
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.8, 'type' => 'law'],
            ],
            'decisions' => [
                ['id' => 'dec1', 'score' => 0.7, 'type' => 'decision'],
            ],
        ];

        $weights = ['laws' => 1.5]; // Only laws has weight

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: decisions should use default weight 1.0
        $lawResult = collect($results)->firstWhere('id', 'law1');
        $decisionResult = collect($results)->firstWhere('id', 'dec1');

        $this->assertEqualsWithDelta(1.2, $lawResult['score'], 0.0001); // 0.8 * 1.5
        $this->assertEqualsWithDelta(0.7, $decisionResult['score'], 0.0001); // 0.7 * 1.0
        $this->assertEquals(1.0, $decisionResult['corpus_weight']);
    }

    /**
     * Test 6: It handles empty corpus results
     *
     * @test
     */
    public function it_handles_empty_corpus_results()
    {
        // Arrange: Empty results
        $corpusResults = [];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Should return empty array
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test 7: It handles corpus with empty results array
     *
     * @test
     */
    public function it_handles_corpus_with_empty_results()
    {
        // Arrange: One corpus has empty results
        $corpusResults = [
            'laws' => [],
            'decisions' => [
                ['id' => 'dec1', 'score' => 0.9, 'type' => 'decision'],
            ],
        ];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Should only have decision result
        $this->assertCount(1, $results);
        $this->assertEquals('dec1', $results[0]['id']);
    }

    /**
     * Test 8: It handles single corpus
     *
     * @test
     */
    public function it_handles_single_corpus()
    {
        // Arrange: Single corpus
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
                ['id' => 'law2', 'score' => 0.8, 'type' => 'law'],
            ],
        ];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Should have both results
        $this->assertCount(2, $results);
    }

    /**
     * Test 9: It preserves all original fields
     *
     * @test
     */
    public function it_preserves_original_fields()
    {
        // Arrange: Results with various fields
        $corpusResults = [
            'laws' => [
                [
                    'id' => 'law1',
                    'score' => 0.9,
                    'type' => 'law',
                    'title' => 'Test Law',
                    'snippet' => 'Test snippet',
                    'metadata' => ['foo' => 'bar'],
                ],
            ],
        ];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: All fields should be preserved
        $this->assertEquals('law1', $results[0]['id']);
        $this->assertEquals('law', $results[0]['type']);
        $this->assertEquals('Test Law', $results[0]['title']);
        $this->assertEquals('Test snippet', $results[0]['snippet']);
        $this->assertEquals(['foo' => 'bar'], $results[0]['metadata']);
    }

    /**
     * Test 10: It handles zero weight
     *
     * @test
     */
    public function it_handles_zero_weight()
    {
        // Arrange: Create results with zero weight
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
            ],
        ];

        $weights = ['laws' => 0.0];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: Score should be 0
        $this->assertEquals(0.0, $results[0]['score']);
        $this->assertEquals(0.9, $results[0]['raw_score']);
        $this->assertEquals(0.0, $results[0]['corpus_weight']);
    }

    /**
     * Test 11: It handles negative weight
     *
     * @test
     */
    public function it_handles_negative_weight()
    {
        // Arrange: Create results with negative weight
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.8, 'type' => 'law'],
            ],
        ];

        $weights = ['laws' => -1.0];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults, $weights);

        // Assert: Score should be negative (though unusual, it's allowed)
        $this->assertEquals(-0.8, $results[0]['score']);
        $this->assertEquals(0.8, $results[0]['raw_score']);
        $this->assertEquals(-1.0, $results[0]['corpus_weight']);
    }

    /**
     * Test 12: It maintains result order within corpus
     *
     * @test
     */
    public function it_maintains_result_order_within_corpus()
    {
        // Arrange: Create results in specific order
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
                ['id' => 'law2', 'score' => 0.8, 'type' => 'law'],
                ['id' => 'law3', 'score' => 0.7, 'type' => 'law'],
            ],
        ];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Order should be maintained
        $this->assertEquals('law1', $results[0]['id']);
        $this->assertEquals('law2', $results[1]['id']);
        $this->assertEquals('law3', $results[2]['id']);
    }

    /**
     * Test 13: It maintains cross-corpus order
     *
     * @test
     */
    public function it_maintains_cross_corpus_order()
    {
        // Arrange: Multiple corpora
        $corpusResults = [
            'laws' => [
                ['id' => 'law1', 'score' => 0.9, 'type' => 'law'],
            ],
            'decisions' => [
                ['id' => 'dec1', 'score' => 0.85, 'type' => 'decision'],
            ],
            'cases' => [
                ['id' => 'case1', 'score' => 0.8, 'type' => 'case'],
            ],
        ];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Results should be in corpus order
        $this->assertEquals('law1', $results[0]['id']);
        $this->assertEquals('dec1', $results[1]['id']);
        $this->assertEquals('case1', $results[2]['id']);
    }

    /**
     * Test 14: It handles large number of results
     *
     * @test
     */
    public function it_handles_large_number_of_results()
    {
        // Arrange: Create many results
        $lawResults = [];
        for ($i = 0; $i < 100; $i++) {
            $lawResults[] = [
                'id' => "law{$i}",
                'score' => 0.9 - ($i * 0.001),
                'type' => 'law',
            ];
        }

        $corpusResults = ['laws' => $lawResults];

        // Act: Aggregate
        $aggregator = new SearchResultAggregator;
        $results = $aggregator->aggregate($corpusResults);

        // Assert: Should handle all results
        $this->assertCount(100, $results);
        $this->assertEquals('law0', $results[0]['id']);
        $this->assertEquals('law99', $results[99]['id']);
    }

    /**
     * Test 15: It can be instantiated via container
     *
     * @test
     */
    public function it_can_be_instantiated_via_container()
    {
        // Act: Resolve from container
        $aggregator = app(SearchResultAggregator::class);

        // Assert: Should be instance
        $this->assertInstanceOf(SearchResultAggregator::class, $aggregator);
    }
}
