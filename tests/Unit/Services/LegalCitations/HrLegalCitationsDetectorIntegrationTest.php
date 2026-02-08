<?php

namespace Tests\Unit\Services\LegalCitations;

use App\Services\LegalCitations\CaseNumberDetector;
use App\Services\LegalCitations\DateDetector;
use App\Services\LegalCitations\EcliDetector;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\LegalCitations\NarodneNovineDetector;
use App\Services\LegalCitations\StatuteCitationDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for HrLegalCitationsDetector using real implementations
 * These tests verify actual Croatian legal citation detection patterns
 * without mocking, serving as regression tests for real-world text.
 */
class HrLegalCitationsDetectorIntegrationTest extends TestCase
{
    protected HrLegalCitationsDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real implementations, not mocks
        $this->detector = new HrLegalCitationsDetector(
            new StatuteCitationDetector(),
            new NarodneNovineDetector(),
            new CaseNumberDetector(),
            new EcliDetector(),
            new DateDetector()
        );
    }

    // ============================================================
    // NARODNE NOVINE (NN) CITATION TESTS
    // Official Gazette of the Republic of Croatia
    // ============================================================

    #[Test]
    public function it_detects_nn_basic_format(): void
    {
        // Basic "NN broj/godina" format
        $text = 'Zakon o parničnom postupku (NN 53/91)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN citation');
        $this->assertContains('53/91', $result['narodne_novine'][0]['issues']);
    }

    #[Test]
    public function it_detects_nn_with_full_name(): void
    {
        // Full "Narodne novine" format
        $text = 'Objavljen u Narodne novine 152/08';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect full Narodne novine format');
        $this->assertContains('152/08', $result['narodne_novine'][0]['issues']);
    }

    #[Test]
    public function it_detects_nn_with_br_abbreviation(): void
    {
        // "NN br." format
        $text = 'Temeljem članka 5. Zakona (NN br. 123/20)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN br. format');
        $this->assertContains('123/20', $result['narodne_novine'][0]['issues']);
    }

    #[Test]
    public function it_detects_nn_with_broj_full(): void
    {
        // "NN broj" format
        $text = 'Zakon (NN broj 45/2021)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN broj format');
        $this->assertContains('45/2021', $result['narodne_novine'][0]['issues']);
    }

    #[Test]
    public function it_detects_multiple_nn_issues_comma_separated(): void
    {
        // Multiple issues separated by commas - common in Croatian laws
        $text = 'Zakon o parničnom postupku (NN 53/91, 91/92, 112/99, 129/00, 88/01, 117/03)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect multiple NN issues');
        $issues = $result['narodne_novine'][0]['issues'];

        $this->assertContains('53/91', $issues, 'Should find first issue');
        $this->assertContains('91/92', $issues, 'Should find second issue');
        $this->assertContains('112/99', $issues, 'Should find third issue');
        $this->assertContains('129/00', $issues, 'Should find fourth issue');
        $this->assertContains('88/01', $issues, 'Should find fifth issue');
        $this->assertContains('117/03', $issues, 'Should find sixth issue');
    }

    #[Test]
    public function it_detects_nn_with_four_digit_year(): void
    {
        // Four-digit year format
        $text = 'Prema Zakonu (NN 123/2023)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN with 4-digit year');
        $this->assertContains('123/2023', $result['narodne_novine'][0]['issues']);
    }

    #[Test]
    public function it_handles_nn_with_three_digit_issue_number(): void
    {
        // Three-digit issue numbers
        $text = 'Zakon (NN 152/08)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine']);
        $this->assertContains('152/08', $result['narodne_novine'][0]['issues']);
    }

    // ============================================================
    // ECLI (European Case Law Identifier) TESTS
    // ============================================================

    #[Test]
    public function it_detects_ecli_vrhovni_sud(): void
    {
        // Supreme Court (Vrhovni sud Republike Hrvatske)
        $text = 'Vidi odluku ECLI:HR:VSRH:2024:123';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI for Vrhovni sud');
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_ecli_ustavni_sud(): void
    {
        // Constitutional Court (Ustavni sud Republike Hrvatske)
        $text = 'Odluka Ustavnog suda ECLI:HR:USRH:2023:456';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI for Ustavni sud');
        $this->assertEquals('ECLI:HR:USRH:2023:456', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_ecli_visoki_trgovacki_sud(): void
    {
        // High Commercial Court (Visoki trgovački sud)
        $text = 'Presuda ECLI:HR:VTSRH:2024:789';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI for Visoki trgovački sud');
        $this->assertEquals('ECLI:HR:VTSRH:2024:789', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_ecli_with_croatian_diacritics(): void
    {
        // Court codes with Croatian special characters
        $text = 'Odluka ECLI:HR:ŽSZG:2024:100 Županijskog suda u Zagrebu';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI with Croatian diacritics');
        $this->assertEquals('ECLI:HR:ŽSZG:2024:100', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_ecli_with_alphanumeric_identifier(): void
    {
        // ECLI with alphanumeric decision identifier
        $text = 'ECLI:HR:VSRH:2024:REV1234';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI with alphanumeric ID');
        $this->assertEquals('ECLI:HR:VSRH:2024:REV1234', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_ecli_with_dotted_identifier(): void
    {
        // ECLI with dots in identifier
        $text = 'Odluka ECLI:HR:VSRH:2024:12.34.56';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI with dotted ID');
        $this->assertEquals('ECLI:HR:VSRH:2024:12.34.56', $result['ecli'][0]['canonical']);
    }

    #[Test]
    public function it_detects_multiple_ecli_in_same_text(): void
    {
        // Multiple ECLI citations
        $text = 'Vidi ECLI:HR:VSRH:2024:100 i ECLI:HR:USRH:2023:200';

        $result = $this->detector->detectAll($text);

        $this->assertCount(2, $result['ecli'], 'Should detect both ECLI citations');
    }

    #[Test]
    public function it_does_not_detect_malformed_ecli(): void
    {
        // Invalid ECLI formats should not be detected
        $invalidTexts = [
            'ECLI:HR:VS:2024:123',       // Court code too short
            'ECLI:DE:VSRH:2024:123',     // Wrong country code (Germany)
            'ECLI:HR:VSRH:24:123',       // Year too short
            'ECLI:HR:12345:2024:123',    // Court code not letters
        ];

        foreach ($invalidTexts as $text) {
            $result = $this->detector->detectAll($text);
            $this->assertEmpty($result['ecli'], "Should NOT detect malformed ECLI: $text");
        }
    }

    // ============================================================
    // COMPLEX REAL-WORLD CITATION TESTS
    // ============================================================

    #[Test]
    public function it_detects_all_citation_types_in_complex_legal_text(): void
    {
        // Real-world-like legal text with multiple citation types
        $text = <<<TEXT
REPUBLIKA HRVATSKA
VRHOVNI SUD REPUBLIKE HRVATSKE

Broj: ECLI:HR:VSRH:2024:REV1234

R J E Š E N J E

Vrhovni sud Republike Hrvatske, u vijeću, u pravnoj stvari tužitelja Ivana Horvata, protiv
tuženika ACME d.o.o., radi naknade štete, temeljem članka 382. stavka 1. Zakona o parničnom
postupku (Narodne novine 53/91, 91/92, 112/99, 129/00, 88/01, 117/03, 88/05, 02/07, 84/08,
96/08, 123/08, 57/11, 25/13, 89/14 - Odluka USRH, 70/19), dana 15.01.2024. godine

r i j e š i o    j e

Revizija se odbija.

O b r a z l o ž e n j e

Prvostupanjskom presudom Općinskog suda u Zagrebu poslovni broj P-1234/2022 od 10.05.2023.
djelomično je usvojen tužbeni zahtjev. Drugostupanjskom presudom Županijskog suda u Zagrebu
poslovni broj Gž-5678/2023 od 20.09.2023. (ECLI:HR:ŽSZG:2023:5678) potvrđena je prvostupanjska
presuda u dijelu u kojem je tužbeni zahtjev odbijen.

Vidi odluku Ustavnog suda ECLI:HR:USRH:2023:100.
TEXT;

        $result = $this->detector->detectAll($text);
        $stats = $this->detector->getStatistics($text);

        // Should find NN citations
        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN citations');

        // Should find ECLI citations
        $this->assertGreaterThanOrEqual(3, count($result['ecli']), 'Should detect at least 3 ECLI citations');

        // Should find dates
        $this->assertNotEmpty($result['dates'], 'Should detect dates');

        // Total citations should be significant
        $this->assertGreaterThan(10, $stats['total_citations'], 'Complex legal text should have many citations');
    }

    #[Test]
    public function it_extracts_law_numbers_from_text(): void
    {
        $text = 'Zakon (NN 53/91, 91/92, 112/99) i Zakon (NN 152/08)';

        $lawNumbers = $this->detector->extractLawNumbers($text);

        $this->assertContains('53/91', $lawNumbers);
        $this->assertContains('91/92', $lawNumbers);
        $this->assertContains('112/99', $lawNumbers);
        $this->assertContains('152/08', $lawNumbers);
    }

    #[Test]
    public function it_extracts_canonical_citations(): void
    {
        $text = 'Vidi ECLI:HR:VSRH:2024:123 i predmet Rev 456/2024';

        $canonicals = $this->detector->extractCanonicalCitations($text);

        $this->assertContains('ECLI:HR:VSRH:2024:123', $canonicals, 'Should include ECLI canonical');
    }

    #[Test]
    public function it_correctly_reports_has_citations(): void
    {
        $textWithCitations = 'Zakon (NN 53/91)';
        $textWithoutCitations = 'Ovo je tekst bez citata';

        $this->assertTrue($this->detector->hasCitations($textWithCitations), 'Should detect citations');
        $this->assertFalse($this->detector->hasCitations($textWithoutCitations), 'Should not detect citations');
    }

    #[Test]
    public function it_handles_empty_text(): void
    {
        $result = $this->detector->detectAll('');
        $stats = $this->detector->getStatistics('');

        $this->assertEmpty($result['statutes']);
        $this->assertEmpty($result['narodne_novine']);
        $this->assertEmpty($result['case_numbers']);
        $this->assertEmpty($result['ecli']);
        $this->assertEmpty($result['dates']);
        $this->assertEquals(0, $stats['total_citations']);
    }

    #[Test]
    public function it_handles_croatian_unicode_text(): void
    {
        // Text with Croatian special characters
        $text = 'Članak 291. ZKP-a (NN 152/08) propisuje, da će Županijski sud u Zagrebu odlučiti o žalbi.';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should work with Croatian unicode text');
    }

    #[Test]
    public function it_provides_accurate_statistics(): void
    {
        $text = 'Zakon (NN 53/91, 91/92) i odluka ECLI:HR:VSRH:2024:123 od 15.01.2024';

        $stats = $this->detector->getStatistics($text);

        $this->assertArrayHasKey('total_citations', $stats);
        $this->assertArrayHasKey('statute_citations', $stats);
        $this->assertArrayHasKey('nn_citations', $stats);
        $this->assertArrayHasKey('case_citations', $stats);
        $this->assertArrayHasKey('ecli_citations', $stats);
        $this->assertArrayHasKey('dates_found', $stats);

        // Should have at least: 2 NN issues + 1 ECLI + 1 date = 4
        $this->assertGreaterThanOrEqual(4, $stats['total_citations']);
    }

    // ============================================================
    // EDGE CASES AND REGRESSION TESTS
    // ============================================================

    #[Test]
    public function it_handles_nn_at_sentence_boundary(): void
    {
        $text = 'Propis. NN 53/91. Sljedeća rečenica.';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN at sentence boundary');
    }

    #[Test]
    public function it_handles_ecli_at_sentence_boundary(): void
    {
        $text = 'Vidi predmet. ECLI:HR:VSRH:2024:123. Nastavak.';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI at sentence boundary');
    }

    #[Test]
    public function it_does_not_match_partial_nn_format(): void
    {
        // Should not match incomplete NN references
        $text = 'Tekst NN i još neki tekst';

        $result = $this->detector->detectAll($text);

        $this->assertEmpty($result['narodne_novine'], 'Should not match incomplete NN format');
    }

    #[Test]
    public function it_handles_real_zpp_citation(): void
    {
        // Real ZPP (Code of Civil Procedure) citation
        $text = <<<TEXT
Zakon o parničnom postupku (Narodne novine, br. 53/91, 91/92, 112/99, 88/01, 117/03, 88/05,
02/07, 84/08, 96/08, 123/08, 57/11, 148/11 - pročišćeni tekst, 25/13, 89/14 - Odluka i
Rješenje USRH, 70/19)
TEXT;

        $result = $this->detector->detectAll($text);
        $stats = $this->detector->getStatistics($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN in ZPP citation');
        $this->assertGreaterThan(5, $stats['nn_citations'], 'Should find multiple NN issues in ZPP');
    }

    #[Test]
    public function it_handles_real_zoo_citation(): void
    {
        // Real ZOO (Obligations Act) citation
        $text = 'Zakon o obveznim odnosima (NN 35/05, 41/08, 125/11, 78/15, 29/18, 126/21, 114/22, 156/22)';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['narodne_novine'], 'Should detect NN in ZOO citation');
        $issues = $result['narodne_novine'][0]['issues'];
        $this->assertContains('35/05', $issues);
        $this->assertContains('156/22', $issues);
    }

    #[Test]
    public function it_handles_case_number_format_with_court_prefix(): void
    {
        // Standard Croatian case number format
        $text = 'Predmet broj Rev 1234/2024';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['case_numbers'], 'Should detect case number with Rev prefix');
    }

    #[Test]
    public function it_extracts_case_ids_for_database_lookup(): void
    {
        $text = 'Predmeti Rev 123/2024 i Gž 456/2023';

        $caseIds = $this->detector->extractCaseIds($text);

        // Should extract canonical case identifiers
        $this->assertNotEmpty($caseIds, 'Should extract case IDs');
    }
}
