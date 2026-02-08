<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\EntityExtractor;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Tests\TestCase;

class EntityExtractorTest extends TestCase
{
    private EntityExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new EntityExtractor();
    }

    public function test_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->extractor);
    }

    public function test_returns_correct_type(): void
    {
        $this->assertEquals(DocumentAnalysis::TYPE_ENTITIES, $this->extractor->type());
    }

    public function test_returns_correct_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->extractor->layer());
    }

    public function test_analyze_returns_expected_structure(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Županijski sud u Zagrebu, predmet K-123/2024';

        $result = $this->extractor->analyze($document, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('entities', $result['results']);
        $this->assertArrayHasKey('total_entities', $result['results']);
        $this->assertArrayHasKey('entity_density', $result['results']);
    }

    public function test_extracts_case_numbers(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Predmet K-123/2024, veza Kž-456/23, referenca I Kž Us 12/2023-5';

        $result = $this->extractor->analyze($document, $text);

        $caseNumbers = $result['results']['entities']['case_numbers'];
        $this->assertNotEmpty($caseNumbers);
        $this->assertGreaterThanOrEqual(1, count($caseNumbers));
    }

    public function test_extracts_ecli_case_numbers(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Europski identifikator ECLI:HR:VSRH:2024:123';

        $result = $this->extractor->analyze($document, $text);

        $caseNumbers = $result['results']['entities']['case_numbers'];
        $this->assertNotEmpty($caseNumbers);
    }

    public function test_extracts_courts(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Županijski sud u Zagrebu donio je presudu. Vrhovni sud u Zagrebu potvrdio je odluku.';

        $result = $this->extractor->analyze($document, $text);

        $courts = $result['results']['entities']['courts'];
        $this->assertNotEmpty($courts);
    }

    public function test_extracts_court_abbreviations(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'DORH je podnio optužnicu. USKOK provodi istragu. VSRH je donio odluku.';

        $result = $this->extractor->analyze($document, $text);

        $courts = $result['results']['entities']['courts'];
        $this->assertNotEmpty($courts);
        $courtNames = array_column($courts, 'court');
        $this->assertContains('DORH', $courtNames);
    }

    public function test_extracts_laws(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Prema Zakonu o kaznenom postupku i Kazneni zakon članak 123.';

        $result = $this->extractor->analyze($document, $text);

        $laws = $result['results']['entities']['laws'];
        $this->assertNotEmpty($laws);
    }

    public function test_extracts_law_abbreviations(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Sukladno ZKP čl. 123, KZ čl. 456, OZ čl. 789.';

        $result = $this->extractor->analyze($document, $text);

        $laws = $result['results']['entities']['laws'];
        $lawNames = array_column($laws, 'law');
        $this->assertContains('ZKP', $lawNames);
        $this->assertContains('KZ', $lawNames);
    }

    public function test_extracts_narodne_novine_references(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Objavljeno u NN 152/08, NN br. 70/17 i Narodne novine 118/18';

        $result = $this->extractor->analyze($document, $text);

        $nnRefs = $result['results']['entities']['narodne_novine'];
        $this->assertNotEmpty($nnRefs);
    }

    public function test_extracts_person_references(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Okrivljenik je ispitan. Svjedok je dao iskaz. Vještak je podnio nalaz. Tužitelj je predložio.';

        $result = $this->extractor->analyze($document, $text);

        $persons = $result['results']['entities']['persons'];
        $this->assertNotEmpty($persons);
        $roles = array_column($persons, 'role');
        $this->assertContains('okrivljenik', $roles);
        $this->assertContains('svjedok', $roles);
    }

    public function test_extracts_institutions(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'MUP je proveo istragu. Policijska uprava Zagreb izvijestila je. Državno odvjetništvo je zaprimilo.';

        $result = $this->extractor->analyze($document, $text);

        $institutions = $result['results']['entities']['institutions'];
        $this->assertNotEmpty($institutions);
    }

    public function test_extracts_monetary_amounts(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Šteta iznosi 1.500,00 kuna. Novčana kazna 500,00 EUR.';

        $result = $this->extractor->analyze($document, $text);

        $amounts = $result['results']['entities']['monetary_amounts'];
        $this->assertNotEmpty($amounts);
        $this->assertArrayHasKey('raw', $amounts[0]);
        $this->assertArrayHasKey('amount', $amounts[0]);
        $this->assertArrayHasKey('currency', $amounts[0]);
    }

    public function test_counts_total_entities(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Županijski sud u Zagrebu, predmet K-123/2024, ZKP čl. 123, okrivljenik, 1.000,00 kuna';

        $result = $this->extractor->analyze($document, $text);

        $this->assertGreaterThan(0, $result['results']['total_entities']);
    }

    public function test_calculates_entity_density(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Županijski sud u Zagrebu je donio presudu u predmetu K-123/2024.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertArrayHasKey('entity_density', $result['results']);
        $this->assertIsFloat($result['results']['entity_density']);
    }

    public function test_handles_empty_text(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = '';

        $result = $this->extractor->analyze($document, $text);

        $this->assertEquals(0, $result['results']['total_entities']);
    }

    public function test_metadata_includes_processing_info(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Test tekst.';

        $result = $this->extractor->analyze($document, $text);

        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('analyzer', $result['metadata']);
        $this->assertEquals('EntityExtractor', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
    }

    public function test_counts_mentions_for_repeated_entities(): void
    {
        $document = new CaseDocument(['id' => 'test-doc-id']);
        $text = 'Županijski sud u Zagrebu. Županijski sud u Zagrebu je odlučio.';

        $result = $this->extractor->analyze($document, $text);

        $courts = $result['results']['entities']['courts'];
        // Should have one entry with mentions count of 2
        $this->assertNotEmpty($courts);
        $this->assertEquals(2, $courts[0]['mentions']);
    }
}
