<?php

namespace Tests\Unit\Services\LegalCitations;

use App\Services\LegalCitations\CaseNumberDetector;
use App\Services\LegalCitations\DateDetector;
use App\Services\LegalCitations\EcliDetector;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\LegalCitations\NarodneNovineDetector;
use App\Services\LegalCitations\StatuteCitationDetector;
use Mockery;
use Tests\TestCase;

class HrLegalCitationsDetectorTest extends TestCase
{
    protected HrLegalCitationsDetector $detector;

    protected $statuteDetector;

    protected $nnDetector;

    protected $caseDetector;

    protected $ecliDetector;

    protected $dateDetector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statuteDetector = Mockery::mock(StatuteCitationDetector::class);
        $this->nnDetector = Mockery::mock(NarodneNovineDetector::class);
        $this->caseDetector = Mockery::mock(CaseNumberDetector::class);
        $this->ecliDetector = Mockery::mock(EcliDetector::class);
        $this->dateDetector = Mockery::mock(DateDetector::class);

        $this->detector = new HrLegalCitationsDetector(
            $this->statuteDetector,
            $this->nnDetector,
            $this->caseDetector,
            $this->ecliDetector,
            $this->dateDetector
        );
    }

    /** @test */
    public function it_detects_all_citation_types()
    {
        $text = 'According to ZKP čl. 291, published in NN 152/08, see case Rev 123/2024 and ECLI:HR:VSRH:2024:123 from 15.01.2024';

        $this->statuteDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['canonical' => 'ZKP:čl.291', 'law' => 'ZKP', 'article' => '291'],
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['raw' => 'NN 152/08', 'issues' => ['152/08']],
        ]);

        $this->caseDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['canonical' => 'Rev 123/2024', 'prefix' => 'Rev', 'number' => '123', 'year' => '2024'],
        ]);

        $this->ecliDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['canonical' => 'ECLI:HR:VSRH:2024:123', 'raw' => 'ECLI:HR:VSRH:2024:123'],
        ]);

        $this->dateDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['raw' => '15.01.2024', 'normalized' => '2024-01-15'],
        ]);

        $result = $this->detector->detectAll($text);

        $this->assertArrayHasKey('statutes', $result);
        $this->assertArrayHasKey('narodne_novine', $result);
        $this->assertArrayHasKey('case_numbers', $result);
        $this->assertArrayHasKey('ecli', $result);
        $this->assertArrayHasKey('dates', $result);

        $this->assertCount(1, $result['statutes']);
        $this->assertCount(1, $result['narodne_novine']);
        $this->assertCount(1, $result['case_numbers']);
        $this->assertCount(1, $result['ecli']);
        $this->assertCount(1, $result['dates']);
    }

    /** @test */
    public function it_extracts_canonical_citations()
    {
        $text = 'ZKP čl. 291, Rev 123/2024, ECLI:HR:VSRH:2024:456';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ZKP:čl.291'],
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([]);

        $this->caseDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'Rev 123/2024'],
        ]);

        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ECLI:HR:VSRH:2024:456'],
        ]);

        $this->dateDetector->shouldReceive('detect')->once()->andReturn([]);

        $canonicals = $this->detector->extractCanonicalCitations($text);

        $this->assertCount(3, $canonicals);
        $this->assertContains('ZKP:čl.291', $canonicals);
        $this->assertContains('Rev 123/2024', $canonicals);
        $this->assertContains('ECLI:HR:VSRH:2024:456', $canonicals);
    }

    /** @test */
    public function it_deduplicates_canonical_citations()
    {
        $text = 'Test text';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ZKP:čl.291'],
            ['canonical' => 'ZKP:čl.291'], // Duplicate
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->caseDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->dateDetector->shouldReceive('detect')->once()->andReturn([]);

        $canonicals = $this->detector->extractCanonicalCitations($text);

        $this->assertCount(1, $canonicals);
    }

    /** @test */
    public function it_gets_citation_statistics()
    {
        $text = 'Test text with multiple citations';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ZKP:čl.291'],
            ['canonical' => 'ZKP:čl.292'],
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([
            ['issues' => ['152/08']],
        ]);

        $this->caseDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'Rev 123/2024'],
        ]);

        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ECLI:HR:VSRH:2024:123'],
        ]);

        $this->dateDetector->shouldReceive('detect')->once()->andReturn([
            ['raw' => '15.01.2024'],
            ['raw' => '16.01.2024'],
        ]);

        $stats = $this->detector->getStatistics($text);

        $this->assertEquals(7, $stats['total_citations']);
        $this->assertEquals(2, $stats['statute_citations']);
        $this->assertEquals(1, $stats['nn_citations']);
        $this->assertEquals(1, $stats['case_citations']);
        $this->assertEquals(1, $stats['ecli_citations']);
        $this->assertEquals(2, $stats['dates_found']);
    }

    /** @test */
    public function it_extracts_law_numbers()
    {
        $text = 'NN 152/08, NN 110/11, NN 91/12';

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([
            ['issues' => ['152/08', '110/11']],
            ['issues' => ['91/12']],
        ]);

        $lawNumbers = $this->detector->extractLawNumbers($text);

        $this->assertCount(3, $lawNumbers);
        $this->assertContains('152/08', $lawNumbers);
        $this->assertContains('110/11', $lawNumbers);
        $this->assertContains('91/12', $lawNumbers);
    }

    /** @test */
    public function it_deduplicates_law_numbers()
    {
        $text = 'Test';

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([
            ['issues' => ['152/08', '152/08']], // Duplicate
            ['issues' => ['152/08']], // Another duplicate
        ]);

        $lawNumbers = $this->detector->extractLawNumbers($text);

        $this->assertCount(1, $lawNumbers);
        $this->assertContains('152/08', $lawNumbers);
    }

    /** @test */
    public function it_extracts_case_ids()
    {
        $text = 'Cases: Rev 123/2024, Gž 456/2023';

        $this->caseDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'Rev 123/2024'],
            ['canonical' => 'Gž 456/2023'],
        ]);

        $caseIds = $this->detector->extractCaseIds($text);

        $this->assertCount(2, $caseIds);
        $this->assertContains('Rev 123/2024', $caseIds);
        $this->assertContains('Gž 456/2023', $caseIds);
    }

    /** @test */
    public function it_checks_if_text_has_citations()
    {
        $textWithCitations = 'ZKP čl. 291';
        $textWithoutCitations = 'No citations here';

        $this->statuteDetector->shouldReceive('detect')->twice()->andReturn(
            [['canonical' => 'ZKP:čl.291']],
            []
        );

        $this->nnDetector->shouldReceive('detect')->twice()->andReturn([], []);
        $this->caseDetector->shouldReceive('detect')->twice()->andReturn([], []);
        $this->ecliDetector->shouldReceive('detect')->twice()->andReturn([], []);
        $this->dateDetector->shouldReceive('detect')->twice()->andReturn([], []);

        $this->assertTrue($this->detector->hasCitations($textWithCitations));
        $this->assertFalse($this->detector->hasCitations($textWithoutCitations));
    }

    /** @test */
    public function it_handles_empty_text()
    {
        $this->statuteDetector->shouldReceive('detect')->once()->with('')->andReturn([]);
        $this->nnDetector->shouldReceive('detect')->once()->with('')->andReturn([]);
        $this->caseDetector->shouldReceive('detect')->once()->with('')->andReturn([]);
        $this->ecliDetector->shouldReceive('detect')->once()->with('')->andReturn([]);
        $this->dateDetector->shouldReceive('detect')->once()->with('')->andReturn([]);

        $result = $this->detector->detectAll('');

        $this->assertEmpty($result['statutes']);
        $this->assertEmpty($result['narodne_novine']);
        $this->assertEmpty($result['case_numbers']);
        $this->assertEmpty($result['ecli']);
        $this->assertEmpty($result['dates']);
    }

    /** @test */
    public function it_handles_croatian_unicode_text()
    {
        $text = 'Članak 291. Zakona o kaznenom postupku, objavljen u NN 152/08';

        $this->statuteDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['canonical' => 'ZKP:čl.291'],
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->with($text)->andReturn([
            ['issues' => ['152/08']],
        ]);

        $this->caseDetector->shouldReceive('detect')->once()->with($text)->andReturn([]);
        $this->ecliDetector->shouldReceive('detect')->once()->with($text)->andReturn([]);
        $this->dateDetector->shouldReceive('detect')->once()->with($text)->andReturn([]);

        $result = $this->detector->detectAll($text);

        $this->assertCount(1, $result['statutes']);
        $this->assertCount(1, $result['narodne_novine']);
    }

    /** @test */
    public function it_handles_mixed_citation_types_in_complex_text()
    {
        $text = 'Prema članku 291. ZKP-a (NN 152/08, 121/11, 56/13), Vrhovni sud u predmetu Rev 1234/2023 (ECLI:HR:VSRH:2023:1234) donesenom 15.12.2023...';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ZKP:čl.291'],
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([
            ['issues' => ['152/08', '121/11', '56/13']],
        ]);

        $this->caseDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'Rev 1234/2023'],
        ]);

        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([
            ['canonical' => 'ECLI:HR:VSRH:2023:1234'],
        ]);

        $this->dateDetector->shouldReceive('detect')->once()->andReturn([
            ['raw' => '15.12.2023'],
        ]);

        $stats = $this->detector->getStatistics($text);

        // Total: 1 statute + 3 NN issues + 1 case + 1 ECLI + 1 date = 7
        $this->assertEquals(7, $stats['total_citations']);
    }

    /** @test */
    public function it_extracts_only_unique_law_numbers()
    {
        $text = 'Test';

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([
            ['issues' => ['152/08', '110/11', '152/08']],
        ]);

        $lawNumbers = $this->detector->extractLawNumbers($text);

        $this->assertCount(2, $lawNumbers);
    }

    /** @test */
    public function it_handles_no_canonical_in_results()
    {
        $text = 'Test';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([
            ['raw' => 'something', 'article' => '291'], // No canonical key
        ]);

        $this->nnDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->caseDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->dateDetector->shouldReceive('detect')->once()->andReturn([]);

        $canonicals = $this->detector->extractCanonicalCitations($text);

        $this->assertEmpty($canonicals);
    }

    /** @test */
    public function it_returns_zero_statistics_for_text_without_citations()
    {
        $text = 'Plain text without any legal references';

        $this->statuteDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->nnDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->caseDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->ecliDetector->shouldReceive('detect')->once()->andReturn([]);
        $this->dateDetector->shouldReceive('detect')->once()->andReturn([]);

        $stats = $this->detector->getStatistics($text);

        $this->assertEquals(0, $stats['total_citations']);
        $this->assertEquals(0, $stats['statute_citations']);
        $this->assertEquals(0, $stats['nn_citations']);
        $this->assertEquals(0, $stats['case_citations']);
        $this->assertEquals(0, $stats['ecli_citations']);
        $this->assertEquals(0, $stats['dates_found']);
    }
}
