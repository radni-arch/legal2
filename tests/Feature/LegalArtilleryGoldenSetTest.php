<?php

namespace Tests\Feature;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\HrLegalCitationsDetector;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\QualityGate;
use Mockery;
use Tests\TestCase;

class LegalArtilleryGoldenSetTest extends TestCase
{
    public function test_sample_predsjednik_suda_passes_quality_gate(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-predsjednik-suda.txt'));

        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('{}');
        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $detector = new HrLegalCitationsDetector();
        $gate = new QualityGate($validator, $detector);

        $result = $gate->evaluate($content, $profile, $context);

        $this->assertTrue($result['passed'], 'Golden set document should pass quality gate. Blockers: ' . implode(', ', $result['blockers']));
    }

    public function test_sample_incomplete_fails_quality_gate(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/LegalArtillery/sample-incomplete.txt'));

        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('{}');
        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $detector = new HrLegalCitationsDetector();
        $gate = new QualityGate($validator, $detector);

        $result = $gate->evaluate($content, $profile, $context);

        $this->assertFalse($result['passed'], 'Incomplete document should NOT pass quality gate');
    }

    public function test_expected_sections_match_profile(): void
    {
        $expected = json_decode(
            file_get_contents(base_path('tests/Fixtures/LegalArtillery/expected-sections.json')),
            true
        );

        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $this->assertEquals(
            $expected['predsjednik_suda']['required_sections'],
            $profile->structure,
            'Profile structure should match expected sections fixture'
        );
    }

    public function test_golden_set_outputs_match_structure_and_citations(): void
    {
        $this->assertFixtureSetMatches(
            'tests/Fixtures/LegalArtillery/golden-set/expected-golden-set.json'
        );
    }

    public function test_real_document_set_outputs_match_structure_and_citations(): void
    {
        $this->assertFixtureSetMatches(
            'tests/Fixtures/LegalArtillery/real-set/expected-real-set.json'
        );
    }

    private function assertFixtureSetMatches(string $fixturePath): void
    {
        $fixtures = json_decode(
            file_get_contents(base_path($fixturePath)),
            true
        );

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('{}');
        $validator = new ArgumentValidator($llm, new DevastatingArgumentBuilder());
        $detector = new HrLegalCitationsDetector();

        foreach ($fixtures['documents'] as $fixture) {
            $content = file_get_contents(base_path($fixture['path']));
            $profile = DocumentProfile::fromConfig($fixture['profile']);

            $this->assertSame(
                $fixture['required_sections'],
                $profile->structure,
                "Fixture {$fixture['name']} should match profile structure"
            );

            $completeness = $validator->validateCompleteness($content, $profile);
            $this->assertTrue(
                $completeness['complete'],
                'Fixture '.$fixture['name'].' missing sections: '.implode(', ', $completeness['missing_sections'])
            );

            $citations = $detector->detectAll($content);
            foreach ($fixture['citation_expectations'] as $type => $minCount) {
                $count = count($citations[$type] ?? []);
                $this->assertGreaterThanOrEqual(
                    $minCount,
                    $count,
                    "Fixture {$fixture['name']} expected {$minCount}+ {$type} citations, got {$count}"
                );
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
