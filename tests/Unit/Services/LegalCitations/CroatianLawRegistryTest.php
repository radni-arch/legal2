<?php

namespace Tests\Unit\Services\LegalCitations;

use App\Services\LegalCitations\CroatianLawRegistry;
use Tests\TestCase;

class CroatianLawRegistryTest extends TestCase
{
    /** @test */
    public function it_provides_law_abbreviations_constant()
    {
        $abbreviations = CroatianLawRegistry::LAW_ABBREVIATIONS;

        $this->assertIsArray($abbreviations);
        $this->assertNotEmpty($abbreviations);
        $this->assertContains('ZPP', $abbreviations);
        $this->assertContains('ZKP', $abbreviations);
        $this->assertContains('KZ', $abbreviations);
        $this->assertContains('ZOO', $abbreviations);
    }

    /** @test */
    public function it_provides_law_name_to_abbreviation_mapping()
    {
        $mapping = CroatianLawRegistry::LAW_NAME_TO_ABBREV;

        $this->assertIsArray($mapping);
        $this->assertNotEmpty($mapping);
        $this->assertEquals('ZPP', $mapping['zakon o parničnom postupku']);
        $this->assertEquals('ZKP', $mapping['zakon o kaznenom postupku']);
        $this->assertEquals('KZ', $mapping['kazneni zakon']);
    }

    /** @test */
    public function it_includes_genitive_forms_in_name_mapping()
    {
        $mapping = CroatianLawRegistry::LAW_NAME_TO_ABBREV;

        // Genitive forms (zakona instead of zakon)
        $this->assertEquals('ZPP', $mapping['zakona o parničnom postupku']);
        $this->assertEquals('ZKP', $mapping['zakona o kaznenom postupku']);
        $this->assertEquals('KZ', $mapping['kaznenog zakona']);
    }

    /** @test */
    public function it_generates_valid_abbreviation_regex()
    {
        $regex = CroatianLawRegistry::getAbbreviationRegex();

        $this->assertIsString($regex);
        $this->assertStringStartsWith('(?:', $regex);
        $this->assertStringEndsWith(')', $regex);

        // Test it's valid regex by using it
        $pattern = '/'.$regex.'/';
        $this->assertEquals(1, preg_match($pattern, 'ZPP'));
        $this->assertEquals(1, preg_match($pattern, 'ZKP'));
        $this->assertEquals(1, preg_match($pattern, 'KZ'));
    }

    /** @test */
    public function it_abbreviation_regex_matches_all_law_codes()
    {
        $regex = CroatianLawRegistry::getAbbreviationRegex();
        $pattern = '/\b'.$regex.'\b/';

        foreach (CroatianLawRegistry::LAW_ABBREVIATIONS as $abbrev) {
            $this->assertEquals(
                1,
                preg_match($pattern, $abbrev),
                "Regex should match abbreviation: {$abbrev}"
            );
        }
    }

    /** @test */
    public function it_abbreviation_regex_does_not_match_invalid_codes()
    {
        $regex = CroatianLawRegistry::getAbbreviationRegex();
        $pattern = '/\b'.$regex.'\b/';

        $this->assertEquals(0, preg_match($pattern, 'INVALID'));
        $this->assertEquals(0, preg_match($pattern, 'XYZ'));
        $this->assertEquals(0, preg_match($pattern, ''));
    }

    /** @test */
    public function it_normalizes_null_input_to_null()
    {
        $result = CroatianLawRegistry::normalizeLaw(null);

        $this->assertNull($result);
    }

    /** @test */
    public function it_normalizes_empty_string_to_null()
    {
        $result = CroatianLawRegistry::normalizeLaw('');

        $this->assertNull($result);
    }

    /** @test */
    public function it_normalizes_whitespace_only_to_null()
    {
        $result = CroatianLawRegistry::normalizeLaw('   ');

        $this->assertNull($result);
    }

