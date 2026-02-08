<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalCase;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\StrategicPlanner;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StrategicPlannerTest extends TestCase
{
    use UsesTestDatabase;

    protected StrategicPlanner $strategicPlanner;

    protected $openAIMock;

    protected $durationEstimatorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->durationEstimatorMock = Mockery::mock(DurationEstimator::class);
        $this->durationEstimatorMock->shouldIgnoreMissing();

        $this->strategicPlanner = new StrategicPlanner(
            $this->openAIMock,
            $this->durationEstimatorMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_action_plan_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now()->subMonth(),
        ]);

        $case->documents()->createMany(
            array_fill(0, 15, [
                'title' => 'Document',
                'content' => 'This is a legal document',
                'doc_id' => 'doc-'.uniqid(),
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'embedding_vector' => array_fill(0, 1536, 0),
                'content_hash' => hash('sha256', 'document-content-'.uniqid()),
            ])
        );

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('phases', $result);
        $this->assertArrayHasKey('total_phases', $result);
        $this->assertArrayHasKey('total_duration_days', $result);
        $this->assertArrayHasKey('milestones', $result);
        $this->assertArrayHasKey('settlement_windows', $result);
        $this->assertArrayHasKey('resources_needed', $result);
        $this->assertArrayHasKey('requires_trial', $result);
    }

    /** @test */
    public function it_generates_multiple_phases()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now(),
        ]);

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertGreaterThanOrEqual(3, count($result['phases'])); // At least discovery, motions, post-decision
        $this->assertGreaterThan(0, $result['total_phases']);
    }

    /** @test */
    public function it_includes_phase_details()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $phase = $result['phases'][0];
        $this->assertArrayHasKey('phase_number', $phase);
        $this->assertArrayHasKey('name', $phase);
        $this->assertArrayHasKey('duration_days', $phase);
        $this->assertArrayHasKey('actions', $phase);
        $this->assertArrayHasKey('deliverables', $phase);
        $this->assertArrayHasKey('start_date', $phase);
        $this->assertArrayHasKey('end_date', $phase);

        $this->assertNotEmpty($phase['actions']);
        $this->assertNotEmpty($phase['deliverables']);
    }

    /** @test */
    public function it_plans_discovery_phase()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $case->documents()->createMany(
            array_fill(0, 20, ['title' => 'Document', 'file_path' => 'doc.pdf'])
        );

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $discoveryPhase = collect($result['phases'])->firstWhere('name', 'Discovery & Investigation');
        $this->assertNotNull($discoveryPhase);
        $this->assertGreaterThan(0, $discoveryPhase['duration_days']);
    }

    /** @test */
    public function it_plans_motions_phase()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $motionsPhase = collect($result['phases'])->firstWhere('name', 'Motions & Hearings');
        $this->assertNotNull($motionsPhase);
    }

    /** @test */
    public function it_identifies_settlement_windows()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertNotEmpty($result['settlement_windows']);
        $settlementWindow = $result['settlement_windows'][0];
        $this->assertArrayHasKey('window', $settlementWindow);
        $this->assertArrayHasKey('timing', $settlementWindow);
        $this->assertArrayHasKey('rationale', $settlementWindow);
    }

    /** @test */
    public function it_calculates_resource_requirements()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $case->documents()->createMany(
            array_fill(0, 30, ['title' => 'Document', 'file_path' => 'doc.pdf'])
        );

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertArrayHasKey('total_attorney_hours', $result['resources_needed']);
        $this->assertArrayHasKey('total_estimated_cost', $result['resources_needed']);
        $this->assertArrayHasKey('expert_witnesses_needed', $result['resources_needed']);
        $this->assertArrayHasKey('support_staff_hours', $result['resources_needed']);

        $this->assertGreaterThan(0, $result['resources_needed']['total_attorney_hours']);
        $this->assertGreaterThan(0, $result['resources_needed']['total_estimated_cost']);
    }

    /** @test */
    public function it_generates_milestones()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertNotEmpty($result['milestones']);
        $milestone = $result['milestones'][0];
        $this->assertArrayHasKey('name', $milestone);
        $this->assertArrayHasKey('target_date', $milestone);
        $this->assertArrayHasKey('phase', $milestone);
    }

    /** @test */
    public function it_determines_if_trial_is_required()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $this->assertIsBool($result['requires_trial']);
    }

    /** @test */
    public function it_adjusts_plan_based_on_case_complexity()
    {
        // Arrange
        $simpleCase = LegalCase::factory()->create();
        $simpleCase->documents()->createMany(
            array_fill(0, 3, ['title' => 'Doc', 'file_path' => 'doc.pdf'])
        );

        $complexCase = LegalCase::factory()->create();
        $complexCase->documents()->createMany(
            array_fill(0, 50, ['title' => 'Doc', 'file_path' => 'doc.pdf'])
        );

        // Act
        $simpleResult = $this->strategicPlanner->createActionPlan($simpleCase->id);
        $complexResult = $this->strategicPlanner->createActionPlan($complexCase->id);

        // Assert
        $this->assertLessThan(
            $complexResult['total_duration_days'],
            $simpleResult['total_duration_days']
        );

        $this->assertLessThan(
            $complexResult['resources_needed']['total_attorney_hours'],
            $simpleResult['resources_needed']['total_attorney_hours']
        );
    }

    /** @test */
    public function it_sequences_phases_chronologically()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now(),
        ]);

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $phases = $result['phases'];
        for ($i = 0; $i < count($phases) - 1; $i++) {
            $currentEnd = \Carbon\Carbon::parse($phases[$i]['end_date']);
            $nextStart = \Carbon\Carbon::parse($phases[$i + 1]['start_date']);

            // Next phase should start on or after current phase ends
            $this->assertGreaterThanOrEqual($currentEnd, $nextStart);
        }
    }

    /** @test */
    public function it_includes_post_decision_phase()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $postDecisionPhase = collect($result['phases'])->firstWhere('name', 'Post-Decision & Enforcement');
        $this->assertNotNull($postDecisionPhase);
    }

    /** @test */
    public function it_calculates_total_duration_from_phases()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id);

        // Assert
        $sumOfPhaseDurations = array_sum(array_column($result['phases'], 'duration_days'));
        $this->assertEquals($sumOfPhaseDurations, $result['total_duration_days']);
    }

    /** @test */
    public function it_handles_objectives_parameter()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->durationEstimatorMock
            ->shouldReceive('estimateDuration')
            ->andReturn(['estimated_days' => 365, 'factors' => ['baseline_days' => 300]]);

        $objectives = ['Win the case', 'Minimize costs', 'Complete discovery quickly'];

        // Act
        $result = $this->strategicPlanner->createActionPlan($case->id, $objectives);

        // Assert
        $this->assertArrayHasKey('phases', $result);
        // Plan should be adjusted based on objectives
    }
}
