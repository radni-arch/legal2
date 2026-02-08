<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LegalPlayground;
use App\Models\LegalCase;
use App\Models\User;
use App\Modules\Evidence\Services\EvidenceAnalysisService;
use App\Modules\Evidence\Services\RecontextualizationService;
use App\Modules\HomeSearch\Services\HomeSearchAbuseDetector;
use App\Modules\Misconduct\Services\ComplaintGenerator;
use App\Modules\Misconduct\Services\DismissalMotionGenerator;
use App\Modules\Misconduct\Services\MisconductDetector;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive Test Suite for LegalPlayground Component
 *
 * Tests all modules:
 * - Evidence Analysis
 * - Recontextualization
 * - Misconduct Detection
 * - Topic Framework
 *
 * Coverage:
 * - Component mounting and initialization
 * - Module switching
 * - Form validation
 * - Service integration
 * - Error handling
 * - Empty states
 */
class LegalPlaygroundTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for authentication
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_can_mount_and_display_the_component()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->assertStatus(200)
            ->assertSet('activeModule', 'evidence')
            ->assertSet('selectedCaseId', $case->id)
            ->assertSee('Legal Defense Playground');
    }

    /** @test */
    public function it_displays_empty_state_when_no_cases_exist()
    {
        Livewire::test(LegalPlayground::class)
            ->assertSee('No Cases Available')
            ->assertSee('Please create a legal case first');
    }

    /** @test */
    public function it_can_switch_between_modules()
    {
        LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->assertSet('activeModule', 'evidence')
            ->call('setModule', 'misconduct')
            ->assertSet('activeModule', 'misconduct')
            ->call('setModule', 'topics')
            ->assertSet('activeModule', 'topics');
    }

    /** @test */
    public function it_loads_recent_cases_on_mount()
    {
        LegalCase::factory()->count(5)->create();

        Livewire::test(LegalPlayground::class)
            ->assertSet('cases', function ($cases) {
                return count($cases) === 5;
            });
    }

    /** @test */
    public function it_can_reset_all_results()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('evidenceAnalysisResult', ['test' => 'data'])
            ->set('misconductResult', ['test' => 'data'])
            ->call('resetResults')
            ->assertSet('evidenceAnalysisResult', null)
            ->assertSet('misconductResult', null);
    }

    // ========================================
    // Evidence Analysis Tests
    // ========================================

    /** @test */
    public function it_validates_evidence_analysis_input()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('evidenceDescription', '') // Empty - should fail
            ->call('analyzeEvidence')
            ->assertHasErrors(['evidenceDescription']);
    }

    /** @test */
    public function it_validates_evidence_description_minimum_length()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('evidenceDescription', 'short') // Too short
            ->call('analyzeEvidence')
            ->assertHasErrors(['evidenceDescription']);
    }

    /** @test */
    public function it_can_analyze_evidence_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockService = Mockery::mock(EvidenceAnalysisService::class);
        $mockService->shouldReceive('analyzeEvidence')
            ->once()
            ->andReturn([
                'type' => 'communication',
                'analysis' => 'Test analysis',
                'constitutional_violations' => [],
            ]);

        $this->app->instance(EvidenceAnalysisService::class, $mockService);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('evidenceDescription', 'This is a valid evidence description.')
            ->call('analyzeEvidence')
            ->assertSet('successMessage', 'Evidence analyzed successfully!')
            ->assertSet('evidenceAnalysisResult', function ($result) {
                return $result['type'] === 'communication';
            });
    }

    /** @test */
    public function it_handles_evidence_analysis_errors_gracefully()
    {
        $case = LegalCase::factory()->create();

        $mockService = Mockery::mock(EvidenceAnalysisService::class);
        $mockService->shouldReceive('analyzeEvidence')
            ->once()
            ->andThrow(new \Exception('Analysis failed'));

        $this->app->instance(EvidenceAnalysisService::class, $mockService);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('evidenceDescription', 'Valid description')
            ->call('analyzeEvidence')
            ->assertSet('errorMessage', 'Analysis failed: Analysis failed')
            ->assertSet('loading', false);
    }

    // ========================================
    // Recontextualization Tests
    // ========================================

    /** @test */
    public function it_validates_recontextualization_input()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('prosecutionEvidence', 'short')
            ->set('fullContent', '') // Empty - should fail
            ->call('recontextualizeEvidence')
            ->assertHasErrors(['prosecutionEvidence', 'fullContent']);
    }

    /** @test */
    public function it_can_recontextualize_evidence_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockService = Mockery::mock(RecontextualizationService::class);
        $mockService->shouldReceive('recontextualize')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('array'), Mockery::type(LegalCase::class))
            ->andReturn([
                'selective_presentation_detected' => true,
                'severity' => 80,
                'defense_narrative' => 'Test narrative',
            ]);

        $this->app->instance(RecontextualizationService::class, $mockService);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('prosecutionEvidence', 'Prosecution selective quote')
            ->set('fullContent', 'Full context with more information')
            ->call('recontextualizeEvidence')
            ->assertSet('successMessage', 'Evidence recontextualized successfully!')
            ->assertSet('recontextResult', function ($result) {
                return $result['selective_presentation_detected'] === true;
            });
    }

    // ========================================
    // Misconduct Detection Tests
    // ========================================

    /** @test */
    public function it_validates_misconduct_detection_input()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductDetails', '') // Empty - should fail
            ->call('detectMisconduct')
            ->assertHasErrors(['misconductDetails']);
    }

    /** @test */
    public function it_can_detect_misconduct_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = Mockery::mock(MisconductDetector::class);
        $mockDetector->shouldReceive('detectMisconduct')
            ->once()
            ->andReturn([
                'severity' => 90,
                'description' => 'Fabricated probable cause',
                'legal_violations' => [],
            ]);

        $this->app->instance(MisconductDetector::class, $mockDetector);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductDetails', 'Warrant was backdated by 2 days')
            ->call('detectMisconduct')
            ->assertSet('successMessage', 'Misconduct detected and analyzed!')
            ->assertSet('misconductResult', function ($result) {
                return $result['severity'] === 90;
            });
    }

    /** @test */
    public function it_requires_misconduct_detection_before_generating_dismissal_motion()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductResult', null)
            ->call('generateDismissalMotion')
            ->assertSet('errorMessage', 'Please detect misconduct first before generating a dismissal motion.');
    }

    /** @test */
    public function it_can_generate_dismissal_motion_after_misconduct_detection()
    {
        $case = LegalCase::factory()->create();

        $mockGenerator = Mockery::mock(DismissalMotionGenerator::class);
        $mockGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([
                'content' => 'ZAHTJEV ZA ODBACIVANJE\n\nTest content...',
            ]);

        $this->app->instance(DismissalMotionGenerator::class, $mockGenerator);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductResult', ['severity' => 90])
            ->call('generateDismissalMotion')
            ->assertSet('successMessage', 'Dismissal motion generated!')
            ->assertSet('dismissalMotion', function ($motion) {
                return isset($motion['content']);
            });
    }

    /** @test */
    public function it_requires_misconduct_detection_before_generating_complaint()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductResult', null)
            ->call('generateComplaint')
            ->assertSet('errorMessage', 'Please detect misconduct first before generating a complaint.');
    }

    /** @test */
    public function it_can_generate_complaint_after_misconduct_detection()
    {
        $case = LegalCase::factory()->create();

        $mockGenerator = Mockery::mock(ComplaintGenerator::class);
        $mockGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([
                'content' => 'PRIJAVA\n\nTest content...',
            ]);

        $this->app->instance(ComplaintGenerator::class, $mockGenerator);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductResult', ['severity' => 90])
            ->call('generateComplaint')
            ->assertSet('successMessage', 'Complaint generated!')
            ->assertSet('complaint', function ($complaint) {
                return isset($complaint['content']);
            });
    }

    // ========================================
    // Topic Framework Tests
    // ========================================

    /** @test */
    public function it_validates_drug_charge_analysis_input()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('amount', -5) // Negative - should fail
            ->call('analyzeTopic')
            ->assertHasErrors(['amount']);
    }

    /** @test */
    public function it_can_analyze_drug_charges_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = Mockery::mock(DrugChargeAbuseDetector::class);
        $mockDetector->shouldReceive('analyzeCase')
            ->once()
            ->andReturn([
                'overcharge_detected' => true,
                'overcharge_severity' => 75,
                'threshold_analysis' => [
                    'actual_amount' => 30,
                    'threshold_amount' => 100,
                    'personal_use_likely' => true,
                ],
            ]);

        $this->app->instance(DrugChargeAbuseDetector::class, $mockDetector);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('selectedTopic', 'drug_charge_severity')
            ->set('drugType', 'cannabis')
            ->set('amount', 30)
            ->set('chargedAs', 'dealing')
            ->call('analyzeTopic')
            ->assertSet('successMessage', 'Topic analyzed successfully!')
            ->assertSet('topicResult', function ($result) {
                return $result['overcharge_detected'] === true;
            });
    }

    /** @test */
    public function it_can_analyze_home_search_abuse()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = Mockery::mock(HomeSearchAbuseDetector::class);
        $mockDetector->shouldReceive('analyzeCase')
            ->once()
            ->andReturn([
                'abuse_detected' => true,
                'severity' => 85,
            ]);

        $this->app->instance(HomeSearchAbuseDetector::class, $mockDetector);

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('selectedTopic', 'home_search_abuse')
            ->call('analyzeTopic')
            ->assertSet('successMessage', 'Topic analyzed successfully!');
    }

    /** @test */
    public function it_validates_regional_comparison_input()
    {
        Livewire::test(LegalPlayground::class)
            ->set('region1', 'Osijek')
            ->set('region2', 'Osijek') // Same region - should fail
            ->set('comparisonYear', 2025)
            ->call('compareRegions')
            ->assertSet('errorMessage', 'Please select two different regions to compare.');
    }

    /** @test */
    public function it_can_compare_regions_successfully()
    {
        $mockDetector = Mockery::mock(DrugChargeAbuseDetector::class);
        $mockDetector->shouldReceive('compareRegions')
            ->once()
            ->with('Osijek', 'Zagreb', 2025)
            ->andReturn([
                'worse_region' => [
                    'worse_region' => 'Osijek',
                    'analysis' => 'Osijek has higher overcharge rates',
                ],
                'region1' => [
                    'name' => 'Osijek',
                    'statistics' => ['overcharge_percentage' => 45],
                ],
                'region2' => [
                    'name' => 'Zagreb',
                    'statistics' => ['overcharge_percentage' => 30],
                ],
            ]);

        $this->app->instance(DrugChargeAbuseDetector::class, $mockDetector);

        Livewire::test(LegalPlayground::class)
            ->set('selectedTopic', 'drug_charge_severity')
            ->set('region1', 'Osijek')
            ->set('region2', 'Zagreb')
            ->set('comparisonYear', 2025)
            ->call('compareRegions')
            ->assertSet('successMessage', 'Regions compared successfully!')
            ->assertSet('comparisonResult', function ($result) {
                return $result['worse_region']['worse_region'] === 'Osijek';
            });
    }

    /** @test */
    public function it_validates_case_id_exists_for_all_modules()
    {
        $nonExistentCaseId = 99999;

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', $nonExistentCaseId)
            ->set('evidenceDescription', 'Valid description')
            ->call('analyzeEvidence')
            ->assertHasErrors(['selectedCaseId']);
    }

    /** @test */
    public function it_resets_messages_when_switching_modules()
    {
        LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('successMessage', 'Test success')
            ->set('errorMessage', 'Test error')
            ->call('setModule', 'misconduct')
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', null);
    }

    /** @test */
    public function it_validates_evidence_type_is_valid()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('evidenceType', 'invalid_type')
            ->set('evidenceDescription', 'Valid description')
            ->call('analyzeEvidence')
            ->assertHasErrors(['evidenceType']);
    }

    /** @test */
    public function it_validates_misconduct_type_is_valid()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('misconductType', 'invalid_type')
            ->set('misconductDetails', 'Valid details')
            ->call('detectMisconduct')
            ->assertHasErrors(['misconductType']);
    }

    /** @test */
    public function it_validates_drug_type_is_valid()
    {
        $case = LegalCase::factory()->create();

        Livewire::test(LegalPlayground::class)
            ->set('selectedCaseId', (string) $case->id) // Cast ULID to string
            ->set('selectedTopic', 'drug_charge_severity')
            ->set('drugType', 'invalid_drug')
            ->set('amount', 30)
            ->call('analyzeTopic')
            ->assertHasErrors(['drugType']);
    }

    /** @test */
    public function it_validates_region_is_valid()
    {
        Livewire::test(LegalPlayground::class)
            ->set('selectedTopic', 'drug_charge_severity')
            ->set('region1', 'InvalidRegion')
            ->set('region2', 'Zagreb')
            ->set('comparisonYear', 2025)
            ->call('compareRegions')
            ->assertHasErrors(['region1']);
    }

    /** @test */
    public function it_validates_comparison_year_range()
    {
        Livewire::test(LegalPlayground::class)
            ->set('region1', 'Osijek')
            ->set('region2', 'Zagreb')
            ->set('comparisonYear', 2050) // Too far in future
            ->call('compareRegions')
            ->assertHasErrors(['comparisonYear']);
    }
}
