<?php

namespace Tests\Integration;

use App\Models\LegalCase;
use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\CaseIntakeService;
use App\Services\LegalReasoning\FactPatternExtractor;

/**
 * Integration test for complete case intake workflow
 *
 * This demonstrates how FactPatternExtractor integrates with the
 * broader legal reasoning system through CaseIntakeService.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 */
class CaseIntakeIntegrationTest extends IntegrationTestCase
{
    protected User $user;

    protected CaseIntakeService $intakeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_completes_full_intake_workflow()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $intakeService = app(CaseIntakeService::class);

        // Process intake
        $result = $intakeService->processIntake([
            'narrative' => 'On March 15, 2024, I signed a contract with XYZ Construction to renovate my restaurant for 500,000 HRK. The work was supposed to be completed by June 1, 2024. They started work on March 20 but stopped after only 2 weeks claiming they needed an additional 200,000 HRK. I refused to pay more. Now they have abandoned the project and I have lost 3 months of business revenue estimated at 150,000 HRK per month.',
            'client_name' => 'My Restaurant Inc',
            'opponent_name' => 'XYZ Construction',
            'objectives' => ['Recover damages', 'Complete renovation'],
            'user_id' => $this->user->id,
        ]);

        // Verify successful processing
        $this->assertTrue($result['success']);

        // Verify fact pattern was created
        $this->assertNotNull($result['fact_pattern_id']);
        $factPattern = LegalFactPattern::find($result['fact_pattern_id']);
        $this->assertInstanceOf(LegalFactPattern::class, $factPattern);
        $this->assertEquals('contract', $factPattern->legal_area);
        $this->assertEquals(0.87, $factPattern->extraction_confidence);

        // Verify case was created
        $this->assertNotNull($result['case_id']);
        $case = LegalCase::find($result['case_id']);
        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertEquals('My Restaurant Inc', $case->client_name);
        $this->assertEquals('XYZ Construction', $case->opponent_name);
        $this->assertEquals('intake', $case->status);

        // Verify analysis was performed
        $analysis = $result['analysis'];
        $this->assertEquals('contract', $analysis['legal_area']);
        $this->assertEquals(0.87, $analysis['extraction_confidence']);

        // Verify parties extracted
        $this->assertCount(2, $analysis['parties']);

        // Verify legal issues identified
        $this->assertNotEmpty($analysis['legal_issues']);

        // Verify risk assessment performed
        $this->assertArrayHasKey('risk_assessment', $analysis);
        $this->assertArrayHasKey('overall_risk_level', $analysis['risk_assessment']);

        // Verify outcome prediction
        $this->assertArrayHasKey('outcome_prediction', $analysis);
        $this->assertArrayHasKey('success_probability', $analysis['outcome_prediction']);

        // Verify preliminary strategy generated
        $this->assertArrayHasKey('preliminary_strategy', $analysis);
        $this->assertArrayHasKey('recommended_approach', $analysis['preliminary_strategy']);

        // Verify next steps identified
        $this->assertArrayHasKey('next_steps', $analysis);
        $this->assertNotEmpty($analysis['next_steps']);

        // Verify next steps include discovery (because there's evidence that needs discovery)
        $discoveryStep = collect($analysis['next_steps'])->first(function ($step) {
            return str_contains($step['action'], 'discovery');
        });
        $this->assertNotNull($discoveryStep, 'Should recommend discovery for unavailable evidence');
    }

    /** @test */
    public function it_identifies_high_risk_cases()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $intakeService = app(CaseIntakeService::class);

        $result = $intakeService->processIntake([
            'narrative' => 'Unclear legal situation with many uncertainties...',
            'client_name' => 'John Doe',
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($result['success']);

        // Should identify as high risk
        $riskLevel = $result['analysis']['risk_assessment']['overall_risk_level'];
        $this->assertContains($riskLevel, ['medium', 'high']);

        // Should flag low confidence
        $flags = $result['analysis']['flags'] ?? [];
        $hasLowConfidenceFlag = collect($flags)->contains(function ($flag) {
            return str_contains($flag['message'] ?? '', 'Low extraction confidence');
        });
        $this->assertTrue($hasLowConfidenceFlag);

        // Should recommend client interview as next step
        $nextSteps = $result['analysis']['next_steps'];
        $hasInterviewStep = collect($nextSteps)->contains(function ($step) {
            return str_contains($step['action'], 'interview');
        });
        $this->assertTrue($hasInterviewStep);
    }

    /** @test */
    public function it_finds_similar_cases_during_intake()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        // Create existing fact pattern
        $existingPattern = LegalFactPattern::factory()->contract()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Test Party', 'role' => 'plaintiff'],
                ],
                'legal_issues' => [
                    ['area_of_law' => 'contract'],
                ],
            ],
        ]);

        $intakeService = app(CaseIntakeService::class);

        $result = $intakeService->processIntake([
            'narrative' => 'A contract dispute case...',
            'client_name' => 'New Client',
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($result['success']);

        // Should find the similar case
        $similarCases = $result['analysis']['similar_cases'] ?? [];
        $this->assertGreaterThan(0, count($similarCases), 'Should find at least one similar case');
    }

    /** @test */
    public function it_generates_case_number_and_title()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $intakeService = app(CaseIntakeService::class);

        $result = $intakeService->processIntake([
            'narrative' => 'I slipped and fell at the store...',
            'client_name' => 'Jane Smith',
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($result['success']);

        $case = LegalCase::find($result['case_id']);

        // Should have generated case number
        $this->assertNotNull($case->case_number);
        $this->assertStringStartsWith('CASE-', $case->case_number);

        // Should have generated title
        $this->assertNotNull($case->title);
        $this->assertGreaterThan(5, strlen($case->title));
    }
}
