<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\SearchResultDeduplicator;
use Tests\TestCase;

/**
 * Tests for SearchResultDeduplicator
 */
class SearchResultDeduplicatorTest extends TestCase
{
    protected SearchResultDeduplicator $deduplicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deduplicator = new SearchResultDeduplicator;
    }

    /** @test */
    public function it_removes_exact_duplicate_ids_with_strict_strategy()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'Content 1'],
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => 'Content 2'],
            ['id' => 'doc-1', 'weighted_score' => 0.7, 'content' => 'Content 1'], // Duplicate
        ];

        $deduplicated = $this->deduplicator->deduplicate($results, ['strategy' => 'strict']);

        $this->assertCount(2, $deduplicated);
        $ids = array_column($deduplicated, 'id');
        $this->assertContains('doc-1', $ids);
        $this->assertContains('doc-2', $ids);
    }

    /** @test */
    public function it_keeps_highest_scoring_duplicate_by_default()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.7, 'content' => 'Content 1'],
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'Content 1'], // Higher score
        ];

        $deduplicated = $this->deduplicator->deduplicate($results);

        $this->assertCount(1, $deduplicated);
        $this->assertEquals(0.9, $deduplicated[0]['weighted_score']);
    }

    /** @test */
    public function it_keeps_first_occurrence_when_keep_highest_score_is_false()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.7, 'content' => 'Content 1'],
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'Content 1'],
        ];

        $deduplicated = $this->deduplicator->deduplicate($results, ['keep_highest_score' => false]);

        $this->assertCount(1, $deduplicated);
        $this->assertEquals(0.7, $deduplicated[0]['weighted_score']); // First occurrence
    }

    /** @test */
    public function it_preserves_results_without_ids()
    {
        $results = [
            ['weighted_score' => 0.9, 'content' => 'Content 1'], // No ID
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => 'Content 2'],
            ['weighted_score' => 0.7, 'content' => 'Content 3'], // No ID
        ];

        $deduplicated = $this->deduplicator->deduplicate($results);

        $this->assertCount(3, $deduplicated);
    }

    /** @test */
    public function it_uses_fuzzy_deduplication_for_similar_content()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'The quick brown fox'],
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => 'the    quick   brown   fox'], // Same normalized
            ['id' => 'doc-3', 'weighted_score' => 0.7, 'content' => 'A different document'],
        ];

        $deduplicated = $this->deduplicator->deduplicate($results, ['strategy' => 'fuzzy']);

        // Should remove doc-2 as it's similar to doc-1
        $this->assertCount(2, $deduplicated);
        $contents = array_column($deduplicated, 'content');
        $this->assertContains('The quick brown fox', $contents);
        $this->assertContains('A different document', $contents);
    }

    /** @test */
    public function it_handles_empty_results_basic()
    {
        $results = [];

        $deduplicated = $this->deduplicator->deduplicate($results);

        $this->assertIsArray($deduplicated);
        $this->assertEmpty($deduplicated);
    }

    /** @test */
    public function it_handles_results_with_no_duplicates()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'Content 1'],
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => 'Content 2'],
            ['id' => 'doc-3', 'weighted_score' => 0.7, 'content' => 'Content 3'],
        ];

        $deduplicated = $this->deduplicator->deduplicate($results);

        $this->assertCount(3, $deduplicated);
    }

    /** @test */
    public function it_generates_deduplication_statistics()
    {
        $original = [
            ['id' => 'doc-1', 'weighted_score' => 0.9],
            ['id' => 'doc-1', 'weighted_score' => 0.8],
            ['id' => 'doc-2', 'weighted_score' => 0.7],
        ];

        $deduplicated = [
            ['id' => 'doc-1', 'weighted_score' => 0.9],
            ['id' => 'doc-2', 'weighted_score' => 0.7],
        ];

        $stats = $this->deduplicator->getStatistics($original, $deduplicated);

        $this->assertEquals(3, $stats['original_count']);
        $this->assertEquals(2, $stats['final_count']);
        $this->assertEquals(1, $stats['removed_count']);
        $this->assertEquals(33.33, $stats['removal_percentage']);
    }

    /** @test */
    public function it_handles_multiple_duplicates_of_same_id()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9],
            ['id' => 'doc-1', 'weighted_score' => 0.95], // Highest
            ['id' => 'doc-1', 'weighted_score' => 0.8],
            ['id' => 'doc-2', 'weighted_score' => 0.7],
        ];

        $deduplicated = $this->deduplicator->deduplicate($results);

        $this->assertCount(2, $deduplicated);
        // Should keep doc-1 with score 0.95
        $doc1 = array_values(array_filter($deduplicated, fn ($r) => $r['id'] === 'doc-1'))[0];
        $this->assertEquals(0.95, $doc1['weighted_score']);
    }

    /** @test */
    public function it_normalizes_content_for_fuzzy_matching()
    {
        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => 'TEST   CONTENT'],
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => 'test content'], // Same normalized
        ];

        $deduplicated = $this->deduplicator->deduplicate($results, ['strategy' => 'fuzzy']);

        // Should recognize as duplicate despite case and whitespace differences
        $this->assertCount(1, $deduplicated);
        $this->assertEquals(0.9, $deduplicated[0]['weighted_score']); // Keeps higher score
    }

    /** @test */
    public function it_uses_content_sample_for_fuzzy_hashing()
    {
        // Create two documents with same beginning but different endings
        $longContent1 = str_repeat('Same beginning content ', 100).' Different ending A';
        $longContent2 = str_repeat('Same beginning content ', 100).' Different ending B';

        $results = [
            ['id' => 'doc-1', 'weighted_score' => 0.9, 'content' => $longContent1],
            ['id' => 'doc-2', 'weighted_score' => 0.8, 'content' => $longContent2],
        ];

        $deduplicated = $this->deduplicator->deduplicate($results, ['strategy' => 'fuzzy']);

        // Should be treated as duplicates since first 500 chars are same
        $this->assertCount(1, $deduplicated);
    }
}
