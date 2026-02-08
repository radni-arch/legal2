<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\ArticleExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleExtractorTest extends TestCase
{
    protected ArticleExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ArticleExtractor();
    }

    #[Test]
    public function it_extracts_clanak_references(): void
    {
        $text = 'Sukladno članku 110. Zakona o parničnom postupku, sud je dužan...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertEquals('110', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_cl_abbreviation(): void
    {
        $text = 'Prema čl. 45. st. 2. ZPP-a, tužitelj mora dokazati...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertEquals('45', $refs[0]['article_number']);
        $this->assertEquals('2', $refs[0]['paragraph']);
    }

    #[Test]
    public function it_extracts_article_with_letter_suffix(): void
    {
        $text = 'Članak 15a. predviđa posebne uvjete za...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertEquals('15a', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_multiple_articles(): void
    {
        $text = 'Temeljem članka 110. i članka 220. ZPP-a, te čl. 45. ZOO-a...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertCount(3, $refs);
        $articleNumbers = array_column($refs, 'article_number');
        $this->assertContains('110', $articleNumbers);
        $this->assertContains('220', $articleNumbers);
        $this->assertContains('45', $articleNumbers);
    }

    #[Test]
    public function it_extracts_article_with_paragraph(): void
    {
        $text = 'Članak 15. stavak 3. Zakona određuje...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertEquals('15', $refs[0]['article_number']);
        $this->assertEquals('3', $refs[0]['paragraph']);
    }

    #[Test]
    public function it_returns_empty_for_empty_text(): void
    {
        $this->assertEmpty($this->extractor->extractReferences(''));
        $this->assertEmpty($this->extractor->extractReferences('   '));
    }

    #[Test]
    public function it_extracts_context_around_reference(): void
    {
        $text = 'Sukladno članku 110. Zakona o parničnom postupku';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertArrayHasKey('context', $refs[0]);
        $this->assertNotEmpty($refs[0]['context']);
    }

    #[Test]
    public function it_generates_deterministic_ids(): void
    {
        $text = 'Članak 110. ZPP-a';

        $refs1 = $this->extractor->extractReferences($text, 'law_123');
        $refs2 = $this->extractor->extractReferences($text, 'law_123');

        $this->assertEquals($refs1[0]['id'], $refs2[0]['id']);
    }

    #[Test]
    public function it_extracts_law_structure(): void
    {
        $lawText = "
Članak 1.
Ovaj zakon uređuje opće uvjete.

Članak 2.
(1) Stavak prvi članka 2.
(2) Stavak drugi članka 2.

Članak 3.
Završne odredbe.
";

        $articles = $this->extractor->extractStructure($lawText, 'law_test');

        $this->assertCount(3, $articles);
        $this->assertEquals('1', $articles[0]['article_number']);
        $this->assertEquals('2', $articles[1]['article_number']);
        $this->assertEquals('3', $articles[2]['article_number']);
        $this->assertEquals(2, $articles[1]['paragraph_count']); // Article 2 has 2 paragraphs
    }

    #[Test]
    public function it_sorts_articles_correctly(): void
    {
        $text = 'čl. 2., čl. 10., čl. 1., čl. 1a.';

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertEquals(['1', '1a', '2', '10'], $numbers);
    }

    #[Test]
    public function it_handles_english_article_format(): void
    {
        $text = 'According to Article 5 of the Convention...';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertEquals('5', $refs[0]['article_number']);
    }

    // ============================================================
    // COMPREHENSIVE CROATIAN GRAMMATICAL CASE TESTS
    // Regression tests for Croatian legal text edge cases
    // ============================================================

    #[Test]
    public function it_extracts_article_in_nominative_case_clanak(): void
    {
        // Nominative: "Članak 123." - subject form
        $text = 'Članak 123. propisuje da svaki zaposlenik ima pravo na godišnji odmor.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract article in nominative case (Članak)');
        $this->assertEquals('123', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_article_in_locative_case_clanku(): void
    {
        // Locative: "članku 45." - location/reference form (na članku, u članku)
        $text = 'U članku 45. Zakona o radu navedeni su uvjeti za raskid ugovora.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract article in locative case (članku)');
        $this->assertEquals('45', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_article_in_instrumental_case_clankom(): void
    {
        // Instrumental: "člankom 78." - means/instrument form (sukladno člankom)
        $text = 'Sukladno člankom 78. Zakona o obveznim odnosima, dužnik odgovara za štetu.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract article in instrumental case (člankom)');
        $this->assertEquals('78', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_article_in_genitive_case_clanka(): void
    {
        // Genitive: "članka 90." - possession/of form (na temelju članka)
        $text = 'Na temelju članka 90. Zakona, sud je utvrdio činjenično stanje.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract article in genitive case (članka)');
        $this->assertEquals('90', $refs[0]['article_number']);
    }

    #[Test]
    public function it_handles_lowercase_clanak_variations(): void
    {
        // Test lowercase versions of all cases
        $text = 'Prema članku 10. i temeljem članka 20. te sukladno člankom 30. zakona.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertCount(3, $refs, 'Should extract all three lowercase grammatical case variations');
        $numbers = array_column($refs, 'article_number');
        $this->assertContains('10', $numbers);
        $this->assertContains('20', $numbers);
        $this->assertContains('30', $numbers);
    }

    #[Test]
    public function it_extracts_abbreviated_cl_with_period(): void
    {
        // Standard abbreviation with period
        $text = 'Prema čl. 12. Zakona o parničnom postupku (ZPP).';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract abbreviated čl. format');
        $this->assertEquals('12', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_abbreviated_cl_without_space(): void
    {
        // Abbreviation without space after period
        $text = 'Temeljem čl.55. ZOO-a, ugovor je ništavan.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract čl. format without space');
        $this->assertEquals('55', $refs[0]['article_number']);
    }

    #[Test]
    public function it_extracts_multiple_articles_from_same_sentence(): void
    {
        // Real-world pattern: multiple articles with standard singular forms
        $text = 'Prema članku 110. i članku 112. Zakona, a u vezi s člankom 220. istog zakona.';

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertContains('110', $numbers, 'Should find article 110');
        $this->assertContains('112', $numbers, 'Should find article 112');
        $this->assertContains('220', $numbers, 'Should find article 220');
    }

    #[Test]
    public function it_handles_article_with_stavak_paragraph(): void
    {
        // Article with paragraph reference
        $text = 'Članak 15. stavak 2. točka 3. Zakona određuje proceduru.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract article with paragraph');
        $this->assertEquals('15', $refs[0]['article_number']);
        $this->assertEquals('2', $refs[0]['paragraph']);
    }

    #[Test]
    public function it_handles_abbreviated_st_paragraph(): void
    {
        // Abbreviated paragraph: čl. X. st. Y.
        $text = 'Prema čl. 291. st. 1. ZKP-a, svjedok je dužan govoriti istinu.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract abbreviated article with paragraph');
        $this->assertEquals('291', $refs[0]['article_number']);
        $this->assertEquals('1', $refs[0]['paragraph']);
    }

    #[Test]
    public function it_handles_article_numbers_with_letter_suffixes(): void
    {
        // Croatian laws often have articles like 15a, 15b
        $texts = [
            'Članak 15a. propisuje dodatne uvjete.' => '15a',
            'Temeljem članka 102b. zakona, postupak se obustavlja.' => '102b',
            'Čl. 7c. određuje izuzetke.' => '7c',
        ];

        foreach ($texts as $text => $expected) {
            $refs = $this->extractor->extractReferences($text);
            $this->assertNotEmpty($refs, "Should extract article with suffix from: $text");
            $this->assertEquals($expected, $refs[0]['article_number'], "Expected article number $expected");
        }
    }

    #[Test]
    public function it_handles_high_article_numbers(): void
    {
        // Some Croatian laws have articles in the hundreds
        $text = 'Članak 456. Kaznenog zakona propisuje kaznu od 1 do 5 godina zatvora.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, 'Should extract three-digit article number');
        $this->assertEquals('456', $refs[0]['article_number']);
    }

    #[Test]
    public function it_avoids_duplicate_articles_in_same_text(): void
    {
        // Same article mentioned multiple times should appear once
        $text = 'Članak 50. propisuje. Na temelju članka 50. sud odlučuje. Sukladno članku 50. zakona.';

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertCount(1, array_keys($numbers, '50'), 'Article 50 should appear only once');
    }

    #[Test]
    public function it_extracts_articles_with_different_paragraphs_separately(): void
    {
        // Same article with different paragraphs should be separate entries
        $text = 'Članak 10. stavak 1. i članak 10. stavak 2. zakona.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertGreaterThanOrEqual(2, count($refs), 'Should extract both paragraph references');
    }

    #[Test]
    public function it_handles_unicode_characters_correctly(): void
    {
        // Ensure Croatian diacritics work properly
        $texts = [
            'Članak 1. Zakona' => '1',
            'članak 2. zakona' => '2',
            'ČLANAK 3. ZAKONA' => '3',
        ];

        foreach ($texts as $text => $expected) {
            $refs = $this->extractor->extractReferences($text);
            $this->assertNotEmpty($refs, "Should handle unicode in: $text");
            $this->assertEquals($expected, $refs[0]['article_number']);
        }
    }

    #[Test]
    public function it_extracts_context_with_sufficient_surrounding_text(): void
    {
        // Verify context extraction includes meaningful surrounding text
        $text = 'Vrlo bitna napomena jest da članak 100. Zakona o obveznim odnosima predviđa posebne odredbe za ovakve situacije.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs);
        $this->assertArrayHasKey('context', $refs[0]);
        $this->assertStringContainsString('članak 100', $refs[0]['context']);
    }

    #[Test]
    public function it_handles_real_world_croatian_legal_text_sample(): void
    {
        // Real-world complex legal text using standard singular grammatical forms
        $text = <<<TEXT
Sukladno članku 10. stavku 2. Zakona o parničnom postupku (Narodne novine, br. 53/91, 91/92, 112/99, 129/00, 88/01, 117/03, 88/05, 02/07, 84/08, 96/08, 123/08, 57/11, 25/13, 28/13 - v. čl. 289. ZKP-a), sud je ovlašten odrediti privremenu mjeru.

Tužitelj se poziva na članak 1045. Zakona o obveznim odnosima koji propisuje opća pravila o odgovornosti za štetu, te na članak 1100. i članak 1101. istog Zakona koji reguliraju neimovninsku štetu.

Temeljem članka 89. stavka 1. u vezi s člankom 90. stavkom 3. ZPP-a, presuda se objavljuje javno.
TEXT;

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertContains('10', $numbers, 'Should find čl. 10');
        $this->assertContains('289', $numbers, 'Should find čl. 289');
        $this->assertContains('1045', $numbers, 'Should find čl. 1045');
        $this->assertContains('1100', $numbers, 'Should find čl. 1100');
        $this->assertContains('1101', $numbers, 'Should find čl. 1101');
        $this->assertContains('89', $numbers, 'Should find čl. 89');
        $this->assertContains('90', $numbers, 'Should find čl. 90');
    }

    #[Test]
    public function it_handles_complex_nested_article_references(): void
    {
        // Complex citation with nested references
        $text = 'Članak 5. st. 1. t. 3. u svezi s čl. 10. st. 4. Zakona.';

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertContains('5', $numbers, 'Should find article 5');
        $this->assertContains('10', $numbers, 'Should find article 10');
    }

    #[Test]
    public function it_matches_all_four_grammatical_cases_in_single_text(): void
    {
        // All grammatical cases in one comprehensive test
        $text = <<<TEXT
Članak 1. definira predmet zakona.
Na temelju članka 2. utvrđuje se nadležnost.
U članku 3. propisani su uvjeti.
Sukladno člankom 4. sud je nadležan.
TEXT;

        $refs = $this->extractor->extractReferences($text);

        $numbers = array_column($refs, 'article_number');
        $this->assertCount(4, $refs, 'Should find all 4 articles in different cases');
        $this->assertEquals(['1', '2', '3', '4'], $numbers, 'Should match nominative, genitive, locative, and instrumental');
    }

    // ============================================================
    // @dataProvider TESTS - CROATIAN ARTICLE FORMS
    // Spec requirement: Use @dataProvider with Croatian article forms
    // ============================================================

    #[Test]
    #[DataProvider('croatianArticleFormsProvider')]
    public function it_extracts_article_from_croatian_form(string $text, string $expectedArticleNumber, ?string $expectedParagraph, string $description): void
    {
        $refs = $this->extractor->extractReferences($text);

        $this->assertNotEmpty($refs, "Should extract article from: $description ($text)");
        $this->assertEquals($expectedArticleNumber, $refs[0]['article_number'], "Article number mismatch for: $description");

        if ($expectedParagraph !== null) {
            $this->assertEquals($expectedParagraph, $refs[0]['paragraph'], "Paragraph mismatch for: $description");
        }
    }

    public static function croatianArticleFormsProvider(): array
    {
        return [
            // Nominative case (tko/sto) - subject form
            'Nominative: Clanak 1.' => [
                'Članak 1. propisuje uvjete.',
                '1', null, 'Nominative case',
            ],
            'Nominative lowercase: clanak 5.' => [
                'članak 5. propisuje uvjete.',
                '5', null, 'Nominative lowercase',
            ],

            // Genitive case (koga/cega) - "of" form
            'Genitive: clanka 2.' => [
                'Na temelju članka 2. zakona.',
                '2', null, 'Genitive case',
            ],

            // Locative case (o komu/cemu) - location form
            'Locative: u clanku 3.' => [
                'U članku 3. navedeni su uvjeti.',
                '3', null, 'Locative case',
            ],

            // Instrumental case (kim/cim) - means form
            'Instrumental: clankom 4.' => [
                'Sukladno člankom 4. dužnik odgovara.',
                '4', null, 'Instrumental case',
            ],

            // Abbreviated forms
            'Abbreviated: cl. 10.' => [
                'Prema čl. 10. Zakona.',
                '10', null, 'Abbreviated cl.',
            ],
            'Abbreviated with paragraph: cl. 10. st. 2.' => [
                'Prema čl. 10. st. 2. Zakona.',
                '10', '2', 'Abbreviated cl. with paragraph',
            ],
            'Abbreviated no space: cl.55.' => [
                'Temeljem čl.55. ZOO-a.',
                '55', null, 'Abbreviated no space',
            ],

            // Full form with paragraph (stavak)
            'Clanak with stavak' => [
                'Članak 15. stavak 3. Zakona.',
                '15', '3', 'Full form with stavak',
            ],

            // Alpha suffix forms
            'Alpha suffix a: Clanak 1a' => [
                'Članak 1a predviđa posebne uvjete.',
                '1a', null, 'Alpha suffix a',
            ],
            'Alpha suffix b: clanka 102b' => [
                'Temeljem članka 102b. zakona.',
                '102b', null, 'Alpha suffix b',
            ],
            'Alpha suffix abbreviated: cl. 7c' => [
                'Čl. 7c. određuje izuzetke.',
                '7c', null, 'Alpha suffix abbreviated',
            ],

            // English form
            'English: Article 5' => [
                'According to Article 5 of the Convention.',
                '5', null, 'English Article form',
            ],

            // High article numbers
            'High number: Clanak 456' => [
                'Članak 456. Kaznenog zakona.',
                '456', null, 'High article number',
            ],
            'Four digit: Clanak 1045' => [
                'Temeljem članka 1045. zakona.',
                '1045', null, 'Four digit article number',
            ],
        ];
    }

    // ============================================================
    // @dataProvider TESTS - STRUCTURE EXTRACTION EDGE CASES
    // ============================================================

    #[Test]
    public function extract_structure_returns_empty_for_empty_text(): void
    {
        $this->assertEmpty($this->extractor->extractStructure('', 'law_test'));
        $this->assertEmpty($this->extractor->extractStructure('   ', 'law_test'));
    }

    #[Test]
    public function extract_structure_captures_parenthetical_titles(): void
    {
        $lawText = "
Članak 1.
(Predmet zakona)
Ovaj zakon uređuje opće uvjete.

Članak 2.
(Primjena zakona)
(1) Stavak prvi članka 2.
(2) Stavak drugi članka 2.
";

        $articles = $this->extractor->extractStructure($lawText, 'law_test');

        $this->assertCount(2, $articles);
        $this->assertEquals('Predmet zakona', $articles[0]['title']);
        $this->assertEquals('Primjena zakona', $articles[1]['title']);
    }

    #[Test]
    public function extract_structure_includes_law_id_and_position(): void
    {
        $lawText = "
Članak 1.
Ovaj zakon uređuje opće uvjete.
";

        $articles = $this->extractor->extractStructure($lawText, 'my_law_id');

        $this->assertCount(1, $articles);
        $this->assertEquals('my_law_id', $articles[0]['law_id']);
        $this->assertEquals(1, $articles[0]['position']);
        $this->assertNotEmpty($articles[0]['id']);
    }

    #[Test]
    public function generate_article_id_with_null_law_id(): void
    {
        $id = $this->extractor->generateArticleId(null, '10');

        $this->assertStringStartsWith('article_', $id);
        // Should produce deterministic result
        $id2 = $this->extractor->generateArticleId(null, '10');
        $this->assertEquals($id, $id2);
    }

    #[Test]
    public function it_handles_text_without_any_article_references(): void
    {
        $text = 'Ovaj tekst ne sadrži nikakve reference na članke zakona.';

        $refs = $this->extractor->extractReferences($text);

        $this->assertEmpty($refs, 'Should return empty for text without article references');
    }

    #[Test]
    public function it_handles_article_range_gracefully(): void
    {
        // The ArticleExtractor does not have a range pattern (Članci 1-5)
        // But it should not crash on this input
        $text = 'Članci 1-5 zakona propisuju opće uvjete.';

        // This should either extract something or return empty without error
        $refs = $this->extractor->extractReferences($text);
        $this->assertIsArray($refs, 'Should handle range text gracefully');
    }
}
