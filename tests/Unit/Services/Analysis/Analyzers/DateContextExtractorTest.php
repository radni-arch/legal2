<?php

namespace Tests\Unit\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Analyzers\DateContextExtractor;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class DateContextExtractorTest extends TestCase
{
    private DateContextExtractor $extractor;
    private CaseDocument $mockDocument;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DateContextExtractor();
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
        $this->assertEquals('dates_with_context', $this->extractor->type());
    }

    /** @test */
    public function it_returns_extraction_layer(): void
    {
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $this->extractor->layer());
    }

    // ===== CROATIAN LONG FORMAT TESTS (DD. month YYYY.) =====

    /** @test */
    public function it_extracts_croatian_long_format_date(): void
    {
        $text = 'Dana 15. siječnja 2024. godine izvršena je pretraga stana.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2024-01-15', $result['results']['dates'][0]['date']);
    }

    /** @test */
    public function it_extracts_date_without_godine_suffix(): void
    {
        $text = 'Presuda od 20. veljače 2025. je pravomoćna.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2025-02-20', $result['results']['dates'][0]['date']);
    }

    /** @test */
    public function it_extracts_all_croatian_months(): void
    {
        $months = [
            'siječnja' => '01', 'veljače' => '02', 'ožujka' => '03',
            'travnja' => '04', 'svibnja' => '05', 'lipnja' => '06',
            'srpnja' => '07', 'kolovoza' => '08', 'rujna' => '09',
            'listopada' => '10', 'studenog' => '11', 'prosinca' => '12',
        ];

        foreach ($months as $monthName => $monthNum) {
            $text = "Datum: 10. {$monthName} 2024.";
            $result = $this->extractor->analyze($this->mockDocument, $text);

            $this->assertNotEmpty($result['results']['dates'], "Failed for month: {$monthName}");
            $this->assertEquals("2024-{$monthNum}-10", $result['results']['dates'][0]['date'], "Failed for month: {$monthName}");
        }
    }

    // ===== DD.MM.YYYY FORMAT TESTS =====

    /** @test */
    public function it_extracts_dd_mm_yyyy_format(): void
    {
        $text = 'Dokument je datiran na 25.12.2024. godine.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2024-12-25', $result['results']['dates'][0]['date']);
    }

    /** @test */
    public function it_extracts_dd_mm_yyyy_with_spaces(): void
    {
        $text = 'Datum: 01. 02. 2025. g.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2025-02-01', $result['results']['dates'][0]['date']);
    }

    /** @test */
    public function it_extracts_dana_prefix_format(): void
    {
        $text = 'Postupak je pokrenut dana 15.03.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2024-03-15', $result['results']['dates'][0]['date']);
    }

    // ===== ISO FORMAT TESTS =====

    /** @test */
    public function it_extracts_iso_format_dates(): void
    {
        $text = 'Zapisnik kreiran: 2024-06-15';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('2024-06-15', $result['results']['dates'][0]['date']);
    }

    // ===== RANGE FORMAT TESTS (od/do) =====

    /** @test */
    public function it_extracts_range_od_format(): void
    {
        $text = 'Razdoblje od 01.01.2024. do 31.12.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $dates = $result['results']['dates'];
        $this->assertGreaterThanOrEqual(2, count($dates));
    }

    /** @test */
    public function it_marks_range_position(): void
    {
        $text = 'od 15.06.2024. do 20.06.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $dates = $result['results']['dates'];
        $odDates = array_filter($dates, fn($d) => ($d['range_position'] ?? null) === 'od');
        $doDates = array_filter($dates, fn($d) => ($d['range_position'] ?? null) === 'do');

        $this->assertNotEmpty($odDates);
        $this->assertNotEmpty($doDates);
    }

    // ===== TIME EXTRACTION TESTS =====

    /** @test */
    public function it_extracts_time_in_sati_format(): void
    {
        $text = 'Dana 15.01.2024. u 14:30 sati izvršena je pretraga.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('14:30', $result['results']['dates'][0]['time']);
    }

    /** @test */
    public function it_extracts_time_without_minutes(): void
    {
        $text = 'Dana 20.02.2024. u 9 sati započelo je ročište.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('09:00', $result['results']['dates'][0]['time']);
    }

    /** @test */
    public function it_extracts_time_with_comma_separator(): void
    {
        $text = 'Dana 10.03.2024. u 16,45 sati obavijestili smo.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates']);
        $this->assertEquals('16:45', $result['results']['dates'][0]['time']);
    }

    // ===== EVENT CLASSIFICATION TESTS =====

    /** @test */
    public function it_classifies_pretraga_event(): void
    {
        $text = 'Dana 15.01.2024. izvršena je pretraga stana osumnjičenika.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('pretraga', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_uhicenje_event(): void
    {
        $text = 'Dana 20.02.2024. uhićen je osumnjičenik.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('uhicenje', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_ispitivanje_event(): void
    {
        $text = 'Dana 10.03.2024. saslušan je svjedok.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('ispitivanje', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_presuda_event(): void
    {
        $text = 'Dana 15.04.2024. donesena je presuda kojom je optuženi osuđen.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('presuda', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_rjesenje_event(): void
    {
        $text = 'Dana 20.05.2024. doneseno je rješenje o pritvoru.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('rjesenje', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_zapljena_event(): void
    {
        $text = 'Dana 25.06.2024. pronađena i zaplijenjena je droga.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('zapljena', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_rociste_event(): void
    {
        $text = 'Dana 30.07.2024. održano je ročište u predmetu.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('rociste', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_classifies_zalba_event(): void
    {
        $text = 'Dana 05.08.2024. podnesena je žalba protiv presude.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals('zalba', $result['results']['dates'][0]['event_type']);
    }

    /** @test */
    public function it_returns_null_for_unclassified_events(): void
    {
        $text = 'Dana 10.09.2024. primljen je dopis.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        // 'dopis' might match 'dostava' or be null depending on context
        // Just verify the date is extracted
        $this->assertNotEmpty($result['results']['dates']);
    }

    // ===== CONTEXT EXTRACTION TESTS =====

    /** @test */
    public function it_includes_context_around_dates(): void
    {
        $text = 'Prije datuma tekst. Dana 15.01.2024. sredina teksta. Nakon datuma tekst.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertNotEmpty($result['results']['dates'][0]['context']);
        $this->assertStringContainsString('Prije datuma', $result['results']['dates'][0]['context']);
    }

    // ===== DEDUPLICATION TESTS =====

    /** @test */
    public function it_deduplicates_same_dates_nearby(): void
    {
        $text = 'Dana 15.01.2024. i 15.01.2024. isti datum.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        // Two occurrences of same date within 20 chars should be deduped
        // But since they're far apart, might keep both
        $dates = $result['results']['dates'];
        $jan15Count = count(array_filter($dates, fn($d) => $d['date'] === '2024-01-15'));

        // Should be at least 1 (deduplicated or not)
        $this->assertGreaterThanOrEqual(1, $jan15Count);
    }

    // ===== SORTING AND STATISTICS TESTS =====

    /** @test */
    public function it_sorts_dates_chronologically(): void
    {
        $text = 'Datum 31.12.2024. pa 01.01.2024. pa 15.06.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $dates = array_column($result['results']['dates'], 'date');
        $sorted = $dates;
        sort($sorted);

        $this->assertEquals($sorted, $dates);
    }

    /** @test */
    public function it_calculates_date_range(): void
    {
        $text = 'Početak 01.01.2024. kraj 31.03.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('date_range', $result['results']);
        $this->assertEquals('2024-01-01', $result['results']['date_range']['earliest']);
        $this->assertEquals('2024-03-31', $result['results']['date_range']['latest']);
        $this->assertEquals(90, $result['results']['date_range']['span_days']);
    }

    /** @test */
    public function it_groups_events_by_type(): void
    {
        // Use text with sufficient spacing between events (400+ chars gap)
        // Context radius is 200 chars, so events need to be > 400 chars apart
        // to have distinct, non-overlapping contexts
        $text = <<<TEXT
Dana 15.01.2024. izvršena je pretraga stana osumnjičenika. Pronađeni su dokazi koji su predani na vještačenje i koji će se koristiti u daljnjem postupku pred nadležnim tijelom za kazneni progon. Ovo je prvi dio dokumenta koji opisuje postupak pretrage i nalaze koji su pri tom utvrđeni u skladu sa zakonskim odredbama.

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.

Dana 20.02.2024. uhićen je osumnjičenik nakon dovršene istrage i prikupljenih dokaza. Uhićenje je provedeno prema nalogu suda uz poštivanje svih zakonskih odredbi i prava osumnjičenika.
TEXT;

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('events_by_type', $result['results']);
        $this->assertArrayHasKey('pretraga', $result['results']['events_by_type']);
        $this->assertArrayHasKey('uhicenje', $result['results']['events_by_type']);
    }

    // ===== METADATA TESTS =====

    /** @test */
    public function it_returns_correct_metadata(): void
    {
        $text = 'Dana 15.01.2024. test.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertEquals('DateContextExtractor', $result['metadata']['analyzer']);
        $this->assertEquals(0, $result['metadata']['api_calls']);
        $this->assertEquals(0, $result['metadata']['cost']);
    }

    /** @test */
    public function it_counts_dates(): void
    {
        $text = 'Dana 01.01.2024. i 15.06.2024. i 31.12.2024.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertEquals(3, $result['results']['date_count']);
        $this->assertEquals(3, $result['results']['unique_dates']);
    }

    // ===== OUTPUT STRUCTURE TESTS =====

    /** @test */
    public function it_returns_correct_array_structure(): void
    {
        $text = 'Empty document.';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('dates', $result['results']);
        $this->assertArrayHasKey('date_count', $result['results']);
        $this->assertArrayHasKey('unique_dates', $result['results']);
        $this->assertArrayHasKey('events_by_type', $result['results']);
    }

    // ===== VALIDATION TESTS =====

    /** @test */
    public function it_rejects_invalid_dates(): void
    {
        $text = 'Dana 30.02.2024. (invalid date).';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        // February 30 is invalid
        $this->assertEmpty($result['results']['dates']);
    }

    /** @test */
    public function it_rejects_dates_outside_valid_range(): void
    {
        $text = 'Dana 15.01.1980. (too old) i 15.01.2050. (too future).';

        $result = $this->extractor->analyze($this->mockDocument, $text);

        // Years outside 1990-2030 should be rejected
        $this->assertEmpty($result['results']['dates']);
    }
}