    /** @test */
    public function it_normalizes_lowercase_abbreviation_to_uppercase()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('zpp'));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw('zkp'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('kz'));
        $this->assertEquals('ZOO', CroatianLawRegistry::normalizeLaw('zoo'));
    }

    /** @test */
    public function it_normalizes_mixed_case_abbreviation_to_uppercase()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('Zpp'));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw('zKp'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('Kz'));
    }

    /** @test */
    public function it_preserves_valid_uppercase_abbreviation()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('ZPP'));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw('ZKP'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('KZ'));
    }

    /** @test */
    public function it_normalizes_long_law_name_to_abbreviation()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('Zakon o parničnom postupku'));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw('Zakon o kaznenom postupku'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('Kazneni zakon'));
        $this->assertEquals('ZOO', CroatianLawRegistry::normalizeLaw('Zakon o obveznim odnosima'));
    }

    /** @test */
    public function it_normalizes_genitive_form_to_abbreviation()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('zakona o parničnom postupku'));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw('zakona o kaznenom postupku'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('kaznenog zakona'));
    }

    /** @test */
    public function it_normalizes_long_name_within_text()
    {
        $text = 'Prema članku 5. zakona o parničnom postupku...';

        $result = CroatianLawRegistry::normalizeLaw($text);

        $this->assertEquals('ZPP', $result);
    }

    /** @test */
    public function it_normalizes_long_name_with_extra_words()
    {
        $text = 'primjena zakona o kaznenom postupku';

        $result = CroatianLawRegistry::normalizeLaw($text);

        $this->assertEquals('ZKP', $result);
    }

    /** @test */
    public function it_handles_croatian_unicode_in_normalization()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('Zakon o parničnom postupku'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('Kazneni zakon'));
    }

    /** @test */
    public function it_trims_whitespace_before_normalization()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('  ZPP  '));
        $this->assertEquals('ZKP', CroatianLawRegistry::normalizeLaw("\t\nZKP\n\t"));
    }

    /** @test */
    public function it_returns_uppercase_fallback_for_unknown_law()
    {
        $result = CroatianLawRegistry::normalizeLaw('Nepoznati zakon');

        $this->assertEquals('NEPOZNATI ZAKON', $result);
    }

    /** @test */
    public function it_returns_uppercase_fallback_for_invalid_abbreviation()
    {
        $result = CroatianLawRegistry::normalizeLaw('XYZ');

        $this->assertEquals('XYZ', $result);
    }

    /** @test */
    public function it_normalizes_all_registered_abbreviations()
    {
        foreach (CroatianLawRegistry::LAW_ABBREVIATIONS as $abbrev) {
            $lowercase = mb_strtolower($abbrev, 'UTF-8');
            $result = CroatianLawRegistry::normalizeLaw($lowercase);

            $this->assertEquals($abbrev, $result, "Should normalize {$lowercase} to {$abbrev}");
        }
    }

    /** @test */
    public function it_normalizes_all_registered_long_names()
    {
        foreach (CroatianLawRegistry::LAW_NAME_TO_ABBREV as $longName => $expectedAbbrev) {
            $result = CroatianLawRegistry::normalizeLaw($longName);

            $this->assertEquals(
                $expectedAbbrev,
                $result,
                "Should normalize '{$longName}' to {$expectedAbbrev}"
            );
        }
    }

    /** @test */
    public function it_generates_valid_long_name_regex()
    {
        $regex = CroatianLawRegistry::getLongNameRegex();

        $this->assertIsString($regex);
        $this->assertNotEmpty($regex);

        // Test it's valid regex by using it
        $pattern = '/'.$regex.'/i';
        $this->assertEquals(1, preg_match($pattern, 'zakon o parničnom postupku'));
        $this->assertEquals(1, preg_match($pattern, 'kazneni zakon'));
    }

    /** @test */
    public function it_long_name_regex_matches_all_registered_names()
    {
        $regex = CroatianLawRegistry::getLongNameRegex();
        $pattern = '/'.$regex.'/iu';

        foreach (array_keys(CroatianLawRegistry::LAW_NAME_TO_ABBREV) as $longName) {
            $this->assertEquals(
                1,
                preg_match($pattern, $longName),
                "Regex should match long name: {$longName}"
            );
        }
    }

    /** @test */
    public function it_long_name_regex_does_not_match_invalid_names()
    {
        $regex = CroatianLawRegistry::getLongNameRegex();
        $pattern = '/'.$regex.'/iu';

        $this->assertEquals(0, preg_match($pattern, 'invalid law name'));
        $this->assertEquals(0, preg_match($pattern, 'nepoznati zakon'));
    }

    /** @test */
    public function it_handles_case_insensitivity_in_normalization()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('ZAKON O PARNIČNOM POSTUPKU'));
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('zakon o parničnom postupku'));
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('Zakon O Parničnom Postupku'));
    }

    /** @test */
    public function it_normalizes_partial_long_name_matches()
    {
        $this->assertEquals('ZPP', CroatianLawRegistry::normalizeLaw('Članak 5. zakona o parničnom postupku'));
        $this->assertEquals('KZ', CroatianLawRegistry::normalizeLaw('Prema kaznenom zakonu članak 10'));
        $this->assertEquals('ZOO', CroatianLawRegistry::normalizeLaw('Primjena zakona o obveznim odnosima'));
    }
}
