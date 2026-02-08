<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\DocumentStatisticsAnalyzer;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Tests\TestCase;

class DocumentStatisticsAnalyzerTest extends TestCase
{
    private DocumentStatisticsAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new DocumentStatisticsAnalyzer();
    }

    public function test_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->analyzer);
    }

    public function test_returns_correct_type(): void
    {
        $this->assertEquals(DocumentAnalysis::TYPE_STATISTICS, $this->analyzer->type());
    }

    public function test_returns_correct_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->analyzer->layer());
    }

    public function test_analyze_returns_expected_structure(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Ovo je test tekst za analizu.';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('word_count', $result['results']);
        $this->assertArrayHasKey('sentence_count', $result['results']);
        $this->assertArrayHasKey('paragraph_count', $result['results']);
        $this->assertArrayHasKey('line_count', $result['results']);
        $this->assertArrayHasKey('character_count', $result['results']);
    }

    public function test_counts_words(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Jedna dva tri četiri pet';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(5, $result['results']['word_count']);
    }

    public function test_counts_sentences(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Prva rečenica. Druga rečenica? Treća rečenica!';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(3, $result['results']['sentence_count']);
    }

    public function test_counts_paragraphs(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = "Prvi paragraf.\n\nDrugi paragraf.\n\nTreći paragraf.";

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(3, $result['results']['paragraph_count']);
    }

    public function test_counts_lines(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = "Prva linija\nDruga linija\nTreća linija";

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(3, $result['results']['line_count']);
    }

    public function test_counts_characters(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Abc';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(3, $result['results']['character_count']);
    }

    public function test_counts_croatian_characters_correctly(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'čćžšđ';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(5, $result['results']['character_count']);
    }

    public function test_calculates_average_sentence_length(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Jedna dva. Tri četiri pet.'; // 2 sentences, 5 words

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(2.5, $result['results']['avg_sentence_length']);
    }

    public function test_calculates_average_paragraph_length(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = "Jedna dva tri.\n\nČetiri pet šest."; // 2 paragraphs, 6 words

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(3, $result['results']['avg_paragraph_length']);
    }

    public function test_detects_croatian_diacritic_density(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'čćžšđ'; // 5 Croatian diacritics out of 5 characters

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('croatian_diacritic_density', $result['results']);
        $this->assertEquals(100, $result['results']['croatian_diacritic_density']);
    }

    public function test_estimates_reading_time(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        // 200 words = 1 minute at 200 words/min for legal text
        $words = array_fill(0, 200, 'riječ');
        $text = implode(' ', $words);

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('estimated_reading_time_minutes', $result['results']);
        $this->assertEquals(1.0, $result['results']['estimated_reading_time_minutes']);
    }

    public function test_estimates_page_count(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        // 300 words = 1 page at ~300 words/page for legal docs
        $words = array_fill(0, 300, 'riječ');
        $text = implode(' ', $words);

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('estimated_page_count', $result['results']);
        $this->assertEquals(1, $result['results']['estimated_page_count']);
    }

    public function test_handles_empty_text(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(0, $result['results']['word_count']);
        $this->assertEquals(0, $result['results']['character_count']);
    }

    public function test_handles_text_with_only_whitespace(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '   ';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertEquals(0, $result['results']['word_count']);
    }

    public function test_metadata_includes_processing_info(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Test tekst.';

        $result = $this->analyzer->analyze($document, $text);

        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('analyzer', $result['metadata']);
        $this->assertEquals('DocumentStatisticsAnalyzer', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
    }

    public function test_handles_text_without_punctuation(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Tekst bez interpunkcije'; // 3 words, treated as 1 sentence

        $result = $this->analyzer->analyze($document, $text);

        // Text without punctuation is treated as one sentence
        $this->assertEquals(1, $result['results']['sentence_count']);
        $this->assertEquals(3.0, $result['results']['avg_sentence_length']);
    }

    public function test_handles_zero_paragraph_count(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '';

        $result = $this->analyzer->analyze($document, $text);

        // Should not divide by zero for avg_paragraph_length
        $this->assertEquals(0, $result['results']['avg_paragraph_length']);
    }
}
