<?php

namespace Tests\Unit\Services\Search;

use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\Search\CitationSearchService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CitationSearchService.
 *
 * Tests the citation-based search logic extracted from UnifiedSearchService.
 * Uses mocks for the citation detector and DB facade to keep tests fast and isolated.
 */
class CitationSearchServiceTest extends TestCase
{
    protected HrLegalCitationsDetector $citationDetectorMock;

    protected CitationSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citationDetectorMock = Mockery::mock(HrLegalCitationsDetector::class);
        $this->service = new CitationSearchService($this->citationDetectorMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // search() method tests
    // ========================================

    /** @test */
    public function search_returns_empty_when_no_citations_detected(): void
    {
        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->with('some query without citations')
            ->once()
            ->andReturn([
                'statutes' => [],
                'narodne_novine' => [],
                'case_numbers' => [],
                'ecli' => [],
                'dates' => [],
            ]);

        $results = $this->service->search('some query without citations', ['laws'], [], 10);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function search_ignores_unknown_corpus(): void
    {
        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn([
                'statutes' => [['text' => 'NN 123/20']],
                'narodne_novine' => [],
                'case_numbers' => [],
                'ecli' => [],
                'dates' => [],
            ]);

        // Searching an unknown corpus should return empty (no crash)
        $results = $this->service->search('NN 123/20', ['unknown_corpus'], [], 10);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function search_dispatches_to_multiple_corpora(): void
    {
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'narodne_novine' => [],
            'case_numbers' => [],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        // Mock DB for laws
        $lawRow = (object) [
            'id' => 'law-1',
            'doc_id' => 'doc-1',
            'title' => 'Test Law',
            'law_number' => '123/20',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content mentioning NN 123/20 citation here.',
            'metadata' => json_encode(['article_number' => '5']),
            'content_hash' => 'hash1',
            'chunk_index' => 0,
            'promulgation_date' => '2020-01-01',
            'effective_date' => '2020-02-01',
        ];

        $caseRow = (object) [
            'id' => 'case-1',
            'case_id' => 'case-id-1',
            'doc_id' => 'doc-2',
            'title' => 'Test Case Doc',
            'category' => 'evidence',
            'language' => 'hr',
            'content' => 'Content with NN 123/20 reference.',
            'metadata' => null,
            'content_hash' => 'hash2',
            'chunk_index' => 0,
            'source' => 'upload',
        ];

        // We need to mock the DB facade for two separate table calls
        $lawQueryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $lawQueryMock->shouldReceive('select')->once()->andReturnSelf();
        $lawQueryMock->shouldReceive('where')->once()->andReturnSelf();
        $lawQueryMock->shouldReceive('limit')->once()->andReturnSelf();
        $lawQueryMock->shouldReceive('get')->once()->andReturn(collect([$lawRow]));

        $caseQueryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $caseQueryMock->shouldReceive('select')->once()->andReturnSelf();
        $caseQueryMock->shouldReceive('where')->once()->andReturnSelf();
        $caseQueryMock->shouldReceive('limit')->once()->andReturnSelf();
        $caseQueryMock->shouldReceive('get')->once()->andReturn(collect([$caseRow]));

        DB::shouldReceive('table')
            ->with('laws')
            ->once()
            ->andReturn($lawQueryMock);

        DB::shouldReceive('table')
            ->with('cases_documents')
            ->once()
            ->andReturn($caseQueryMock);

        $results = $this->service->search('NN 123/20', ['laws', 'cases'], [], 10);

        $this->assertCount(2, $results);
        $this->assertEquals('law', $results[0]['type']);
        $this->assertEquals('case', $results[1]['type']);
    }

    // ========================================
    // buildCitationSearchPatterns() tests
    // ========================================

    /** @test */
    public function build_citation_search_patterns_extracts_text_from_arrays(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $citations = [
            'statutes' => [
                ['text' => 'Zakon o radu', 'canonical' => 'ZR'],
                ['text' => 'Zakon o obveznim odnosima', 'canonical' => 'ZOO'],
            ],
            'ecli' => [
                ['text' => 'ECLI:HR:VSRH:2020:123'],
            ],
        ];

        $patterns = $service->publicBuildCitationSearchPatterns($citations);

        $this->assertCount(3, $patterns);
        $this->assertContains('Zakon o radu', $patterns);
        $this->assertContains('Zakon o obveznim odnosima', $patterns);
        $this->assertContains('ECLI:HR:VSRH:2020:123', $patterns);
    }

    /** @test */
    public function build_citation_search_patterns_extracts_strings(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $citations = [
            'case_numbers' => ['Gzz-123/2020', 'Kz-456/2019'],
        ];

        $patterns = $service->publicBuildCitationSearchPatterns($citations);

        $this->assertCount(2, $patterns);
        $this->assertContains('Gzz-123/2020', $patterns);
        $this->assertContains('Kz-456/2019', $patterns);
    }

    /** @test */
    public function build_citation_search_patterns_deduplicates(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $citations = [
            'statutes' => [
                ['text' => 'NN 123/20'],
                ['text' => 'NN 123/20'], // duplicate
            ],
            'case_numbers' => ['NN 123/20'], // also duplicate as string
        ];

        $patterns = $service->publicBuildCitationSearchPatterns($citations);

        $this->assertCount(1, $patterns);
        $this->assertContains('NN 123/20', $patterns);
    }

    /** @test */
    public function build_citation_search_patterns_returns_empty_for_empty_input(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $patterns = $service->publicBuildCitationSearchPatterns([]);

        $this->assertIsArray($patterns);
        $this->assertEmpty($patterns);
    }

    // ========================================
    // calculateCitationScore() tests
    // ========================================

    /** @test */
    public function calculate_citation_score_returns_one_when_all_match(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $content = 'This text mentions NN 123/20 and ECLI:HR:VSRH:2020:123 explicitly.';
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'ecli' => [['text' => 'ECLI:HR:VSRH:2020:123']],
        ];

        $score = $service->publicCalculateCitationScore($content, $citations);

        $this->assertEquals(1.0, $score);
    }

    /** @test */
    public function calculate_citation_score_returns_partial_for_some_matches(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $content = 'This text only mentions NN 123/20 but nothing else.';
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'ecli' => [['text' => 'ECLI:HR:VSRH:2020:123']],
        ];

        $score = $service->publicCalculateCitationScore($content, $citations);

        $this->assertEquals(0.5, $score);
    }

    /** @test */
    public function calculate_citation_score_returns_zero_for_no_matches(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $content = 'This text has no citations at all.';
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'ecli' => [['text' => 'ECLI:HR:VSRH:2020:123']],
        ];

        $score = $service->publicCalculateCitationScore($content, $citations);

        $this->assertEquals(0.0, $score);
    }

