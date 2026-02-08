<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\CaseReferenceExtractor;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class CaseReferenceExtractorTest extends TestCase
{
    private CaseReferenceExtractor $extractor;
    private CaseDocument $mockDocument;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new CaseReferenceExtractor();
        $this->mockDocument = Mockery::mock(CaseDocument::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_document_analyzer_interface(): void
    {
        $this->assertInstanceOf(DocumentAnalyzerInterface::class, $this->extractor);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $this->assertEquals('case_references', $this->extractor->type());
    }

    /** @test */
    public function it_returns_extraction_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->extractor->layer());
    }

    // ===== KLASA EXTRACTION TESTS =====

    /** @test */
    public function it_extracts_klasa_with_up_i_prefix(): void
    {
        $text = 'Dokument s klasom KLASA: UP/I-034-02/20-01/123 je zaprimljen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa']);
        $this->assertStringContainsString('UP/I-034-02/20-01/123', $result['results']['klasa'][0]['value']);
    }

    /** @test */
    public function it_extracts_klasa_without_prefix(): void
    {
        $text = 'KLASA: 034-02/25-01/5 je nova klasa.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa']);
        $this->assertStringContainsString('034-02/25-01/5', $result['results']['klasa'][0]['value']);
    }

    /** @test */
    public function it_extracts_klasa_with_spaces(): void
    {
        $text = 'K L A S A : UP/I-034-02/20-01/999 datum primitka.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa']);
    }

    /** @test */
    public function it_extracts_mixed_case_klasa(): void
    {
        $text = 'Klasa: UP/I-561-08/25-01/122 je ispravna.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa']);
    }

    // ===== URBROJ EXTRACTION TESTS =====

    /** @test */
    public function it_extracts_urbroj_standard_format(): void
    {
        $text = 'URBROJ: 511-01-02-03-20-1 PU Zagreb.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['urbroj']);
        $this->assertStringContainsString('511-01-02-03-20-1', $result['results']['urbroj'][0]['value']);
    }

    /** @test */
    public function it_extracts_urbroj_with_spaces(): void
    {
        $text = 'U R B R O J: 2158-64-16-01-25-1 sud.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['urbroj']);
    }

    /** @test */
    public function it_extracts_urbroj_mixed_case(): void
    {
        $text = 'Urbroj: 2168-01-02-03-25-1 Zagreb.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['urbroj']);
    }

    /** @test */
    public function it_extracts_ur_br_variation(): void
    {
        $text = 'Ur.br.: 511-07-02/25-123 policija.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['urbroj']);
    }

    /** @test */
    public function it_classifies_mup_urbroj(): void
    {
        $text = 'URBROJ: 511-01-02-03-20-1 MUP.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('mup_policija', $result['results']['urbroj'][0]['sub_type']);
    }

    /** @test */
    public function it_classifies_sud_urbroj(): void
    {
        $text = 'URBROJ: 2158-64-16-01-25-1 sud.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('sud', $result['results']['urbroj'][0]['sub_type']);
    }

    // ===== BROJ EXTRACTION TESTS =====

    /** @test */
    public function it_extracts_broj_with_prefix(): void
    {
        $text = 'Broj: 511-07-11-K-51/2025 je policijski broj.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['broj']);
    }

    /** @test */
    public function it_extracts_police_number_format(): void
    {
        $text = 'Dokument 511-07-11-K-51/2025 zaprimljen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['broj']);
        $this->assertEquals('policijski', $result['results']['broj'][0]['sub_type']);
    }

    /** @test */
    public function it_extracts_do_number_format(): void
    {
        $text = 'Spis 67-00-731/2025 DO Zagreb.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['broj']);
        $this->assertEquals('drzavno_odvjetnistvo', $result['results']['broj'][0]['sub_type']);
    }

    // ===== CASE NUMBER EXTRACTION TESTS =====

    /** @test */
    public function it_extracts_kazneni_case_numbers(): void
    {
        $text = 'U predmetu K-123/24 optuzeni je osuđen. Predmet Kž-456/2024 odbijen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $this->assertGreaterThanOrEqual(2, count($caseNumbers));

        $values = array_column($caseNumbers, 'value');
        $this->assertContains('K-123/24', $values);
        $this->assertContains('Kž-456/2024', $values);

        foreach ($caseNumbers as $cn) {
            $this->assertEquals('kazneni', $cn['sub_type']);
        }
    }

    /** @test */
    public function it_extracts_prekrsajni_case_numbers(): void
    {
        $text = 'Nalog Pp Prz-74/2025-2 za pretragu doma.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $this->assertNotEmpty($caseNumbers);
        $this->assertStringContainsString('Pp Prz-74/2025', $caseNumbers[0]['value']);
        $this->assertEquals('prekrsajni', $caseNumbers[0]['sub_type']);
    }

    /** @test */
    public function it_extracts_dorh_case_numbers(): void
    {
        $text = 'Prema predmetu DO-111/2024 i KP-222/2025 postupak je obustavljen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $dorhNumbers = array_filter($caseNumbers, fn($cn) => $cn['sub_type'] === 'dorh');

        $this->assertGreaterThanOrEqual(2, count($dorhNumbers));
    }

    /** @test */
    public function it_extracts_gradanski_case_numbers(): void
    {
        $text = 'Parnični postupak P-100/24 i Gž-200/2025.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $gradanski = array_filter($caseNumbers, fn($cn) => $cn['sub_type'] === 'gradanski');

        $this->assertGreaterThanOrEqual(2, count($gradanski));
    }

    /** @test */
    public function it_extracts_upravni_case_numbers(): void
    {
        $text = 'Upravni spor Us-500/2024 i Usž-600/2025 riješen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $upravni = array_filter($caseNumbers, fn($cn) => $cn['sub_type'] === 'upravni');

        $this->assertGreaterThanOrEqual(2, count($upravni));
    }

    /** @test */
    public function it_extracts_all_kazneni_variants(): void
    {
        $text = <<<TEXT
        Kazneni predmeti: K-1/24, Kž-2/24, Kžm-3/24, Kr-4/24, Kv-5/24,
        Kv II-6/24, KO-7/24, KIO-8/24, KIR-9/24, Kov-10/24, I Kž-11/24, Kis-12/24
        TEXT;

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $this->assertGreaterThanOrEqual(10, count($caseNumbers));

        foreach ($caseNumbers as $cn) {
            $this->assertEquals('kazneni', $cn['sub_type']);
        }
    }

    // ===== KLASA-URBROJ PAIRING TESTS =====

    /** @test */
    public function it_detects_klasa_urbroj_pairs(): void
    {
        $text = <<<TEXT
        REPUBLIKA HRVATSKA
        MINISTARSTVO UNUTARNJIH POSLOVA

        KLASA: UP/I-034-02/20-01/123
        URBROJ: 511-01-02-03-20-1

        Zagreb, 15. siječnja 2025.
        TEXT;

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa_urbroj_pairs']);
    }

    // ===== CONTEXT EXTRACTION TESTS =====

    /** @test */
    public function it_includes_context_around_references(): void
    {
        $text = 'Prije klase neki tekst. KLASA: 034-02/25-01/5 je registrirana. Nakon klase tekst.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['klasa'][0]['context']);
        $this->assertStringContainsString('Prije klase', $result['results']['klasa'][0]['context']);
    }

    // ===== DEDUPLICATION TESTS =====

    /** @test */
    public function it_deduplicates_same_references(): void
    {
        $text = 'Predmet K-123/24 je naveden. Ponovno K-123/24 u dokumentu.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $caseNumbers = $result['results']['case_numbers'];
        $k123 = array_filter($caseNumbers, fn($cn) => $cn['value'] === 'K-123/24');

        $this->assertCount(1, $k123);
        $this->assertEquals(2, reset($k123)['mentions']);
    }

    // ===== METADATA TESTS =====

    /** @test */
    public function it_returns_correct_metadata(): void
    {
        $text = 'KLASA: 034-02/25-01/5 test.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertEquals('CaseReferenceExtractor', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
        $this->assertEquals('extract_references.sh v4', $result['metadata']['source_script_version']);
    }

    /** @test */
    public function it_counts_total_references(): void
    {
        $text = 'KLASA: 034-02/25-01/5 URBROJ: 511-01-02-03-20-1 K-123/24';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertGreaterThanOrEqual(3, $result['metadata']['total_references']);
    }

    // ===== OUTPUT STRUCTURE TESTS =====

    /** @test */
    public function it_returns_correct_array_structure(): void
    {
        $text = 'Empty document.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('klasa', $result['results']);
        $this->assertArrayHasKey('urbroj', $result['results']);
        $this->assertArrayHasKey('broj', $result['results']);
        $this->assertArrayHasKey('case_numbers', $result['results']);
        $this->assertArrayHasKey('klasa_urbroj_pairs', $result['results']);
        $this->assertArrayHasKey('unique_values', $result['results']);
        $this->assertArrayHasKey('case_numbers_by_type', $result['results']);
    }
}
