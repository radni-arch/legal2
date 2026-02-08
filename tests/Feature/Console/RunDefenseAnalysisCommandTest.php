<?php

namespace Tests\Feature\Console;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use App\Services\Defense\DefenseReportBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class RunDefenseAnalysisCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_runs_defense_analysis_on_case(): void
    {
        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->with('case-123')
            ->andReturn(new DefenseReport(
                caseId: 'case-123',
                flags: [],
                generatedAt: now(),
                processingTime: 1.5,
                detectorsRun: ['zastara', 'chain_of_custody'],
            ));

        $this->app->instance(DefenseReportBuilder::class, $mockBuilder);

        $this->artisan('case:defense', ['case_id' => 'case-123'])
            ->assertSuccessful()
            ->expectsOutputToContain('Running defense analysis for case case-123')
            ->expectsOutputToContain('Analysis completed');
    }

    /** @test */
    public function it_outputs_json_when_flag_is_set(): void
    {
        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->andReturn(new DefenseReport(
                caseId: 'case-456',
                flags: [
                    new DefenseFlag(
                        tactic: 'zastara',
                        severity: DefenseFlag::SEVERITY_CRITICAL,
                        title: 'Test Flag',
                        description: 'Test description',
                        legalBasis: 'cl. 81. KZ',
                        echrBasis: null,
                        evidence: [],
                        recommendedAction: 'Test action',
                        confidence: 0.9,
                    ),
                ],
                generatedAt: now(),
                processingTime: 2.0,
                detectorsRun: ['zastara'],
            ));

        $this->app->instance(DefenseReportBuilder::class, $mockBuilder);

        // Just verify successful execution with --json flag
        $this->artisan('case:defense', ['case_id' => 'case-456', '--json' => true])
            ->assertSuccessful();
    }

    /** @test */
    public function it_filters_by_tactic_when_option_provided(): void
    {
        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->andReturn(new DefenseReport(
                caseId: 'case-789',
                flags: [
                    new DefenseFlag(
                        tactic: 'zastara',
                        severity: DefenseFlag::SEVERITY_HIGH,
                        title: 'Zastara Flag',
                        description: 'Test',
                        legalBasis: 'cl. 81. KZ',
                        echrBasis: null,
                        evidence: [],
                        recommendedAction: 'Test',
                        confidence: 0.8,
                    ),
                    new DefenseFlag(
                        tactic: 'chain_of_custody',
                        severity: DefenseFlag::SEVERITY_MEDIUM,
                        title: 'Chain Flag',
                        description: 'Test',
                        legalBasis: 'cl. 250. ZKP',
                        echrBasis: null,
                        evidence: [],
                        recommendedAction: 'Test',
                        confidence: 0.7,
                    ),
                ],
                generatedAt: now(),
                processingTime: 1.0,
                detectorsRun: ['zastara', 'chain_of_custody'],
            ));

        $this->app->instance(DefenseReportBuilder::class, $mockBuilder);

        $this->artisan('case:defense', ['case_id' => 'case-789', '--tactic' => 'zastara'])
            ->assertSuccessful()
            ->expectsOutputToContain('Filtered to tactic: zastara')
            ->expectsOutputToContain('Zastara Flag');
    }

    /** @test */
    public function it_displays_flags_with_severity_indicators(): void
    {
        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->andReturn(new DefenseReport(
                caseId: 'case-test',
                flags: [
                    new DefenseFlag(
                        tactic: 'zastara',
                        severity: DefenseFlag::SEVERITY_CRITICAL,
                        title: 'APSOLUTNA ZASTARA',
                        description: 'Critical issue',
                        legalBasis: 'cl. 81. KZ',
                        echrBasis: 'Test v. Croatia',
                        evidence: ['date' => '2020-01-01'],
                        recommendedAction: 'File motion',
                        confidence: 0.95,
                    ),
                ],
                generatedAt: now(),
                processingTime: 0.5,
                detectorsRun: ['zastara'],
            ));

        $this->app->instance(DefenseReportBuilder::class, $mockBuilder);

        $this->artisan('case:defense', ['case_id' => 'case-test'])
            ->assertSuccessful()
            ->expectsOutputToContain('CRITICAL: 1')
            ->expectsOutputToContain('APSOLUTNA ZASTARA')
            ->expectsOutputToContain('Pravni temelj: cl. 81. KZ')
            ->expectsOutputToContain('ECHR: Test v. Croatia')
            ->expectsOutputToContain('Pouzdanost: 95%');
    }

    /** @test */
    public function it_handles_empty_results(): void
    {
        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->andReturn(new DefenseReport(
                caseId: 'clean-case',
                flags: [],
                generatedAt: now(),
                processingTime: 0.1,
                detectorsRun: ['zastara', 'chain_of_custody'],
            ));

        $this->app->instance(DefenseReportBuilder::class, $mockBuilder);

        $this->artisan('case:defense', ['case_id' => 'clean-case'])
            ->assertSuccessful()
            ->expectsOutputToContain('Analysis completed');
    }
}
