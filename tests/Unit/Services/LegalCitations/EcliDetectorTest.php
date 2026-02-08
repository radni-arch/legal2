<?php

namespace Tests\Unit\Services\LegalCitations;

use App\Services\LegalCitations\EcliDetector;
use Tests\TestCase;

class EcliDetectorTest extends TestCase
{
    protected EcliDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new EcliDetector;
    }

    /** @test */
    public function it_detects_valid_ecli_identifiers()
    {
        $text = 'See ECLI:HR:VSRH:2024:123 for more details';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result[0]['raw']);
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result[0]['canonical']);
    }

    /** @test */
    public function it_detects_multiple_ecli_identifiers()
    {
        $text = 'Cases ECLI:HR:VSRH:2024:123 and ECLI:HR:VTSRH:2023:456 are relevant';

        $result = $this->detector->detect($text);

        $this->assertCount(2, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result[0]['canonical']);
        $this->assertEquals('ECLI:HR:VTSRH:2023:456', $result[1]['canonical']);
    }

    /** @test */
    public function it_detects_ecli_with_croatian_unicode_court_codes()
    {
        $text = 'Decision ECLI:HR:ŽSST:2024:789 from County Court';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:ŽSST:2024:789', $result[0]['canonical']);
    }

    /** @test */
    public function it_detects_ecli_with_various_court_codes()
    {
        $courts = [
            'ECLI:HR:VSRH:2024:123',    // Vrhovni sud
            'ECLI:HR:USRH:2023:456',    // Ustavni sud
            'ECLI:HR:VTSRH:2024:789',   // Visoki trgovački sud
            'ECLI:HR:VUSRH:2023:100',   // Visoki upravni sud
            'ECLI:HR:ŽSZG:2024:200',    // Županijski sud Zagreb
        ];

        foreach ($courts as $ecli) {
            $result = $this->detector->detect($ecli);
            $this->assertCount(1, $result);
            $this->assertEquals($ecli, $result[0]['canonical']);
        }
    }

    /** @test */
    public function it_handles_ecli_with_alphanumeric_id()
    {
        $text = 'ECLI:HR:VSRH:2024:A1B2C3';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:A1B2C3', $result[0]['canonical']);
    }

    /** @test */
    public function it_handles_ecli_with_dots_in_id()
    {
        $text = 'ECLI:HR:VSRH:2024:12.34.56';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:12.34.56', $result[0]['canonical']);
    }

    /** @test */
    public function it_does_not_detect_invalid_ecli_format()
    {
        $invalidCases = [
            'ECLI:HR:VS:2024:123',        // Court code too short
            'ECLI:HR:12345:2024:123',     // Court code not letters
            'ECLI:HR:VSRH:24:123',        // Year too short (3 digits)
            'ECLI:HR:VSRH:2024:',         // Missing ID
            'ECLI:DE:VSRH:2024:123',      // Wrong country code
        ];

        foreach ($invalidCases as $text) {
            $result = $this->detector->detect($text);
            $this->assertEmpty($result, "Should not detect: {$text}");
        }
    }

    /** @test */
    public function it_requires_word_boundaries()
    {
        // Should not match ECLI in middle of other text
        $text = 'xxxECLI:HR:VSRH:2024:123xxx';

        $result = $this->detector->detect($text);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_detects_ecli_in_croatian_legal_text()
    {
        $text = 'Vrhovni sud Republike Hrvatske donio je odluku ECLI:HR:VSRH:2024:1234 dana 15.01.2024. godine.';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:1234', $result[0]['canonical']);
    }

    /** @test */
    public function it_returns_empty_array_for_text_without_ecli()
    {
        $text = 'This text contains no ECLI identifiers at all';

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
    public function it_detects_ecli_at_text_boundaries()
    {
        $atStart = 'ECLI:HR:VSRH:2024:123 is the case';
        $atEnd = 'See case ECLI:HR:VSRH:2024:456';
        $alone = 'ECLI:HR:VSRH:2024:789';

        $this->assertCount(1, $this->detector->detect($atStart));
        $this->assertCount(1, $this->detector->detect($atEnd));
        $this->assertCount(1, $this->detector->detect($alone));
    }

    /** @test */
    public function it_handles_ecli_with_maximum_length_id()
    {
        $text = 'ECLI:HR:VSRH:2024:VERYLONGIDENTIFIER123456789';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:VERYLONGIDENTIFIER123456789', $result[0]['canonical']);
    }

    /** @test */
    public function it_handles_ecli_with_short_id()
    {
        $text = 'ECLI:HR:VSRH:2024:1AB';

        $result = $this->detector->detect($text);

        $this->assertCount(1, $result);
        $this->assertEquals('ECLI:HR:VSRH:2024:1AB', $result[0]['canonical']);
    }
}
