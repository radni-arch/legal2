<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\HrLegalCitationsDetector;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\QualityGate;
use Mockery;
use Tests\TestCase;

class QualityGateTest extends TestCase
{
    private QualityGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('{}')->byDefault();
        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $detector = new HrLegalCitationsDetector();
        $this->gate = new QualityGate($validator, $detector);
    }

    public function test_evaluates_complete_document(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-predsjednik-suda.txt'));

        $result = $this->gate->evaluate($content, $profile, $context);

        $this->assertArrayHasKey('passed', $result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('completeness', $result);
        $this->assertArrayHasKey('citations', $result);
        $this->assertArrayHasKey('blockers', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsFloat($result['quality_score']);
        $this->assertGreaterThan(0, $result['quality_score']);
    }

    public function test_blocks_incomplete_document(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-incomplete.txt'));

        $result = $this->gate->evaluate($content, $profile, $context);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['blockers']);
    }

    public function test_detects_citations_in_document(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-predsjednik-suda.txt'));

        $result = $this->gate->evaluate($content, $profile, $context);

        $this->assertGreaterThan(0, $result['citations']['total']);
    }

    public function test_blocks_empty_document(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $result = $this->gate->evaluate('', $profile, $context);

        $this->assertFalse($result['passed']);
        $this->assertContains('No legal citations found in document', $result['blockers']);
    }

    public function test_quality_score_within_range(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-predsjednik-suda.txt'));

        $result = $this->gate->evaluate($content, $profile, $context);

        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(10, $result['quality_score']);
    }

    public function test_returns_citation_breakdown_by_type(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-predsjednik-suda.txt'));

        $result = $this->gate->evaluate($content, $profile, $context);

        $this->assertArrayHasKey('by_type', $result['citations']);
        $this->assertArrayHasKey('total', $result['citations']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
