<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\DateExtractor;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Tests\TestCase;

class DateExtractorTest extends TestCase
{
    private DateExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DateExtractor();
    }

    public function test_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->extractor);
    }

    public function test_returns_correct_type(): void
    {
        $this->assertEquals(DocumentAnalysis::TYPE_DATES, $this->extractor->type());
    }

    public function test_returns_correct_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->extractor->layer());
    }

    public function test_analyze_returns_expected_structure(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Dana 15. siječnja 2024. godine održana je rasprava.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('dates', $result['results']);
        $this->assertArrayHasKey('date_count', $result['results']);
        $this->assertArrayHasKey('unique_dates', $result['results']);
        $this->assertArrayHasKey('date_range', $result['results']);
    }

    public function test_extracts_croatian_long_format_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Dana 15. siječnja 2024. godine održana je rasprava. Sljedeća je zakazana za 20. veljače 2024.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEquals(2, $result['results']['date_count']);
        
        $dates = $result['results']['dates'];
        $this->assertEquals('2024-01-15', $dates[0]['date']);
        $this->assertEquals('croatian_long', $dates[0]['format_detected']);
    }

    public function test_extracts_dd_mm_yyyy_format_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Datum: 15.01.2024. Rok: 28.02.2024';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEquals(2, $result['results']['date_count']);
        
        $dates = $result['results']['dates'];
        $dateValues = array_column($dates, 'date');
        $this->assertContains('2024-01-15', $dateValues);
        $this->assertContains('2024-02-28', $dateValues);
    }

    public function test_extracts_iso_format_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Dokument kreiran: 2024-01-15 Ažurirano: 2024-03-20';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEquals(2, $result['results']['date_count']);
        
        $dates = $result['results']['dates'];
        $this->assertEquals('iso', $dates[0]['format_detected']);
    }

    public function test_extracts_all_croatian_months(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $months = [
            '1. siječnja 2024' => '2024-01-01',
            '1. veljače 2024' => '2024-02-01',
            '1. ožujka 2024' => '2024-03-01',
            '1. travnja 2024' => '2024-04-01',
            '1. svibnja 2024' => '2024-05-01',
            '1. lipnja 2024' => '2024-06-01',
            '1. srpnja 2024' => '2024-07-01',
            '1. kolovoza 2024' => '2024-08-01',
            '1. rujna 2024' => '2024-09-01',
            '1. listopada 2024' => '2024-10-01',
            '1. studenoga 2024' => '2024-11-01',
            '1. prosinca 2024' => '2024-12-01',
        ];

        foreach ($months as $text => $expectedDate) {
            $result = $this->extractor->analyze($document, $text);
            $this->assertNotEmpty($result['results']['dates'], "Failed for: $text");
            $this->assertEquals($expectedDate, $result['results']['dates'][0]['date'], "Failed for: $text");
        }
    }

    public function test_includes_context_for_each_date(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Predmet zaprimljen dana 15. siječnja 2024. godine u Županijski sud.';

        $result = $this->extractor->analyze($document, $text);

        $dates = $result['results']['dates'];
        $this->assertNotEmpty($dates);
        $this->assertArrayHasKey('context', $dates[0]);
        $this->assertStringContainsString('Predmet', $dates[0]['context']);
    }

    public function test_avoids_duplicate_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        // Same date in two formats
        $text = '15. siječnja 2024. godine (15.01.2024.)';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEquals(1, $result['results']['unique_dates']);
    }

    public function test_calculates_date_range(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Početak: 15.01.2024. Završetak: 15.03.2024.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertNotNull($result['results']['date_range']);
        $this->assertEquals('2024-01-15', $result['results']['date_range']['earliest']);
        $this->assertEquals('2024-03-15', $result['results']['date_range']['latest']);
        $this->assertEquals(60, $result['results']['date_range']['span_days']);
    }

    public function test_handles_empty_text(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEmpty($result['results']['dates']);
        $this->assertEquals(0, $result['results']['date_count']);
    }

    public function test_handles_text_without_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Ovaj tekst ne sadrži nikakve datume.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEmpty($result['results']['dates']);
        $this->assertEquals(0, $result['results']['date_count']);
    }

    public function test_rejects_invalid_dates(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '31.02.2024'; // Invalid - February doesn't have 31 days

        $result = $this->extractor->analyze($document, $text);

        $this->assertEmpty($result['results']['dates']);
    }

    public function test_metadata_includes_processing_info(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Dana 15. siječnja 2024.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('analyzer', $result['metadata']);
        $this->assertEquals('DateExtractor', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
    }

    public function test_sorts_dates_chronologically(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '20.03.2024 10.01.2024 15.02.2024';

        $result = $this->extractor->analyze($document, $text);

        $dates = array_column($result['results']['dates'], 'date');
        $this->assertEquals(['2024-01-10', '2024-02-15', '2024-03-20'], $dates);
    }
}
