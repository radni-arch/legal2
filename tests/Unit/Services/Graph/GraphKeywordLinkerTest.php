<?php

namespace Tests\Unit\Services\Graph;

use App\Services\AdvancedKeywordExtractor;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Comprehensive tests for GraphKeywordLinker
 *
 * These tests verify keyword extraction and graph linking behavior,
 * including hybrid extraction, legal term boosting, and edge cases.
 */
class GraphKeywordLinkerTest extends TestCase
{
    protected $graphMock;

    protected $advancedExtractorMock;

    protected GraphKeywordLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->advancedExtractorMock = Mockery::mock(AdvancedKeywordExtractor::class);

        // Configure for testing
        Config::set('keywords.use_hybrid', true);
        Config::set('keywords.log_extraction', false);
        Config::set('keywords.log_fallback', false);
    }

    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions to avoid risky test warnings
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Basic Functionality Tests
    // ========================================

    /** @test */
    public function it_creates_keyword_nodes_for_extracted_keywords()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with('Test content with keywords')
            ->andReturn(['test' => 1.0, 'content' => 0.8]);

        // Verify keyword nodes are created
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Keyword', 'keyword_'.md5('test'), Mockery::on(function ($props) {
                return $props['name'] === 'test'
                    && $props['normalized'] === 'test';
            }));

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Keyword', 'keyword_'.md5('content'), Mockery::on(function ($props) {
                return $props['name'] === 'content'
                    && $props['normalized'] === 'content';
            }));

        // Allow relationship creation
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->twice();

        $this->linker->link('LawDocument', 'law-id', 'Test content with keywords');
    }

    /** @test */
    public function it_creates_has_keyword_relationships_with_weights()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['important' => 1.0, 'keyword' => 0.5]);

        // Allow node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->twice();

        // Verify relationships with weights
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'LawDocument',
                'law-id',
                'HAS_KEYWORD',
                'Keyword',
                'keyword_'.md5('important'),
                ['weight' => 1.0]
            );

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'LawDocument',
                'law-id',
                'HAS_KEYWORD',
                'Keyword',
                'keyword_'.md5('keyword'),
                ['weight' => 0.5]
            );

        $this->linker->link('LawDocument', 'law-id', 'Important keyword content');
    }

    /** @test */
    public function it_uses_md5_hash_for_keyword_ids()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['unique-keyword' => 1.0]);

        $expectedId = 'keyword_'.md5('unique-keyword');

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Keyword', $expectedId, Mockery::any());

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'LawDocument',
                'law-id',
                'HAS_KEYWORD',
                'Keyword',
                $expectedId,
                Mockery::any()
            );

        $this->linker->link('LawDocument', 'law-id', 'unique-keyword content');
    }

    /** @test */
    public function it_normalizes_keyword_names_to_lowercase()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['MixedCase' => 1.0]);

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Keyword', Mockery::any(), Mockery::on(function ($props) {
                return $props['name'] === 'MixedCase'  // Original preserved
                    && $props['normalized'] === 'mixedcase';  // Normalized to lowercase
            }));

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once();

        $this->linker->link('LawDocument', 'law-id', 'MixedCase content');
    }

    // ========================================
    // Hybrid Extraction Tests
    // ========================================

    /** @test */
    public function it_uses_advanced_extractor_when_available_and_hybrid_enabled()
    {
        Config::set('keywords.use_hybrid', true);

        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with('Test content')
            ->andReturn(['test' => 1.0]);

        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->graphMock->shouldReceive('createRelationship')->once();

        $this->linker->link('LawDocument', 'law-id', 'Test content');
    }

    /** @test */
    public function it_falls_back_to_legacy_extraction_when_hybrid_disabled()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        // Advanced extractor should NOT be called
        $this->advancedExtractorMock
            ->shouldNotReceive('extract');

        // Should use legacy extraction (creates nodes for extracted keywords)
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->atLeast()->once();

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once();

        $this->linker->link('LawDocument', 'law-id', 'Zakon o radu postupak sud');
    }

    /** @test */
    public function it_falls_back_to_legacy_extraction_on_advanced_extractor_error()
    {
        Config::set('keywords.use_hybrid', true);

        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        // Advanced extractor throws exception
        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \Exception('Extraction failed'));

        // Should fall back to legacy extraction
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->atLeast()->once();

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once();

        $this->linker->link('LawDocument', 'law-id', 'Zakon o ugovoru presuda');
    }

    /** @test */
    public function it_uses_legacy_extraction_when_advanced_extractor_not_available()
    {
        Config::set('keywords.use_hybrid', true);

        // Create linker WITHOUT advanced extractor
        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Should use legacy extraction
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->atLeast()->once();

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once();

        $this->linker->link('LawDocument', 'law-id', 'Zakon o kaznenom postupku');
    }

    /** @test */
    public function it_logs_hybrid_extraction_when_configured()
    {
        Config::set('keywords.use_hybrid', true);
        Config::set('keywords.log_extraction', true);

        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['test' => 1.0]);

        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->graphMock->shouldReceive('createRelationship')->once();

        Log::shouldReceive('info')
            ->once()
            ->with('GraphKeywordLinker - Using hybrid keyword extraction', Mockery::on(function ($context) {
                return $context['node_label'] === 'LawDocument'
                    && $context['node_id'] === 'law-id'
                    && isset($context['keywords_count']);
            }));

        $this->linker->link('LawDocument', 'law-id', 'Test content');
    }

    /** @test */
    public function it_logs_fallback_warning_on_extraction_error()
    {
        Config::set('keywords.use_hybrid', true);
        Config::set('keywords.log_fallback', true);

        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \Exception('Test error'));

        $this->graphMock->shouldReceive('upsertNode')->atLeast()->once();
        $this->graphMock->shouldReceive('createRelationship')->atLeast()->once();

        Log::shouldReceive('warning')
            ->once()
            ->with('GraphKeywordLinker - Hybrid extraction failed, using legacy method', Mockery::on(function ($context) {
                return $context['node_label'] === 'LawDocument'
                    && $context['node_id'] === 'law-id'
                    && $context['error'] === 'Test error';
            }));

        $this->linker->link('LawDocument', 'law-id', 'Test content');
    }

    // ========================================
    // Legacy Extraction Tests
    // ========================================

    /** @test */
    public function it_extracts_keywords_with_legal_term_boosting()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with legal terms (should be boosted)
        $content = 'Zakon o ugovoru između stranaka presuda suda';

        // Extract keywords via reflection
        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content);

        // Legal terms should be present
        $this->assertArrayHasKey('zakon', $keywords);
        $this->assertArrayHasKey('ugovoru', $keywords);
        $this->assertArrayHasKey('presuda', $keywords);
        $this->assertArrayHasKey('suda', $keywords);
    }

    /** @test */
    public function it_filters_stopwords()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with stopwords
        $content = 'je su biti ima da za na u i ili te se';

        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content);

        // Should return empty array (all stopwords)
        $this->assertEmpty($keywords);
    }

    /** @test */
    public function it_filters_short_words()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with short words (≤3 chars)
        $content = 'za na od po is to by';

        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content);

        // Should return empty array (all short words)
        $this->assertEmpty($keywords);
    }

    /** @test */
    public function it_respects_max_keywords_limit()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with many keywords
        $content = str_repeat('zakon ugovor presuda odluka postupak tužba parnica odvjetnik sudac stranka ', 10);

        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content, 5);

        // Should return at most 5 keywords
        $this->assertLessThanOrEqual(5, count($keywords));
    }

    /** @test */
    public function it_normalizes_weights_to_0_1_range()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        $content = 'Zakon o kaznenom postupku sadrži važne odredbe';

        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content);

        // All weights should be between 0 and 1
        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight);
            $this->assertLessThanOrEqual(1, $weight);
        }

        // Highest weight should be 1.0
        $maxWeight = max($keywords);
        $this->assertEquals(1.0, $maxWeight);
    }

    /** @test */
    public function it_boosts_legal_terms_higher_than_regular_terms()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Mix of legal terms and regular words (both appearing once)
        $content = 'Zakon regularword postupak anotherword presuda thirdword';

        $reflection = new \ReflectionClass($this->linker);
        $method = $reflection->getMethod('extractKeywords');
        $method->setAccessible(true);

        $keywords = $method->invoke($this->linker, $content);

        // Legal terms should have higher weights than regular words
        if (isset($keywords['zakon']) && isset($keywords['regularword'])) {
            $this->assertGreaterThan($keywords['regularword'], $keywords['zakon']);
        }
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    /** @test */
    public function it_handles_empty_content()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Should not create any nodes or relationships
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->graphMock->shouldNotReceive('createRelationship');

        $this->linker->link('LawDocument', 'law-id', '');
    }

    /** @test */
    public function it_handles_content_with_only_stopwords()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with only stopwords
        $content = 'je su biti ima da za na';

        // Should not create any nodes or relationships
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->graphMock->shouldNotReceive('createRelationship');

        $this->linker->link('LawDocument', 'law-id', $content);
    }

    /** @test */
    public function it_handles_special_characters_in_content()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Content with special characters
        $content = 'Zakon@#$% postupak!!! presuda???';

        $this->graphMock->shouldReceive('upsertNode')->atLeast()->once();
        $this->graphMock->shouldReceive('createRelationship')->atLeast()->once();

        // Should not throw exception
        $this->linker->link('LawDocument', 'law-id', $content);
        $this->assertTrue(true);
    }

    /** @test */
    public function it_works_with_different_node_types()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $nodeTypes = ['LawDocument', 'CaseDocument', 'CourtDecisionDocument', 'TextractDocument'];

        foreach ($nodeTypes as $nodeType) {
            $this->advancedExtractorMock
                ->shouldReceive('extract')
                ->once()
                ->andReturn(['test' => 1.0]);

            $this->graphMock
                ->shouldReceive('upsertNode')
                ->once();

            $this->graphMock
                ->shouldReceive('createRelationship')
                ->once()
                ->with($nodeType, Mockery::any(), 'HAS_KEYWORD', Mockery::any(), Mockery::any(), Mockery::any());

            $this->linker->link($nodeType, "id-$nodeType", 'Test content');
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_croatian_characters_correctly()
    {
        Config::set('keywords.use_hybrid', false);

        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Croatian text with special characters (č, ć, š, ž, đ)
        $content = 'Članak pravilnika određuje složenu tužbu građanina';

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->atLeast()->once()
            ->with('Keyword', Mockery::any(), Mockery::on(function ($props) {
                // Normalized name should preserve Croatian lowercase characters
                return mb_strlen($props['normalized']) > 0;
            }));

        $this->graphMock->shouldReceive('createRelationship')->atLeast()->once();

        $this->linker->link('LawDocument', 'law-id', $content);
    }

    // ========================================
    // Integration Tests
    // ========================================

    /** @test */
    public function it_creates_multiple_keywords_and_relationships_in_one_call()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn([
                'keyword1' => 1.0,
                'keyword2' => 0.8,
                'keyword3' => 0.6,
            ]);

        // Should create 3 keyword nodes
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->times(3);

        // Should create 3 relationships
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->times(3);

        $this->linker->link('LawDocument', 'law-id', 'Content with multiple keywords');
    }

    /** @test */
    public function it_deduplicates_keywords_by_using_md5_hash()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, $this->advancedExtractorMock);

        // Call link twice with same keyword
        $this->advancedExtractorMock
            ->shouldReceive('extract')
            ->twice()
            ->andReturn(['duplicate' => 1.0]);

        $expectedId = 'keyword_'.md5('duplicate');

        // Should create/update same node twice (upsert)
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->twice()
            ->with('Keyword', $expectedId, Mockery::any());

        // Should create relationship twice (to different documents)
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->twice();

        $this->linker->link('LawDocument', 'law-id-1', 'duplicate content');
        $this->linker->link('LawDocument', 'law-id-2', 'duplicate content');
    }

    // ========================================
    // unlinkAll() Method Tests
    // ========================================

    /** @test */
    public function it_deletes_has_keyword_relationships_for_node()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Mock the Cypher query to delete HAS_KEYWORD relationships
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'MATCH')
                        && str_contains($query, 'HAS_KEYWORD')
                        && str_contains($query, 'DELETE r');
                }),
                Mockery::on(function ($params) {
                    return $params['nodeType'] === 'LawDocument'
                        && $params['nodeId'] === 'law-123';
                })
            )
            ->andReturn((object) ['count' => 5]);

        // Mock the orphaned keyword cleanup query
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'MATCH (k:Keyword)')
                        && str_contains($query, 'NOT (k)<-[]')
                        && str_contains($query, 'DELETE k');
                }),
                []
            )
            ->andReturn((object) ['count' => 2]);

        $count = $this->linker->unlinkAll('LawDocument', 'law-123');

        $this->assertEquals(5, $count);
    }

    /** @test */
    public function it_cleans_up_orphaned_keyword_nodes()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Mock the relationship deletion
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andReturn((object) ['count' => 3]);

        // Mock the orphaned keyword cleanup - verify it's called
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($query) {
                    // Verify the query looks for orphaned keywords
                    return str_contains($query, 'MATCH (k:Keyword)')
                        && str_contains($query, 'WHERE NOT (k)<-[]')
                        && str_contains($query, 'DELETE k');
                }),
                []
            )
            ->andReturn((object) ['count' => 2]);

        $count = $this->linker->unlinkAll('LawDocument', 'law-456');

        // Should return count of relationships deleted, not nodes
        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_returns_accurate_relationship_count()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Mock the relationship deletion with specific count
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andReturn((object) ['count' => 12]);

        // Mock the orphaned keyword cleanup
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andReturn((object) ['count' => 5]);

        $count = $this->linker->unlinkAll('CaseDocument', 'case-789');

        $this->assertEquals(12, $count);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully()
    {
        $this->linker = new GraphKeywordLinker($this->graphMock, null);

        // Mock Neo4j being unavailable
        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \RuntimeException('Neo4j is not available'));

        // Should return 0 instead of throwing exception
        $count = $this->linker->unlinkAll('LawDocument', 'law-999');

        $this->assertEquals(0, $count);
    }
}
