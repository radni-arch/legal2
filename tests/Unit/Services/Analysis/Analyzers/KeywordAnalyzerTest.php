<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\KeywordAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Tests\TestCase;

class KeywordAnalyzerTest extends TestCase
{
    private KeywordAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new KeywordAnalyzer();
    }

    public function test_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->analyzer);
    }

    public function test_returns_correct_type(): void
    {
        $this->assertEquals(DocumentAnalysis::TYPE_KEYWORDS, $this->analyzer->type());
    }

    public function test_returns_correct_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->analyzer->layer());
    }

    public function test_analyze_returns_expected_structure(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Optuženik je uhićen nakon pretrage. Dokazi su prikupljeni.';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('keywords', $result['results']);
        $this->assertArrayHasKey('bigrams', $result['results']);
        $this->assertArrayHasKey('total_unique_terms', $result['results']);
        $this->assertArrayHasKey('total_words', $result['results']);
    }

    public function test_extracts_keywords_from_croatian_legal_text(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Optuženik je uhićen nakon pretrage stana. Dokazi su prikupljeni na pretresu. Sud je razmatrao dokaze.';

        $result = $this->analyzer->analyze($document, $text);

        $keywords = $result['results']['keywords'];
        $this->assertNotEmpty($keywords);

        // Check structure of each keyword entry
        $firstKeyword = $keywords[0];
        $this->assertArrayHasKey('term', $firstKeyword);
        $this->assertArrayHasKey('count', $firstKeyword);
        $this->assertArrayHasKey('tf_score', $firstKeyword);
        $this->assertArrayHasKey('boost', $firstKeyword);
        $this->assertArrayHasKey('final_score', $firstKeyword);
    }

    public function test_applies_legal_domain_boost_to_relevant_terms(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        // Include legal terms that should get boosted (optužen, dokaz, pretrag)
        $text = 'Optuženik ima dokaze. Pretraga je nezakonita.';

        $result = $this->analyzer->analyze($document, $text);

        $keywords = collect($result['results']['keywords']);

        // Find boosted terms
        $boostedTerm = $keywords->first(fn($k) => str_starts_with($k['term'], 'optuženik'));
        if ($boostedTerm) {
            $this->assertGreaterThan(1.0, $boostedTerm['boost'], 'Legal term should have boost > 1.0');
        }
    }

    public function test_removes_croatian_stop_words(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Optuženik je bio na sudu i u zatvoru.';

        $result = $this->analyzer->analyze($document, $text);

        $keywordTerms = array_column($result['results']['keywords'], 'term');

        // These Croatian stop words should NOT appear in keywords
        $this->assertNotContains('je', $keywordTerms);
        $this->assertNotContains('bio', $keywordTerms);
        $this->assertNotContains('na', $keywordTerms);
        $this->assertNotContains('i', $keywordTerms);
        $this->assertNotContains('u', $keywordTerms);
    }

    public function test_extracts_bigrams(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Kazneni postupak optuženik optuženik kazneni postupak';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertNotEmpty($result['results']['bigrams']);
        $firstBigram = $result['results']['bigrams'][0];
        $this->assertArrayHasKey('phrase', $firstBigram);
        $this->assertArrayHasKey('count', $firstBigram);
    }

    public function test_limits_keywords_to_fifty(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        // Generate text with many unique words
        $words = [];
        for ($i = 0; $i < 100; $i++) {
            $words[] = 'riječ' . $i;
        }
        $text = implode(' ', $words);

        $result = $this->analyzer->analyze($document, $text);

        $this->assertLessThanOrEqual(50, count($result['results']['keywords']));
    }

    public function test_metadata_includes_processing_info(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Test tekst za analizu.';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('analyzer', $result['metadata']);
        $this->assertArrayHasKey('api_calls', $result['metadata']);
        $this->assertArrayHasKey('cost', $result['metadata']);
        $this->assertEquals('KeywordAnalyzer', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
    }

    public function test_handles_empty_text(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEmpty($result['results']['keywords']);
        $this->assertEquals(0, $result['results']['total_words']);
    }

    public function test_handles_text_with_only_stop_words(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'i u je da na se za su od';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEmpty($result['results']['keywords']);
    }
}
