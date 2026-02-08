<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\LegalArtillery\ValidationResult;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Tests\TestCase;

class ArgumentValidatorCompletenessTest extends TestCase
{
    private ArgumentValidator $validator;
    private LlmClient $llmMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->llmMock = Mockery::mock(LlmClient::class);
        $this->llmMock->shouldReceive('generate')->andReturn('{}')->byDefault();
        $this->validator = new ArgumentValidator($this->llmMock, new DevastatingArgumentBuilder());
    }

    // =========================================================================
    // Tests for validateCompleteness()
    // =========================================================================

    public function test_validates_complete_document(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        // Content covering all 8 sections of predsjednik_suda:
        // heading, case_reference, identification, facts_chronology,
        // legal_arguments, requests, legal_remedy_demand, signature
        $content = "ZAGLAVLJE\nBroj predmeta: Pp Prz-74/2025\nPodnositelj zahtjeva: Test\nCINJENICE ovdje su opisane\nPRAVNI ARGUMENTI su jaki\nZAHTJEV: molimo\nPravni lijek: pravo na zalbu\nPotpis ovdje";

        $result = $this->validator->validateCompleteness($content, $profile);

        $this->assertTrue($result['complete']);
        $this->assertEmpty($result['missing_sections']);
        $this->assertEquals(100.0, $result['completeness_score']);
    }

    public function test_detects_missing_sections(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        // Content only matches 'heading' and 'signature'
        $content = "Samo zaglavlje i potpis ovdje";

        $result = $this->validator->validateCompleteness($content, $profile);

        $this->assertFalse($result['complete']);
        $this->assertNotEmpty($result['missing_sections']);
        $this->assertLessThan(100, $result['completeness_score']);
    }

    public function test_completeness_score_is_proportional(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $totalSections = count($profile->structure);

        // Content that matches roughly half the sections
        $content = "Zaglavlje naslov\nPodnositelj identifikacija\nCINJENICE opis";

        $result = $this->validator->validateCompleteness($content, $profile);

        $this->assertGreaterThan(0, $result['completeness_score']);
        $this->assertLessThanOrEqual(100, $result['completeness_score']);
        // Score should reflect the proportion of matched sections
        $this->assertCount($totalSections, array_merge($result['present_sections'], $result['missing_sections']));
    }

    public function test_section_detection_is_case_insensitive(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        // Mix of upper, lower, and mixed case
        $content = "ZAGLAVLJE\nbroj predmeta\npodnositelj\ncinjenice\npravni argumenti\nzahtjev\npravni lijek\npotpis";

        $result = $this->validator->validateCompleteness($content, $profile);

        // All sections should be found regardless of case
        $this->assertTrue($result['complete']);
        $this->assertEmpty($result['missing_sections']);
    }

    public function test_empty_content_has_all_sections_missing(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $result = $this->validator->validateCompleteness('', $profile);

        $this->assertFalse($result['complete']);
        $this->assertEquals(count($profile->structure), count($result['missing_sections']));
        $this->assertEquals(0.0, $result['completeness_score']);
    }

    public function test_returns_correct_array_keys(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $result = $this->validator->validateCompleteness('test content', $profile);

        $this->assertArrayHasKey('complete', $result);
        $this->assertArrayHasKey('missing_sections', $result);
        $this->assertArrayHasKey('present_sections', $result);
        $this->assertArrayHasKey('completeness_score', $result);
        $this->assertIsBool($result['complete']);
        $this->assertIsArray($result['missing_sections']);
        $this->assertIsArray($result['present_sections']);
        $this->assertIsFloat($result['completeness_score']);
    }

    public function test_empty_structure_profile_is_complete(): void
    {
        $profile = new DocumentProfile(
            key: 'test_empty',
            name: 'Test Empty Profile',
            recipient: ['title' => 'Test'],
            legalBasis: [],
            tone: 'formal',
            structure: [],
            docxTemplate: 'legal-formal',
        );

        $result = $this->validator->validateCompleteness('any content', $profile);

        $this->assertTrue($result['complete']);
        $this->assertEmpty($result['missing_sections']);
        $this->assertEquals(100.0, $result['completeness_score']);
    }

    // =========================================================================
    // Tests for isFireReady()
    // =========================================================================

    public function test_is_fire_ready_blocks_on_missing_sections(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $content = "Samo parcijalni sadrzaj";

        $result = $this->validator->isFireReady($content, $profile, $context);

        $this->assertFalse($result['fire_ready']);
        $this->assertNotEmpty($result['blockers']);
        // Quality validation should NOT have been called (save API costs)
        $this->assertNull($result['quality']);
    }

    public function test_is_fire_ready_returns_correct_array_keys(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $result = $this->validator->isFireReady('partial content', $profile, $context);

        $this->assertArrayHasKey('fire_ready', $result);
        $this->assertArrayHasKey('completeness', $result);
        $this->assertArrayHasKey('quality', $result);
        $this->assertArrayHasKey('blockers', $result);
    }

    public function test_is_fire_ready_skips_llm_when_incomplete(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        // The LLM mock should NOT receive a 'generate' call for validation
        // since completeness check fails first
        $strictLlm = Mockery::mock(LlmClient::class);
        $strictLlm->shouldNotReceive('generate');
        $validator = new ArgumentValidator($strictLlm, new DevastatingArgumentBuilder());

        $result = $validator->isFireReady('incomplete content', $profile, $context);

        $this->assertFalse($result['fire_ready']);
        $this->assertNull($result['quality']);
    }

    public function test_is_fire_ready_runs_llm_when_complete_and_passes(): void
    {
        // Use a profile with structure we can fully match
        $profile = new DocumentProfile(
            key: 'test_simple',
            name: 'Test Simple',
            recipient: ['title' => 'Test'],
            legalBasis: [],
            tone: 'formal',
            structure: ['heading', 'signature'],
            docxTemplate: 'legal-formal',
        );
        $context = CaseContext::fromConfig();

        // Content that matches all sections
        $content = "ZAGLAVLJE ovdje\nPotpis ovdje";

        // LLM should be called and return a high-quality result
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'overall_score' => 9,
                'citation_accuracy' => 9,
                'argument_strength' => 9,
                'logical_coherence' => 9,
                'verdict' => 'FIRE_READY',
                'issues' => [],
                'improvements' => [],
                'strengths' => ['Excellent'],
            ]));

        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $result = $validator->isFireReady($content, $profile, $context);

        $this->assertTrue($result['fire_ready']);
        $this->assertEmpty($result['blockers']);
        $this->assertNotNull($result['quality']);
        $this->assertInstanceOf(ValidationResult::class, $result['quality']);
    }

    public function test_is_fire_ready_blocks_on_low_quality_score(): void
    {
        // Use a profile with structure we can fully match
        $profile = new DocumentProfile(
            key: 'test_simple',
            name: 'Test Simple',
            recipient: ['title' => 'Test'],
            legalBasis: [],
            tone: 'formal',
            structure: ['heading', 'signature'],
            docxTemplate: 'legal-formal',
        );
        $context = CaseContext::fromConfig();

        $content = "ZAGLAVLJE ovdje\nPotpis ovdje";

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'overall_score' => 5,
                'citation_accuracy' => 5,
                'argument_strength' => 5,
                'logical_coherence' => 5,
                'verdict' => 'MODERATE',
                'issues' => [['severity' => 'major', 'description' => 'Weak arguments']],
                'improvements' => ['Add legal basis'],
                'strengths' => [],
            ]));

        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $result = $validator->isFireReady($content, $profile, $context);

        $this->assertFalse($result['fire_ready']);
        $this->assertNotEmpty($result['blockers']);
        $this->assertNotNull($result['quality']);
        // Should have blockers about low score and wrong verdict
        $blockerText = implode(' ', $result['blockers']);
        $this->assertStringContainsString('score', mb_strtolower($blockerText));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
