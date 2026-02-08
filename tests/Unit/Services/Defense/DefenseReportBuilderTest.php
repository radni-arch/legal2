<?php

namespace Tests\Unit\Services\Defense;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Defense\DefenseReportBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefenseReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2024-02-04 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /** @test */
    public function it_registers_detector(): void
    {
        $builder = new DefenseReportBuilder();
        $detector = $this->createMockDetector('test_tactic', 'Test Label', []);

        $result = $builder->register($detector);

        $this->assertSame($builder, $result); // Fluent interface
    }

    /** @test */
    public function it_builds_empty_report_with_no_detectors(): void
    {
        $builder = new DefenseReportBuilder();

        $report = $builder->buildReport('case-123');

        $this->assertInstanceOf(DefenseReport::class, $report);
        $this->assertEquals('case-123', $report->caseId);
        $this->assertEmpty($report->flags);
        $this->assertEmpty($report->detectorsRun);
        $this->assertGreaterThanOrEqual(0, $report->processingTime);
    }

    /** @test */
    public function it_runs_detector_and_collects_flags(): void
    {
        $builder = new DefenseReportBuilder();
        $flag = new DefenseFlag(
            tactic: 'zastara',
            severity: DefenseFlag::SEVERITY_CRITICAL,
            title: 'Test Flag',
            description: 'Test description',
            legalBasis: 'Test basis',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'Test action',
            confidence: 0.95,
        );

        $detector = $this->createMockDetector('zastara', 'Zastara', [], [$flag]);
        $builder->register($detector);

        $report = $builder->buildReport('case-456');

        $this->assertCount(1, $report->flags);
        $this->assertEquals('zastara', $report->flags[0]->tactic);
        $this->assertContains('zastara', $report->detectorsRun);
    }

    /** @test */
    public function it_runs_multiple_detectors(): void
    {
        $builder = new DefenseReportBuilder();

        $flag1 = new DefenseFlag(
            tactic: 'zastara',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'Zastara Flag',
            description: 'Description',
            legalBasis: 'Basis',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'Action',
            confidence: 0.8,
        );

        $flag2 = new DefenseFlag(
            tactic: 'ne_bis_in_idem',
            severity: DefenseFlag::SEVERITY_MEDIUM,
            title: 'Ne Bis Flag',
            description: 'Description',
            legalBasis: 'Basis',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'Action',
            confidence: 0.7,
        );

        $detector1 = $this->createMockDetector('zastara', 'Zastara', [], [$flag1]);
        $detector2 = $this->createMockDetector('ne_bis_in_idem', 'Ne Bis', [], [$flag2]);

        $builder->register($detector1)->register($detector2);

        $report = $builder->buildReport('case-multi');

        $this->assertCount(2, $report->flags);
        $this->assertCount(2, $report->detectorsRun);
        $this->assertContains('zastara', $report->detectorsRun);
        $this->assertContains('ne_bis_in_idem', $report->detectorsRun);
    }

    /** @test */
    public function it_sorts_flags_by_severity_and_confidence(): void
    {
        $builder = new DefenseReportBuilder();

        $flagMedium = new DefenseFlag(
            tactic: 'medium',
            severity: DefenseFlag::SEVERITY_MEDIUM,
            title: 'Medium',
            description: 'D',
            legalBasis: 'B',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'A',
            confidence: 0.9,
        );

        $flagCritical = new DefenseFlag(
            tactic: 'critical',
            severity: DefenseFlag::SEVERITY_CRITICAL,
            title: 'Critical',
            description: 'D',
            legalBasis: 'B',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'A',
            confidence: 0.5,
        );

        $flagHigh1 = new DefenseFlag(
            tactic: 'high1',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'High 1',
            description: 'D',
            legalBasis: 'B',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'A',
            confidence: 0.6,
        );

        $flagHigh2 = new DefenseFlag(
            tactic: 'high2',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'High 2',
            description: 'D',
            legalBasis: 'B',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'A',
            confidence: 0.9, // Higher confidence
        );

        // Register in random order
        $detector1 = $this->createMockDetector('d1', 'L', [], [$flagMedium]);
        $detector2 = $this->createMockDetector('d2', 'L', [], [$flagCritical]);
        $detector3 = $this->createMockDetector('d3', 'L', [], [$flagHigh1, $flagHigh2]);

        $builder->register($detector1)->register($detector2)->register($detector3);

        $report = $builder->buildReport('case-sort');

        // Should be sorted: critical first, then high (higher confidence first), then medium
        $this->assertEquals(DefenseFlag::SEVERITY_CRITICAL, $report->flags[0]->severity);
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $report->flags[1]->severity);
        $this->assertEquals(0.9, $report->flags[1]->confidence); // Higher confidence first
        $this->assertEquals(DefenseFlag::SEVERITY_HIGH, $report->flags[2]->severity);
        $this->assertEquals(0.6, $report->flags[2]->confidence);
        $this->assertEquals(DefenseFlag::SEVERITY_MEDIUM, $report->flags[3]->severity);
    }

    /** @test */
    public function it_handles_detector_exception_gracefully(): void
    {
        $builder = new DefenseReportBuilder();

        $failingDetector = $this->createMock(DefenseTacticDetectorInterface::class);
        $failingDetector->method('tactic')->willReturn('failing');
        $failingDetector->method('label')->willReturn('Failing Detector');
        $failingDetector->method('requires')->willReturn([]);
        $failingDetector->method('detect')->willThrowException(new \RuntimeException('Test error'));

        $builder->register($failingDetector);

        $report = $builder->buildReport('case-error');

        // Should have an info flag about the error
        $this->assertCount(1, $report->flags);
        $this->assertEquals(DefenseFlag::SEVERITY_INFO, $report->flags[0]->severity);
        $this->assertStringContainsString('Failing Detector', $report->flags[0]->title);
        $this->assertStringContainsString('Test error', $report->flags[0]->description);
    }

    /** @test */
    public function it_persists_flags_to_database(): void
    {
        $builder = new DefenseReportBuilder();

        $flag = new DefenseFlag(
            tactic: 'persist_test',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'Persistent Flag',
            description: 'Should be saved',
            legalBasis: 'Test Law',
            echrBasis: 'Test ECHR',
            evidence: ['key' => 'value'],
            recommendedAction: 'Test action',
            confidence: 0.85,
            metadata: ['extra' => 'data'],
        );

        $detector = $this->createMockDetector('persist_test', 'Persist', [], [$flag]);
        $builder->register($detector);

        $builder->buildReport('case-persist');

        // Check database
        $this->assertDatabaseHas('defense_flags', [
            'case_id' => 'case-persist',
            'tactic' => 'persist_test',
            'severity' => 'high',
            'title' => 'Persistent Flag',
            'legal_basis' => 'Test Law',
            'echr_basis' => 'Test ECHR',
            'confidence' => 0.85,
        ]);
    }

    /** @test */
    public function it_clears_previous_flags_before_building_new_report(): void
    {
        $builder = new DefenseReportBuilder();

        // Insert old flag directly
        DB::table('defense_flags')->insert([
            'case_id' => 'case-clear',
            'tactic' => 'old_flag',
            'severity' => 'low',
            'title' => 'Old Flag',
            'description' => 'Should be removed',
            'legal_basis' => 'Old',
            'echr_basis' => null,
            'evidence' => '[]',
            'recommended_action' => 'Old',
            'confidence' => 0.5,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newFlag = new DefenseFlag(
            tactic: 'new_flag',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'New Flag',
            description: 'Should replace',
            legalBasis: 'New',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'New action',
            confidence: 0.9,
        );

        $detector = $this->createMockDetector('new_flag', 'New', [], [$newFlag]);
        $builder->register($detector);

        $builder->buildReport('case-clear');

        // Old flag should be gone, new flag should exist
        $this->assertDatabaseMissing('defense_flags', [
            'case_id' => 'case-clear',
            'tactic' => 'old_flag',
        ]);

        $this->assertDatabaseHas('defense_flags', [
            'case_id' => 'case-clear',
            'tactic' => 'new_flag',
        ]);
    }

    private function createMockDetector(string $tactic, string $label, array $requires, array $flags = []): DefenseTacticDetectorInterface
    {
        $detector = $this->createMock(DefenseTacticDetectorInterface::class);
        $detector->method('tactic')->willReturn($tactic);
        $detector->method('label')->willReturn($label);
        $detector->method('requires')->willReturn($requires);
        $detector->method('detect')->willReturn($flags);

        return $detector;
    }
}
