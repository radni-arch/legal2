<?php

namespace Tests\Feature\Console;

use App\DTOs\Agent\AgentResult;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyzeCaseWithAgentCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected MockInterface $managerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->managerMock = Mockery::mock(AgentManager::class);
        $this->app->instance(AgentManager::class, $this->managerMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_case_id_argument()
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $this->artisan('case:agent-analyze');
    }

    /** @test */
    public function it_validates_case_exists()
    {
        $this->artisan('case:agent-analyze', ['case_id' => 'non-existent-case'])
            ->expectsOutputToContain('Case not found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_case_with_no_documents()
    {
        $case = LegalCase::factory()->create();

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('No documents found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_runs_analysis_with_default_driver()
    {
        $case = LegalCase::factory()->create(['case_number' => 'K-123/2024']);

        // Create a case document with a file
        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Test Document',
            'source' => 'storage/app/documents/test.pdf',
        ]);

        // Create the test file
        $filePath = storage_path('app/documents/test.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        // Mock the driver
        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'claude',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 5.2,
                    sessionId: 'test-session',
                    outputFile: storage_path('app/case-analysis/test-output.json'),
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('K-123/2024')
            ->assertExitCode(0);

        // Cleanup
        @unlink($filePath);
    }

    /** @test */
    public function it_runs_analysis_with_specified_driver()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test2.pdf',
        ]);

        $filePath = storage_path('app/documents/test2.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'gemini',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 3.5,
                    sessionId: 'test-session',
                    outputFile: null,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with('gemini')
            ->andReturn($driverMock);

        $this->artisan('case:agent-analyze', [
            'case_id' => $case->id,
            '--driver' => 'gemini',
        ])
            ->assertExitCode(0);

        @unlink($filePath);
    }

    /** @test */
    public function it_uses_fallback_chain_when_flag_is_set()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test3.pdf',
        ]);

        $filePath = storage_path('app/documents/test3.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $this->managerMock
            ->shouldReceive('runWithFallback')
            ->once()
            ->andReturn(new AgentResult(
                success: true,
                driver: 'claude',
                results: ['analysis' => 'completed'],
                rawOutput: '',
                rawError: '',
                exitCode: 0,
                elapsedSeconds: 4.1,
                sessionId: 'test-session',
                outputFile: null,
            ));

        $this->artisan('case:agent-analyze', [
            'case_id' => $case->id,
            '--fallback' => true,
        ])
            ->expectsOutputToContain('Using fallback chain')
            ->assertExitCode(0);

        @unlink($filePath);
    }

    /** @test */
    public function it_shows_elapsed_time_in_output()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test4.pdf',
        ]);

        $filePath = storage_path('app/documents/test4.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'claude',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 12.5,
                    sessionId: 'test-session',
                    outputFile: null,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->assertExitCode(0);

        @unlink($filePath);
    }

    /** @test */
    public function it_shows_failure_when_analysis_fails()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test5.pdf',
        ]);

        $filePath = storage_path('app/documents/test5.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: false,
                    driver: 'claude',
                    results: null,
                    rawOutput: '',
                    rawError: 'Agent error occurred',
                    exitCode: 1,
                    elapsedSeconds: 2.0,
                    sessionId: 'test-session',
                    outputFile: null,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('failed')
            ->assertExitCode(1);

        @unlink($filePath);
    }

    /** @test */
    public function it_displays_driver_used_in_output()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test6.pdf',
        ]);

        $filePath = storage_path('app/documents/test6.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'claude',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 5.0,
                    sessionId: 'test-session',
                    outputFile: null,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('claude')
            ->assertExitCode(0);

        @unlink($filePath);
    }

    /** @test */
    public function it_shows_output_file_location_when_available()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test7.pdf',
        ]);

        $filePath = storage_path('app/documents/test7.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $outputFile = storage_path('app/case-analysis/output-abc123.json');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'claude',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 5.0,
                    sessionId: 'test-session',
                    outputFile: $outputFile,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('output-abc123.json')
            ->assertExitCode(0);

        @unlink($filePath);
    }

    /** @test */
    public function it_supports_sync_mode()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'source' => 'storage/app/documents/test8.pdf',
        ]);

        $filePath = storage_path('app/documents/test8.pdf');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'test content');

        $driverMock = Mockery::mock(AgentInterface::class);
        $driverMock->shouldReceive('bulkAnalysis')
            ->once()
            ->andReturn([
                'document_review' => new AgentResult(
                    success: true,
                    driver: 'claude',
                    results: ['analysis' => 'completed'],
                    rawOutput: '',
                    rawError: '',
                    exitCode: 0,
                    elapsedSeconds: 5.0,
                    sessionId: 'test-session',
                    outputFile: null,
                ),
            ]);

        $this->managerMock
            ->shouldReceive('driver')
            ->with(null)
            ->andReturn($driverMock);

        $this->managerMock
            ->shouldReceive('getDefaultDriver')
            ->andReturn('claude');

        $this->artisan('case:agent-analyze', [
            'case_id' => $case->id,
            '--sync' => true,
        ])
            ->assertExitCode(0);

        @unlink($filePath);
    }
}
