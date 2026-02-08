<?php

namespace Tests\Unit\Services\LegalMetadata;

use App\Services\LegalMetadata\CourtDetector;
use Tests\TestCase;

class CourtDetectorTest extends TestCase
{
    protected CourtDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new CourtDetector;
    }

    /** @test */
    public function it_detects_supreme_court_full_name()
    {
        $text = 'Vrhovni sud Republike Hrvatske donio je odluku...';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $result[0]['raw']);
        $this->assertEquals('vrhovni sud rh', $result[0]['normalized']);
        $this->assertEquals('supreme', $result[0]['type']);
    }

    /** @test */
    public function it_detects_supreme_court_abbreviation()
    {
        $text = 'Odluka VSRH broj 123/2024';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('VSRH', $result[0]['raw']);
        $this->assertEquals('vrhovni sud rh', $result[0]['normalized']);
        $this->assertEquals('supreme', $result[0]['type']);
    }

    /** @test */
    public function it_detects_supreme_court_variations()
    {
        $variations = [
            'Vrhovni sud RH',
            'Vrhovni sud',
        ];

        foreach ($variations as $variation) {
            $result = $this->detector->detect($variation);

            $this->assertCount(1, $result, "Should detect: {$variation}");
            $this->assertEquals('supreme', $result[0]['type']);
        }
    }

    /** @test */
    public function it_detects_constitutional_court()
    {
        $text = 'Ustavni sud Republike Hrvatske odlučio je...';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Ustavni sud Republike Hrvatske', $result[0]['raw']);
        $this->assertEquals('constitutional', $result[0]['type']);
    }

    /** @test */
    public function it_detects_constitutional_court_abbreviation()
    {
        $text = 'Odluka USRH';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('USRH', $result[0]['raw']);
        $this->assertEquals('constitutional', $result[0]['type']);
    }

    /** @test */
    public function it_detects_high_commercial_court()
    {
        $text = 'Visoki trgovački sud Republike Hrvatske';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Visoki trgovački sud Republike Hrvatske', $result[0]['raw']);
        $this->assertEquals('high', $result[0]['type']);
    }

    /** @test */
    public function it_detects_high_administrative_court()
    {
        $text = 'Visoki upravni sud Republike Hrvatske';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('high', $result[0]['type']);
    }

    /** @test */
    public function it_detects_county_court_with_city()
    {
        $text = 'Županijski sud u Zagrebu presudio je...';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Županijski sud u Zagrebu', $result[0]['raw']);
        $this->assertEquals('županijski sud u zagrebu', $result[0]['normalized']);
        $this->assertEquals('county', $result[0]['type']);
        $this->assertEquals('Zagreb', $result[0]['city']);
    }

    /** @test */
    public function it_detects_county_courts_in_various_cities()
    {
        $cities = ['Split', 'Rijeka', 'Osijek', 'Varaždin', 'Zadar'];

        foreach ($cities as $city) {
            $text = "Županijski sud u {$city}u";
            $result = $this->detector->detect($text);

            $this->assertGreaterThanOrEqual(1, count($result), "Should detect county court in {$city}");
            $found = false;
            foreach ($result as $court) {
                if (isset($court['city']) && $court['city'] === $city) {
                    $found = true;
                    $this->assertEquals('county', $court['type']);
                    break;
                }
            }
            $this->assertTrue($found, "Should find county court for city {$city}");
        }
    }

    /** @test */
    public function it_detects_municipal_court_with_city()
    {
        $text = 'Općinski sud u Zagrebu';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Općinski sud u Zagrebu', $result[0]['raw']);
        $this->assertEquals('municipal', $result[0]['type']);
        $this->assertEquals('Zagreb', $result[0]['city']);
    }

    /** @test */
    public function it_detects_commercial_court_with_city()
    {
        $text = 'Trgovački sud u Zagrebu';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('commercial', $result[0]['type']);
        $this->assertEquals('Zagreb', $result[0]['city']);
    }

    /** @test */
    public function it_detects_misdemeanor_court_with_city()
    {
        $text = 'Prekršajni sud u Zagrebu';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('misdemeanor', $result[0]['type']);
        $this->assertEquals('Zagreb', $result[0]['city']);
    }

    /** @test */
    public function it_detects_multiple_courts_in_text()
    {
        $text = 'Vrhovni sud Republike Hrvatske i Županijski sud u Zagrebu odlučili su...';

        $result = $this->detector->detect($text);

        $this->assertCount(2, $result);
        $this->assertEquals('supreme', $result[0]['type']);
        $this->assertEquals('county', $result[1]['type']);
    }

    /** @test */
    public function it_deduplicates_same_court_mentioned_multiple_times()
    {
        $text = 'Vrhovni sud Republike Hrvatske je odlučio. Vrhovni sud Republike Hrvatske je presudio.';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result, 'Should deduplicate identical court mentions');
    }

    /** @test */
    public function it_deduplicates_court_full_name_and_abbreviation()
    {
        $text = 'Vrhovni sud Republike Hrvatske (VSRH) je odlučio...';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result, 'Should deduplicate full name and abbreviation');
    }

    /** @test */
    public function it_detects_all_high_courts()
    {
        $courts = [
            'Visoki trgovački sud Republike Hrvatske' => 'high',
            'Visoki prekršajni sud Republike Hrvatske' => 'high',
            'Visoki upravni sud Republike Hrvatske' => 'high',
        ];

        foreach ($courts as $courtName => $expectedType) {
            $result = $this->detector->detect($courtName);

            $this->assertGreaterThanOrEqual(1, count($result), "Should detect: {$courtName}");
            $this->assertEquals($expectedType, $result[0]['type']);
        }
    }

    /** @test */
    public function it_normalizes_court_names_consistently()
    {
        $text1 = 'Vrhovni sud Republike Hrvatske';
        $text2 = 'VRHOVNI SUD REPUBLIKE HRVATSKE';
        $text3 = 'vrhovni sud republike hrvatske';

        $result1 = $this->detector->detect($text1);
        $result2 = $this->detector->detect($text2);
        $result3 = $this->detector->detect($text3);

        $this->assertEquals($result1[0]['normalized'], $result2[0]['normalized']);
        $this->assertEquals($result1[0]['normalized'], $result3[0]['normalized']);
    }

    /** @test */
    public function it_normalizes_republike_hrvatske_to_rh()
    {
        $text = 'Vrhovni sud Republike Hrvatske';

        $result = $this->detector->detect($text);

        $this->assertStringContainsString('rh', $result[0]['normalized']);
        $this->assertStringNotContainsString('republike hrvatske', $result[0]['normalized']);
    }

    /** @test */
    public function it_classifies_court_types_correctly()
    {
        $classifications = [
            'Vrhovni sud' => 'supreme',
            'Ustavni sud' => 'constitutional',
            'Visoki trgovački sud' => 'high',
            'Županijski sud u Zagrebu' => 'county',
            'Općinski sud u Splitu' => 'municipal',
            'Trgovački sud u Rijeci' => 'commercial',
            'Prekršajni sud u Osijeku' => 'misdemeanor',
        ];

        foreach ($classifications as $courtName => $expectedType) {
            $result = $this->detector->detect($courtName);

            $this->assertGreaterThanOrEqual(
                1,
                count($result),
                "Should detect court: {$courtName}"
            );

            $this->assertEquals(
                $expectedType,
                $result[0]['type'],
                "Court '{$courtName}' should be classified as '{$expectedType}'"
            );
        }
    }

    /** @test */
    public function it_returns_empty_array_for_text_without_courts()
    {
        $text = 'This text contains no Croatian court names at all.';

        $result = $this->detector->detect($text);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_empty_text()
    {
        $result = $this->detector->detect('');

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_croatian_unicode_characters()
    {
        $text = 'Županijski sud u Šibeniku i Općinski sud u Čakovcu';

        $result = $this->detector->detect($text);

        $this->assertCount(2, $result);
        $this->assertEquals('county', $result[0]['type']);
        $this->assertEquals('Šibenik', $result[0]['city']);
        $this->assertEquals('municipal', $result[1]['type']);
        $this->assertEquals('Čakovec', $result[1]['city']);
    }

    /** @test */
    public function it_detects_court_with_location_variations()
    {
        // Croatian uses locative case with 'u' (in)
        $texts = [
            'Županijski sud u Zagrebu',  // u + locative
            'Županijski sud Zagrebu',     // without 'u'
        ];

        foreach ($texts as $text) {
            $result = $this->detector->detect($text);

            $this->assertGreaterThanOrEqual(
                1,
                count($result),
                "Should detect court in text: {$text}"
            );
        }
    }

    /** @test */
    public function it_handles_court_names_in_case_sensitive_manner_for_detection()
    {
        $lowercaseText = 'vrhovni sud republike hrvatske';
        $uppercaseText = 'VRHOVNI SUD REPUBLIKE HRVATSKE';
        $mixedCaseText = 'Vrhovni Sud Republike Hrvatske';

        $result1 = $this->detector->detect($lowercaseText);
        $result2 = $this->detector->detect($uppercaseText);
        $result3 = $this->detector->detect($mixedCaseText);

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
        $this->assertCount(1, $result3);
    }

    /** @test */
    public function it_detects_courts_in_realistic_croatian_legal_text()
    {
        $text = 'Vrhovni sud Republike Hrvatske presudom Rev-123/2024 preinačio je presudu '.
                'Županijskog suda u Zagrebu Gž-456/2023 koja je potvrdila prvostupanjsku '.
                'presudu Općinskog suda u Zagrebu.';

        $result = $this->detector->detect($text);

        $this->assertGreaterThanOrEqual(2, count($result));

        $courtTypes = array_column($result, 'type');
        $this->assertContains('supreme', $courtTypes);
        $this->assertContains('county', $courtTypes);
    }

    /** @test */
    public function it_includes_all_major_croatian_cities_in_detection()
    {
        $majorCities = ['Zagreb', 'Split', 'Rijeka', 'Osijek'];

        foreach ($majorCities as $city) {
            $text = "Županijski sud u {$city}u";
            $result = $this->detector->detect($text);

            $this->assertGreaterThanOrEqual(
                1,
                count($result),
                "Should detect court in major city: {$city}"
            );
        }
    }

    /** @test */
    public function it_handles_word_boundaries_correctly_for_abbreviations()
    {
        $text = 'VSRH je odlučio, VTSRH je potvrdio, USRH je proglasio';

        $result = $this->detector->detect($text);

        $this->assertGreaterThanOrEqual(3, count($result));
    }

    /** @test */
    public function it_detects_high_misdemeanor_court()
    {
        $text = 'Visoki prekršajni sud Republike Hrvatske';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('high', $result[0]['type']);
    }
}
