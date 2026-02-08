<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Models\LegalProvision;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use App\Services\LegalArtillery\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ArgumentValidator::validateCitations() (Sprint 2D).
 *
 * Verifies:
 * - validateCitations() uses HrLegalCitationsDetector to find all citations
 * - Checks detected citations against legal provisions in the database
 * - Returns structured result with found/verified/unverified citations
 * - Handles empty content and content without citations
 */
class ArgumentValidatorCitationValidationTest extends TestCase
{
    use RefreshDatabase;

    private ArgumentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $llmMock = Mockery::mock(LlmClient::class);
        $this->validator = new ArgumentValidator($llmMock, new DevastatingArgumentBuilder());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // validateCitations() basic structure tests
    // =========================================================================

    public function test_validate_citations_returns_array(): void
    {
        $result = $this->validator->validateCitations('Test content');

        $this->assertIsArray($result);
    }

    public function test_validate_citations_returns_expected_keys(): void
    {
        $result = $this->validator->validateCitations('Test content');

        $this->assertArrayHasKey('detected_citations', $result);
        $this->assertArrayHasKey('verified', $result);
        $this->assertArrayHasKey('unverified', $result);
        $this->assertArrayHasKey('coverage_score', $result);
    }

    public function test_validate_citations_handles_empty_content(): void
    {
        $result = $this->validator->validateCitations('');

        $this->assertEmpty($result['detected_citations']);
        $this->assertEmpty($result['verified']);
        $this->assertEmpty($result['unverified']);
    }

    // =========================================================================
    // Citation detection and verification tests
    // =========================================================================

    public function test_validate_citations_detects_statutes_in_content(): void
    {
        // Use ZKP which is in the CroatianLawRegistry abbreviation list
        $content = 'Sukladno ZKP cl.10 st.2, dokazi su nezakoniti.';

        $result = $this->validator->validateCitations($content);

        $this->assertNotEmpty($result['detected_citations'], 'Expected at least one citation detected');
    }

    public function test_validate_citations_marks_matching_provisions_as_verified(): void
    {
        // Create a provision in the database that matches what we'll detect
        LegalProvision::create([
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
            'article' => '10',
            'paragraph' => '2',
            'point' => null,
            'title' => 'Nezakoniti dokazi',
            'full_text' => 'Nezakonito pribavljeni dokazi ne mogu se koristiti',
            'interpretation' => null,
            'tags' => ['all_profiles'],
        ]);

        $content = 'Sukladno ZKP cl.10 st.2, dokazi su nezakoniti.';

        $result = $this->validator->validateCitations($content);

        $this->assertNotEmpty($result['verified'], 'Expected verified citations when DB has matching provision');
    }

    public function test_validate_citations_marks_unmatched_as_unverified(): void
    {
        // DB is empty - no provisions exist
        $content = 'Prema ZKP cl.999 st.1, izmisljeni clanak ne postoji.';

        $result = $this->validator->validateCitations($content);

        // Detection finds a statute, it should be unverified since no DB entry
        $this->assertNotEmpty($result['detected_citations'], 'Expected at least one citation detected');
        $this->assertNotEmpty($result['unverified'], 'Expected unverified citation when DB has no matching provision');
    }

    public function test_validate_citations_coverage_score_is_percentage(): void
    {
        LegalProvision::create([
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
            'article' => '10',
            'paragraph' => '2',
            'point' => null,
            'title' => 'Nezakoniti dokazi',
            'full_text' => 'Nezakonito pribavljeni dokazi',
            'interpretation' => null,
            'tags' => ['all_profiles'],
        ]);

        $content = 'Sukladno ZKP cl.10 st.2, dokazi su nezakoniti.';

        $result = $this->validator->validateCitations($content);

        $this->assertGreaterThanOrEqual(0, $result['coverage_score']);
        $this->assertLessThanOrEqual(100, $result['coverage_score']);
    }

    public function test_validate_citations_with_multiple_statutes(): void
    {
        LegalProvision::create([
            'law_name' => 'Zakon o kaznenom postupku',
            'law_short' => 'ZKP',
            'article' => '10',
            'paragraph' => '2',
            'point' => null,
            'title' => 'Nezakoniti dokazi',
            'full_text' => 'Nezakonito pribavljeni dokazi',
            'interpretation' => null,
            'tags' => ['all_profiles'],
        ]);

        $content = 'Temeljem ZKP cl.10 st.2 i ZKP cl.999 st.3, podnositelj zahtijeva.';

        $result = $this->validator->validateCitations($content);

        // Should have both verified and unverified
        $totalDetected = count($result['detected_citations']);
        $this->assertGreaterThanOrEqual(1, $totalDetected, 'Should detect at least one citation');
    }

    public function test_validate_citations_content_without_legal_references(): void
    {
        $content = 'Ovo je obican tekst bez ikakvih pravnih referenci.';

        $result = $this->validator->validateCitations($content);

        $this->assertEmpty($result['detected_citations']);
        $this->assertEmpty($result['verified']);
        $this->assertEmpty($result['unverified']);
        $this->assertEquals(100, $result['coverage_score'], 'No citations means 100% coverage (nothing to verify)');
    }
}
