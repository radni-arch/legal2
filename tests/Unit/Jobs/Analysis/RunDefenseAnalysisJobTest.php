<?php

namespace Tests\Unit\Jobs\Analysis;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use App\Jobs\Analysis\RunDefenseAnalysisJob;
use App\Services\Defense\DefenseReportBuilder;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RunDefenseAnalysisJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated_with_case_id(): void
    {
        $job = new RunDefenseAnalysisJob('case-123');

        $this->assertEquals('case-123', $job->caseId);
    }

    /** @test */
    public function it_uses_analysis_queue(): void
    {
        $job = new RunDefenseAnalysisJob('case-123');

        $this->assertEquals('analysis', $job->queue());
    }

    /** @test */
    public function it_has_retry_configuration(): void
    {
        $job = new RunDefenseAnalysisJob('case-123');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new RunDefenseAnalysisJob('case-456');

        $tags = $job->tags();

        $this->assertContains('defense-analysis', $tags);
        $this->assertContains('case:case-456', $tags);
    }

    /** @test */
    public function it_calls_defense_report_builder(): void
    {
        $job = new RunDefenseAnalysisJob('case-789');

        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->once()
            ->with('case-789')
            ->andReturn(new DefenseReport(
                caseId: 'case-789',
                flags: [],
                generatedAt: now(),
                processingTime: 1.0,
                detectorsRun: ['zastara'],
            ));

        $job->handle($mockBuilder);
    }

    /** @test */
    public function it_logs_completion_with_statistics(): void
    {
        Log::shouldReceive('info')
            ->twice();

        $job = new RunDefenseAnalysisJob('case-log-test');

        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->andReturn(new DefenseReport(
                caseId: 'case-log-test',
                flags: [
                    new DefenseFlag(
                        tactic: 'zastara',
                        severity: DefenseFlag::SEVERITY_CRITICAL,
                        title: 'Test',
                        description: 'Test',
                        legalBasis: 'Test',
                        echrBasis: null,
                        evidence: [],
                        recommendedAction: 'Test',
                        confidence: 0.9,
                    ),
                    new DefenseFlag(
                        tactic: 'chain_of_custody',
                        severity: DefenseFlag::SEVERITY_HIGH,
                        title: 'Test',
                        description: 'Test',
                        legalBasis: 'Test',
                        echrBasis: null,
                        evidence: [],
                        recommendedAction: 'Test',
                        confidence: 0.8,
                    ),
                ],
                generatedAt: now(),
                processingTime: 2.5,
                detectorsRun: ['zastara', 'chain_of_custody'],
            ));

        $job->handle($mockBuilder);
    }

    /** @test */
    public function it_rethrows_exceptions_after_logging(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Failed for case/'), Mockery::any());

        $job = new RunDefenseAnalysisJob('case-error');

        $mockBuilder = Mockery::mock(DefenseReportBuilder::class);
        $mockBuilder->shouldReceive('buildReport')
            ->andThrow(new \RuntimeException('Test error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $job->handle($mockBuilder);
    }

    /** @test */
    public function it_can_be_serialized(): void
    {
        $job = new RunDefenseAnalysisJob('case-serialize');

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals('case-serialize', $unserialized->caseId);
    }
}