    /** @test */
    public function calculate_citation_score_is_case_insensitive(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $content = 'This text has nn 123/20 in lowercase.';
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
        ];

        $score = $service->publicCalculateCitationScore($content, $citations);

        $this->assertEquals(1.0, $score);
    }

    /** @test */
    public function calculate_citation_score_caps_at_one(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        // With only 1 pattern, 1 match => 1/1 = 1.0, min(1.0, 1.0) = 1.0
        $content = 'NN 123/20 appears here.';
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
        ];

        $score = $service->publicCalculateCitationScore($content, $citations);

        $this->assertLessThanOrEqual(1.0, $score);
    }

    // ========================================
    // normalizeResult() tests
    // ========================================

    /** @test */
    public function normalize_result_returns_correct_format(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $result = $service->publicNormalizeResult(
            'law',
            'test-id-123',
            'Test Law Title',
            'This is a snippet.',
            0.85432,
            ['law_number' => '123/20']
        );

        $this->assertEquals('law', $result['type']);
        $this->assertEquals('test-id-123', $result['id']);
        $this->assertEquals('Test Law Title', $result['title']);
        $this->assertEquals('This is a snippet.', $result['snippet']);
        $this->assertEquals(0.8543, $result['score']); // rounded to 4 decimals
        $this->assertEquals(['law_number' => '123/20'], $result['metadata']);
    }

    /** @test */
    public function normalize_result_rounds_score_to_four_decimals(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $result = $service->publicNormalizeResult('law', 'id', 'title', 'snippet', 0.123456789, []);

        $this->assertEquals(0.1235, $result['score']);
    }

    // ========================================
    // extractSnippet() tests
    // ========================================

    /** @test */
    public function extract_snippet_returns_full_content_when_short(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $result = $service->publicExtractSnippet('Short content.', 200);

        $this->assertEquals('Short content.', $result);
    }

    /** @test */
    public function extract_snippet_truncates_at_sentence_boundary(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        // Create content longer than 200 chars with a period at a good break point
        $sentence1 = str_repeat('A', 150) . '.';  // 151 chars
        $sentence2 = ' ' . str_repeat('B', 100) . '.'; // 102 chars
        $content = $sentence1 . $sentence2; // 253 chars total

        $result = $service->publicExtractSnippet($content, 200);

        // Should break at the period of sentence1 since it's beyond 60% of maxLength
        $this->assertEquals($sentence1, $result);
    }

    /** @test */
    public function extract_snippet_adds_ellipsis_when_no_good_boundary(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        // Content with no sentence-ending punctuation
        $content = str_repeat('A', 300);

        $result = $service->publicExtractSnippet($content, 200);

        $this->assertEquals(str_repeat('A', 200) . '...', $result);
    }

    /** @test */
    public function extract_snippet_trims_whitespace(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $result = $service->publicExtractSnippet('  content with whitespace  ', 200);

        $this->assertEquals('content with whitespace', $result);
    }

    /** @test */
    public function extract_snippet_handles_boundary_too_early(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        // Period is within first 60% of maxLength (at position 20 of 200 = 10%)
        // This is too early, so it should use ellipsis instead
        $content = str_repeat('A', 20) . '.' . str_repeat('B', 250);

        $result = $service->publicExtractSnippet($content, 200);

        // Period at position 20 is at 10%, which is less than 60% threshold
        // So it should fall through to truncation with ellipsis
        $this->assertStringEndsWith('...', $result);
        $this->assertEquals(203, mb_strlen($result)); // 200 chars + '...'
    }

    // ========================================
    // applyFiltersToQuery() tests
    // ========================================

    /** @test */
    public function apply_filters_with_equals_operator(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('where')
            ->with('jurisdiction', '=', 'HR')
            ->once();

        $service->publicApplyFiltersToQuery($queryMock, ['jurisdiction' => 'HR'], [
            'jurisdiction' => ['column' => 'jurisdiction'],
        ]);

        // Count Mockery expectations as PHPUnit assertions
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    /** @test */
    public function apply_filters_with_like_operator(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('where')
            ->with('cd.court', 'LIKE', '%Vrhovni%')
            ->once();

        $service->publicApplyFiltersToQuery($queryMock, ['court' => 'Vrhovni'], [
            'court' => ['column' => 'cd.court', 'operator' => 'LIKE'],
        ]);

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    /** @test */
    public function apply_filters_with_date_range_operators(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('where')
            ->with('decision_date', '>=', '2020-01-01')
            ->once();
        $queryMock->shouldReceive('where')
            ->with('decision_date', '<=', '2020-12-31')
            ->once();

        $service->publicApplyFiltersToQuery($queryMock, [
            'date_from' => '2020-01-01',
            'date_to' => '2020-12-31',
        ], [
            'date_from' => ['column' => 'decision_date', 'operator' => '>='],
            'date_to' => ['column' => 'decision_date', 'operator' => '<='],
        ]);

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    /** @test */
    public function apply_filters_ignores_unmapped_filters(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        // No 'where' call should be made for the unmapped filter
        $queryMock->shouldNotReceive('where');

        $service->publicApplyFiltersToQuery($queryMock, ['nonexistent_filter' => 'value'], [
            'jurisdiction' => ['column' => 'jurisdiction'],
        ]);

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    /** @test */
    public function apply_filters_escapes_like_special_characters(): void
    {
        $service = new TestCitationSearchService($this->citationDetectorMock);

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        // The value "100%" should have the % escaped
        $queryMock->shouldReceive('where')
            ->with('cd.court', 'LIKE', '%100\\%%')
            ->once();

        $service->publicApplyFiltersToQuery($queryMock, ['court' => '100%'], [
            'court' => ['column' => 'cd.court', 'operator' => 'LIKE'],
        ]);

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    // ========================================
    // citationSearchLaws() integration-style mock test
    // ========================================

    /** @test */
    public function citation_search_laws_returns_normalized_results(): void
    {
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'narodne_novine' => [],
            'case_numbers' => [],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        $lawRow = (object) [
            'id' => 'law-id-1',
            'doc_id' => 'doc-1',
            'title' => 'Zakon o radu',
            'law_number' => '93/14',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content with NN 123/20 citation.',
            'metadata' => json_encode(['article_number' => '12']),
            'content_hash' => 'abc123',
            'chunk_index' => 0,
            'promulgation_date' => '2014-07-18',
            'effective_date' => '2014-08-07',
        ];

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('select')->once()->andReturnSelf();
        $queryMock->shouldReceive('where')->once()->andReturnSelf();
        $queryMock->shouldReceive('limit')->with(10)->once()->andReturnSelf();
        $queryMock->shouldReceive('get')->once()->andReturn(collect([$lawRow]));

        DB::shouldReceive('table')
            ->with('laws')
            ->once()
            ->andReturn($queryMock);

        $results = $this->service->search('NN 123/20', ['laws'], [], 10);

        $this->assertCount(1, $results);
        $this->assertEquals('law', $results[0]['type']);
        $this->assertEquals('law-id-1', $results[0]['id']);
        $this->assertEquals('Zakon o radu', $results[0]['title']);
        $this->assertArrayHasKey('metadata', $results[0]);
        $this->assertEquals('93/14', $results[0]['metadata']['law_number']);
        $this->assertEquals('12', $results[0]['metadata']['article_number']);
    }

    /** @test */
    public function citation_search_decisions_returns_normalized_results(): void
    {
        $citations = [
            'statutes' => [],
            'narodne_novine' => [],
            'case_numbers' => [['text' => 'Gzz-123/2020']],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        $decisionRow = (object) [
            'id' => 'dec-doc-1',
            'decision_id' => 'dec-1',
            'case_number' => 'Gzz-123/2020',
            'title' => 'Decision Title',
            'court' => 'Vrhovni sud RH',
            'jurisdiction' => 'HR',
            'decision_date' => '2020-06-15',
            'decision_type' => 'presuda',
            'ecli' => 'ECLI:HR:VSRH:2020:Gzz.123.2020.1',
            'content' => 'Content with Gzz-123/2020 reference.',
            'metadata' => null,
            'content_hash' => 'def456',
            'chunk_index' => 0,
        ];

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('join')
            ->with('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
            ->once()
            ->andReturnSelf();
        $queryMock->shouldReceive('select')->once()->andReturnSelf();
        $queryMock->shouldReceive('where')->once()->andReturnSelf();
        $queryMock->shouldReceive('limit')->with(10)->once()->andReturnSelf();
        $queryMock->shouldReceive('get')->once()->andReturn(collect([$decisionRow]));

        DB::shouldReceive('table')
            ->with('court_decision_documents as cdd')
            ->once()
            ->andReturn($queryMock);

        $results = $this->service->search('Gzz-123/2020', ['decisions'], [], 10);

        $this->assertCount(1, $results);
        $this->assertEquals('decision', $results[0]['type']);
        $this->assertEquals('dec-doc-1', $results[0]['id']);
        $this->assertEquals('Decision Title', $results[0]['title']);
        $this->assertEquals('dec-1', $results[0]['metadata']['decision_id']);
        $this->assertEquals('Vrhovni sud RH', $results[0]['metadata']['court']);
        $this->assertEquals('ECLI:HR:VSRH:2020:Gzz.123.2020.1', $results[0]['metadata']['ecli']);
    }

    /** @test */
    public function citation_search_cases_returns_normalized_results(): void
    {
        $citations = [
            'statutes' => [['text' => 'NN 55/21']],
            'narodne_novine' => [],
            'case_numbers' => [],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        $caseRow = (object) [
            'id' => 'case-doc-1',
            'case_id' => 'case-1',
            'doc_id' => 'doc-abc',
            'title' => 'Case Document',
            'category' => 'evidence',
            'language' => 'hr',
            'content' => 'This case references NN 55/21 citation.',
            'metadata' => null,
            'content_hash' => 'ghi789',
            'chunk_index' => 0,
            'source' => 'upload',
        ];

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('select')->once()->andReturnSelf();
        $queryMock->shouldReceive('where')->once()->andReturnSelf();
        $queryMock->shouldReceive('limit')->with(10)->once()->andReturnSelf();
        $queryMock->shouldReceive('get')->once()->andReturn(collect([$caseRow]));

        DB::shouldReceive('table')
            ->with('cases_documents')
            ->once()
            ->andReturn($queryMock);

        $results = $this->service->search('NN 55/21', ['cases'], [], 10);

        $this->assertCount(1, $results);
        $this->assertEquals('case', $results[0]['type']);
        $this->assertEquals('case-doc-1', $results[0]['id']);
        $this->assertEquals('Case Document', $results[0]['title']);
        $this->assertEquals('case-1', $results[0]['metadata']['case_id']);
        $this->assertEquals('upload', $results[0]['metadata']['source']);
    }

    /** @test */
    public function citation_search_handles_db_exception_gracefully(): void
    {
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'narodne_novine' => [],
            'case_numbers' => [],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        DB::shouldReceive('table')
            ->with('laws')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        // Should not throw, just return empty from the failed corpus
        $results = $this->service->search('NN 123/20', ['laws'], [], 10);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function citation_search_laws_handles_json_metadata(): void
    {
        $citations = [
            'statutes' => [['text' => 'NN 123/20']],
            'narodne_novine' => [],
            'case_numbers' => [],
            'ecli' => [],
            'dates' => [],
        ];

        $this->citationDetectorMock
            ->shouldReceive('detectAll')
            ->once()
            ->andReturn($citations);

        // Row with metadata as a JSON string with article_number
        $lawRow = (object) [
            'id' => 'law-1',
            'doc_id' => 'doc-1',
            'title' => null,
            'law_number' => '123/20',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'NN 123/20 content.',
            'metadata' => json_encode(['article_number' => '99']),
            'content_hash' => 'hash',
            'chunk_index' => 1,
            'promulgation_date' => null,
            'effective_date' => null,
        ];

        $queryMock = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $queryMock->shouldReceive('select')->once()->andReturnSelf();
        $queryMock->shouldReceive('where')->once()->andReturnSelf();
        $queryMock->shouldReceive('limit')->once()->andReturnSelf();
        $queryMock->shouldReceive('get')->once()->andReturn(collect([$lawRow]));

        DB::shouldReceive('table')
            ->with('laws')
            ->once()
            ->andReturn($queryMock);

        $results = $this->service->search('NN 123/20', ['laws'], [], 10);

        $this->assertCount(1, $results);
        // Title should fall back to "Law {law_number}" when title is null
        $this->assertEquals('Law 123/20', $results[0]['title']);
        $this->assertEquals('99', $results[0]['metadata']['article_number']);
    }
}

/**
 * Test helper that exposes protected methods for unit testing.
 */
class TestCitationSearchService extends CitationSearchService
{
    public function publicBuildCitationSearchPatterns(array $citations): array
    {
        return $this->buildCitationSearchPatterns($citations);
    }

    public function publicCalculateCitationScore(string $content, array $citations): float
    {
        return $this->calculateCitationScore($content, $citations);
    }

    public function publicNormalizeResult(string $type, string $id, string $title, string $snippet, float $score, array $metadata): array
    {
        return $this->normalizeResult($type, $id, $title, $snippet, $score, $metadata);
    }

    public function publicExtractSnippet(string $content, int $maxLength = 200): string
    {
        return $this->extractSnippet($content, $maxLength);
    }

    public function publicApplyFiltersToQuery($queryBuilder, array $filters, array $filterMappings): void
    {
        $this->applyFiltersToQuery($queryBuilder, $filters, $filterMappings);
    }
}
