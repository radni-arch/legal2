<?php

namespace Tests\Unit\Services;

use App\Services\HrLegalCitationsDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for HrLegalCitationsDetector (app/Services/ facade version)
 *
 * This detector delegates to sub-detectors (StatuteCitationDetector, NarodneNovineDetector,
 * CaseNumberDetector, EcliDetector, DateDetector, CourtTypeDetector, LegalTermDetector)
 * and provides normalized extraction via extract(), detectAll(), and extractKeywords().
 */
class HrLegalCitationsDetectorTest extends TestCase
{
    protected HrLegalCitationsDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new HrLegalCitationsDetector;
    }

    // ============================================================
    // detectAll() - STRUCTURE AND KEYS
    // ============================================================

    #[Test]
    public function detect_all_returns_all_seven_detector_keys(): void
    {
        $result = $this->detector->detectAll('Neki tekst');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('statutes', $result);
        $this->assertArrayHasKey('nn', $result);
        $this->assertArrayHasKey('cases', $result);
        $this->assertArrayHasKey('ecli', $result);
        $this->assertArrayHasKey('dates', $result);
        $this->assertArrayHasKey('courts', $result);
        $this->assertArrayHasKey('legal_terms', $result);
    }

    #[Test]
    public function detect_all_returns_arrays_for_each_key(): void
    {
        $result = $this->detector->detectAll('Tekst bez citata.');

        foreach ($result as $key => $value) {
            $this->assertIsArray($value, "Key '$key' should be an array");
        }
    }

    #[Test]
    public function detect_all_returns_empty_arrays_for_empty_text(): void
    {
        $result = $this->detector->detectAll('');

        $this->assertIsArray($result);
        $this->assertCount(7, $result, 'Should have exactly 7 detector keys');

        foreach ($result as $key => $value) {
            $this->assertIsArray($value, "Key '$key' should be an array");
        }
    }

    // ============================================================
    // detect() - SPECIFIC TYPE DETECTION
    // ============================================================

    #[Test]
    public function detect_throws_exception_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown citation type: invalid');

        $this->detector->detect('invalid', 'some text');
    }

    #[Test]
    public function detect_statutes_returns_law_and_article(): void
    {
        $result = $this->detector->detect('statutes', 'ZPP čl. 110 st. 2');

        $this->assertNotEmpty($result);
        $this->assertEquals('ZPP', $result[0]['law']);
        $this->assertEquals('110', $result[0]['article']);
        $this->assertEquals('2', $result[0]['paragraph']);
    }

    #[Test]
    public function detect_nn_returns_issues_array(): void
    {
        $result = $this->detector->detect('nn', 'Objavljeno u NN 123/20');

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('issues', $result[0]);
        $this->assertContains('123/20', $result[0]['issues']);
    }

    #[Test]
    public function detect_cases_returns_prefix_number_year(): void
    {
        $result = $this->detector->detect('cases', 'Predmet Rev-123/2024');

        $this->assertNotEmpty($result);
        $this->assertEquals('Rev', $result[0]['prefix']);
        $this->assertEquals('123', $result[0]['number']);
        $this->assertEquals('2024', $result[0]['year']);
    }

    #[Test]
    public function detect_ecli_returns_canonical_format(): void
    {
        $result = $this->detector->detect('ecli', 'ECLI:HR:VSRH:2024:123');

        $this->assertNotEmpty($result);
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result[0]['canonical']);
    }

    #[Test]
    public function detect_dates_returns_array(): void
    {
        $result = $this->detector->detect('dates', 'Datum: 15. siječnja 2024.');

        $this->assertIsArray($result);
    }

    #[Test]
    public function detect_courts_returns_court_types(): void
    {
        $result = $this->detector->detect('courts', 'Vrhovni sud Republike Hrvatske');

        $this->assertNotEmpty($result);
        $types = array_column($result, 'type');
        $this->assertContains('supreme', $types);
    }

    #[Test]
    public function detect_legal_terms_returns_terms_with_categories(): void
    {
        // LegalTermDetector uses exact word boundary matching, not stemming
        // So we must use the nominative/base form from the LEGAL_TERMS dictionary
        $result = $this->detector->detect('legal_terms', 'tužitelj podnosi tužba');

        $this->assertNotEmpty($result);
        $terms = array_column($result, 'term');
        $this->assertContains('tužba', $terms);
        $this->assertContains('tužitelj', $terms);
    }

    // ============================================================
    // NN (NARODNE NOVINE) CITATION FORMAT TESTS
    // ============================================================

    #[Test]
    #[DataProvider('nnCitationProvider')]
    public function it_detects_nn_citation_formats(string $text, string $expectedIssue): void
    {
        $result = $this->detector->detect('nn', $text);

        $this->assertNotEmpty($result, "Should detect NN citation in: $text");

        $allIssues = [];
        foreach ($result as $detection) {
            $allIssues = array_merge($allIssues, $detection['issues']);
        }

        $this->assertContains($expectedIssue, $allIssues, "Should find issue $expectedIssue in: $text");
    }

    public static function nnCitationProvider(): array
    {
        return [
            'basic NN format' => ['NN 53/91', '53/91'],
            'NN with br. abbreviation' => ['NN br. 123/20', '123/20'],
            'NN with broj full' => ['NN broj 45/2021', '45/2021'],
            'full Narodne novine' => ['Narodne novine 152/08', '152/08'],
            'three-digit issue number' => ['NN 152/08', '152/08'],
            'four-digit year' => ['NN 123/2023', '123/2023'],
            'inside parentheses' => ['Zakon (NN 35/05)', '35/05'],
            'with comma-separated issues' => ['NN 53/91, 91/92', '53/91'],
            'second comma-separated issue' => ['NN 53/91, 91/92', '91/92'],
        ];
    }

    #[Test]
    public function it_detects_multiple_nn_issues_in_parenthetical(): void
    {
        $text = 'Zakon o parničnom postupku (NN 53/91, 91/92, 112/99, 129/00, 88/01)';

        $result = $this->detector->detect('nn', $text);

        $this->assertNotEmpty($result);
        $issues = $result[0]['issues'];

        $this->assertContains('53/91', $issues);
        $this->assertContains('91/92', $issues);
        $this->assertContains('112/99', $issues);
        $this->assertContains('129/00', $issues);
        $this->assertContains('88/01', $issues);
        $this->assertCount(5, $issues);
    }

    #[Test]
    public function it_does_not_detect_nn_without_issue_number(): void
    {
        $text = 'Tekst koji sadrži NN ali bez broja.';

        $result = $this->detector->detect('nn', $text);

        $this->assertEmpty($result, 'Should not match NN without issue number');
    }

    // ============================================================
    // ECLI CITATION PARSING TESTS
    // ============================================================

    #[Test]
    #[DataProvider('ecliCitationProvider')]
    public function it_detects_ecli_citation_formats(string $text, string $expectedCanonical): void
    {
        $result = $this->detector->detect('ecli', $text);

        $this->assertNotEmpty($result, "Should detect ECLI in: $text");
        $this->assertEquals($expectedCanonical, $result[0]['canonical']);
    }

    public static function ecliCitationProvider(): array
    {
        return [
            'Vrhovni sud (VSRH)' => ['ECLI:HR:VSRH:2024:123', 'ECLI:HR:VSRH:2024:123'],
            'Ustavni sud (USRH)' => ['ECLI:HR:USRH:2023:456', 'ECLI:HR:USRH:2023:456'],
            'Visoki trgovacki sud (VTSRH)' => ['ECLI:HR:VTSRH:2024:789', 'ECLI:HR:VTSRH:2024:789'],
            'alphanumeric identifier' => ['ECLI:HR:VSRH:2024:REV1234', 'ECLI:HR:VSRH:2024:REV1234'],
            'dotted identifier' => ['ECLI:HR:VSRH:2024:12.34.56', 'ECLI:HR:VSRH:2024:12.34.56'],
        ];
    }

    #[Test]
    public function it_detects_multiple_ecli_in_text(): void
    {
        $text = 'Vidi ECLI:HR:VSRH:2024:100 i ECLI:HR:USRH:2023:200';

        $result = $this->detector->detect('ecli', $text);

        $this->assertCount(2, $result);
        $canonicals = array_column($result, 'canonical');
        $this->assertContains('ECLI:HR:VSRH:2024:100', $canonicals);
        $this->assertContains('ECLI:HR:USRH:2023:200', $canonicals);
    }

    #[Test]
    public function it_does_not_detect_non_hr_ecli(): void
    {
        // Only Croatian ECLI (HR) should be detected
        $text = 'ECLI:DE:BVerfG:2024:123';

        $result = $this->detector->detect('ecli', $text);

        $this->assertEmpty($result, 'Should not detect non-Croatian ECLI');
    }

    // ============================================================
    // CASE NUMBER TESTS
    // ============================================================

    #[Test]
    #[DataProvider('caseNumberProvider')]
    public function it_detects_case_number_formats(string $text, string $expectedPrefix, string $expectedNumber, string $expectedYear): void
    {
        $result = $this->detector->detect('cases', $text);

        $this->assertNotEmpty($result, "Should detect case number in: $text");
        $this->assertEquals($expectedPrefix, $result[0]['prefix'], "Prefix mismatch for: $text");
        $this->assertEquals($expectedNumber, $result[0]['number'], "Number mismatch for: $text");
        $this->assertEquals($expectedYear, $result[0]['year'], "Year mismatch for: $text");
    }

    public static function caseNumberProvider(): array
    {
        return [
            'Revizija' => ['Rev-123/2024', 'Rev', '123', '2024'],
            'Graz. zalba' => ['Gž-456/2023', 'Gž', '456', '2023'],
            'Kaznena zalba' => ['Kž-789/2022', 'Kž', '789', '2022'],
            'Constitutional U-III' => ['U-III-1234/2019', 'U-III', '1234', '2019'],
            'Without dash' => ['Rev 100/2024', 'Rev', '100', '2024'],
            'Two-digit year' => ['Gž-456/23', 'Gž', '456', '23'],
        ];
    }

    #[Test]
    public function it_detects_multiple_case_numbers(): void
    {
        $text = 'Predmeti Rev-123/2024 i Gž-456/2023';

        $result = $this->detector->detect('cases', $text);

        $this->assertGreaterThanOrEqual(2, count($result));
        $prefixes = array_column($result, 'prefix');
        $this->assertContains('Rev', $prefixes);
        $this->assertContains('Gž', $prefixes);
    }

    // ============================================================
    // MIXED CITATIONS IN TEXT
    // ============================================================

    #[Test]
    public function it_detects_all_types_in_mixed_legal_text(): void
    {
        $text = 'Prema čl. 332 ZKP (NN 152/08) u predmetu Rev-1234/2024 (ECLI:HR:VSRH:2024:123) Vrhovni sud odlučio je dana 15.01.2024.';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['statutes'], 'Should detect statute citation');
        $this->assertNotEmpty($result['nn'], 'Should detect NN citation');
        $this->assertNotEmpty($result['cases'], 'Should detect case number');
        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI');
        $this->assertNotEmpty($result['courts'], 'Should detect court type');
    }

    #[Test]
    public function it_detects_citations_in_complex_real_world_text(): void
    {
        $text = <<<TEXT
Sukladno članku 10. stavku 2. Zakona o parničnom postupku (Narodne novine, br. 53/91, 91/92,
112/99, 129/00, 88/01, 117/03) Vrhovni sud Republike Hrvatske u predmetu Rev 1234/2023
(ECLI:HR:VSRH:2023:1234) donesenom dana 15. prosinca 2023. godine odlučio je da se revizija
odbija. Tužitelj se poziva na članak 1045. Zakona o obveznim odnosima.
TEXT;

        $result = $this->detector->detectAll($text);

        // NN citations
        $this->assertNotEmpty($result['nn'], 'Should detect NN in complex text');
        $issues = $result['nn'][0]['issues'];
        $this->assertContains('53/91', $issues, 'Should find first NN issue');

        // ECLI
        $this->assertNotEmpty($result['ecli'], 'Should detect ECLI in complex text');
        $this->assertEquals('ECLI:HR:VSRH:2023:1234', $result['ecli'][0]['canonical']);

        // Case numbers
        $this->assertNotEmpty($result['cases'], 'Should detect case numbers in complex text');

        // Courts
        $this->assertNotEmpty($result['courts'], 'Should detect courts in complex text');
    }

    // ============================================================
    // extract() - NORMALIZED EXTRACTION
    // ============================================================

    #[Test]
    public function extract_returns_normalized_structure(): void
    {
        $text = 'ZOO čl. 1045 prema NN 35/05 u predmetu Rev-789/2023';

        $result = $this->detector->extract($text);

        $this->assertArrayHasKey('laws', $result);
        $this->assertArrayHasKey('articles', $result);
        $this->assertArrayHasKey('case_numbers', $result);
        $this->assertArrayHasKey('court_types', $result);
        $this->assertArrayHasKey('legal_terms', $result);
        $this->assertArrayHasKey('nn_references', $result);
        $this->assertArrayHasKey('dates', $result);
        $this->assertArrayHasKey('has_specific_refs', $result);
    }

    #[Test]
    public function extract_includes_laws_from_statutes(): void
    {
        $text = 'ZOO čl. 1045';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['laws']);
        $lawValues = array_column($result['laws'], 'value');
        $this->assertContains('ZOO', $lawValues);
    }

    #[Test]
    public function extract_includes_laws_from_nn_references(): void
    {
        $text = 'Prema NN 35/05';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['laws']);
        $types = array_column($result['laws'], 'type');
        $this->assertContains('nn_reference', $types);
    }

    #[Test]
    public function extract_includes_article_details(): void
    {
        $text = 'ZOO čl. 1045 st. 1 toč. 3 al. 2';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['articles']);
        $article = $result['articles'][0];
        $this->assertEquals('1045', $article['number']);
        $this->assertEquals('1', $article['paragraph']);
        $this->assertEquals('3', $article['item']);
        $this->assertEquals('2', $article['alineja']);
    }

    #[Test]
    public function extract_includes_case_numbers_with_details(): void
    {
        $text = 'U predmetu Rev-789/2023';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['case_numbers']);
        $caseNum = $result['case_numbers'][0];
        $this->assertEquals('Rev', $caseNum['prefix']);
        $this->assertEquals('789', $caseNum['number']);
        $this->assertEquals('2023', $caseNum['year']);
    }

    #[Test]
    public function extract_sets_has_specific_refs_true_when_refs_present(): void
    {
        $text = 'Prema NN 123/45 i Rev-456/2022';

        $result = $this->detector->extract($text);

        $this->assertTrue($result['has_specific_refs']);
    }

    #[Test]
    public function extract_sets_has_specific_refs_false_when_no_refs(): void
    {
        $text = 'Neka opća pitanja o pravu.';

        $result = $this->detector->extract($text);

        $this->assertFalse($result['has_specific_refs']);
    }

    #[Test]
    public function extract_returns_empty_for_empty_text(): void
    {
        $result = $this->detector->extract('');

        $this->assertIsArray($result);
        $this->assertEmpty($result['laws']);
        $this->assertEmpty($result['articles']);
        $this->assertEmpty($result['case_numbers']);
        $this->assertFalse($result['has_specific_refs']);
    }

    #[Test]
    public function extract_deduplicates_identical_law_references(): void
    {
        // The normalizeLaws() method deduplicates by canonical (which includes article number).
        // Same law+article referenced twice should be deduplicated to one entry.
        $text = 'ZPP čl. 110 i prema ZPP čl. 110';

        $result = $this->detector->extract($text);

        $laws = $result['laws'];
        $zppLaws = array_filter($laws, fn ($l) => isset($l['abbreviation']) && $l['abbreviation'] === 'ZPP');

        $this->assertCount(1, $zppLaws, 'Should deduplicate identical ZPP:čl.110 references');
    }

    // ============================================================
    // extractKeywords() - KEYWORD EXTRACTION
    // ============================================================

    #[Test]
    public function extract_keywords_removes_stop_words(): void
    {
        $text = 'Što kaže zakon o obveznim odnosima u vezi s ugovorima?';

        $keywords = $this->detector->extractKeywords($text);

        $this->assertIsArray($keywords);
        $this->assertContains('zakon', $keywords);
        $this->assertContains('obveznim', $keywords);
        $this->assertContains('ugovorima', $keywords);

        // Stop words should be removed
        $this->assertNotContains('što', $keywords);
        $this->assertNotContains('o', $keywords);
        $this->assertNotContains('s', $keywords);
    }

    #[Test]
    public function extract_keywords_removes_short_words(): void
    {
        $text = 'u i na za od do po';

        $keywords = $this->detector->extractKeywords($text);

        $this->assertEmpty($keywords, 'All short words should be removed');
    }

    #[Test]
    public function extract_keywords_strips_punctuation(): void
    {
        $text = 'presuda, tužba. žalba! ugovor?';

        $keywords = $this->detector->extractKeywords($text);

        $this->assertContains('presuda', $keywords);
        $this->assertContains('tužba', $keywords);
        $this->assertContains('žalba', $keywords);
        $this->assertContains('ugovor', $keywords);
    }

    #[Test]
    public function extract_keywords_returns_unique_values(): void
    {
        $text = 'ugovor ugovor ugovor';

        $keywords = $this->detector->extractKeywords($text);

        $this->assertCount(1, $keywords, 'Should deduplicate keywords');
        $this->assertContains('ugovor', $keywords);
    }

    #[Test]
    public function extract_keywords_handles_empty_text(): void
    {
        $keywords = $this->detector->extractKeywords('');

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords);
    }

    // ============================================================
    // hasSpecificReferences() - REFERENCE DETECTION FLAG
    // ============================================================

    #[Test]
    public function has_specific_references_returns_false_for_null(): void
    {
        $result = $this->detector->hasSpecificReferences(null);

        $this->assertFalse($result);
    }

    #[Test]
    public function has_specific_references_returns_true_for_statutes(): void
    {
        $detected = ['statutes' => [['canonical' => 'ZPP:čl.110']], 'nn' => [], 'cases' => []];

        $result = $this->detector->hasSpecificReferences($detected);

        $this->assertTrue($result);
    }

    #[Test]
    public function has_specific_references_returns_true_for_nn(): void
    {
        $detected = ['statutes' => [], 'nn' => [['issues' => ['53/91']]], 'cases' => []];

        $result = $this->detector->hasSpecificReferences($detected);

        $this->assertTrue($result);
    }

    #[Test]
    public function has_specific_references_returns_true_for_cases(): void
    {
        $detected = ['statutes' => [], 'nn' => [], 'cases' => [['canonical' => 'Rev 123/2024']]];

        $result = $this->detector->hasSpecificReferences($detected);

        $this->assertTrue($result);
    }

    #[Test]
    public function has_specific_references_returns_false_for_empty_detected(): void
    {
        $detected = ['statutes' => [], 'nn' => [], 'cases' => []];

        $result = $this->detector->hasSpecificReferences($detected);

        $this->assertFalse($result);
    }

    // ============================================================
    // EDGE CASES AND REGRESSION TESTS
    // ============================================================

    #[Test]
    public function it_handles_text_without_any_citations(): void
    {
        $text = 'Obični tekst bez ikakvih citata ili pravnih referenci.';

        $result = $this->detector->detectAll($text);

        foreach (['statutes', 'nn', 'cases', 'ecli'] as $key) {
            $this->assertEmpty($result[$key], "Key '$key' should be empty for text without citations");
        }
    }

    #[Test]
    public function it_handles_unicode_text_correctly(): void
    {
        $text = 'Članak 291. ZKP-a (NN 152/08) propisuje, da će Županijski sud odlučiti o žalbi.';

        $result = $this->detector->detectAll($text);

        $this->assertNotEmpty($result['nn'], 'Should detect NN in unicode text');
        $this->assertContains('152/08', $result['nn'][0]['issues']);
    }

    #[Test]
    public function it_handles_real_world_zpp_citation(): void
    {
        $text = 'Zakon o parničnom postupku (Narodne novine, br. 53/91, 91/92, 112/99, 88/01, 117/03, 88/05, 02/07, 84/08)';

        $result = $this->detector->detect('nn', $text);

        $this->assertNotEmpty($result);
        $issues = $result[0]['issues'];
        $this->assertGreaterThanOrEqual(5, count($issues), 'Should detect multiple NN issues in ZPP citation');
        $this->assertContains('53/91', $issues);
        $this->assertContains('84/08', $issues);
    }

    #[Test]
    public function it_handles_real_world_zoo_citation(): void
    {
        $text = 'Zakon o obveznim odnosima (NN 35/05, 41/08, 125/11, 78/15, 29/18)';

        $result = $this->detector->detect('nn', $text);

        $this->assertNotEmpty($result);
        $issues = $result[0]['issues'];
        $this->assertContains('35/05', $issues);
        $this->assertContains('29/18', $issues);
    }

    #[Test]
    public function it_detects_constitutional_court_case_numbers(): void
    {
        $text = 'U predmetu U-III-1234/2019 Ustavni sud odlučio je.';

        $result = $this->detector->detect('cases', $text);

        $this->assertNotEmpty($result);
        $this->assertEquals('U-III', $result[0]['prefix']);
        $this->assertEquals('1234', $result[0]['number']);
        $this->assertEquals('2019', $result[0]['year']);
    }
}
