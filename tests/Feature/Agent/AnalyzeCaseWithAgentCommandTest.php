<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\DTOs\Agent\AgentResult;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for the case:agent-analyze Artisan command.
 *
 * Tests verify the command properly:
 * - Requires a case_id argument
 * - Errors when no documents exist for the case
 * - Uses the specified driver via --driver option
 * - Uses fallback chain when --fallback is specified
 * - Reports success/failure status
 */
class AnalyzeCaseWithAgentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_requires_case_id_argument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

        $this->artisan('case:agent-analyze');
    }

    public function test_command_errors_when_no_documents_exist(): void
    {
        $this->artisan('case:agent-analyze', ['case_id' => 'nonexistent-case-123'])
            ->expectsOutput('No documents for case nonexistent-case-123')
            ->assertExitCode(1);
    }

    public function test_command_uses_default_driver_when_none_specified(): void
    {
        // Create test data
        $case = LegalCase::factory()->create(['id' => 'test-case-001']);
        $doc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'filename' => 'test-document.pdf',
        ]);

        // Create the test file
        $testFilePath = storage_path('app/case-documents/test-document.pdf');
        if (!is_dir(dirname($testFilePath))) {
            mkdir(dirname($testFilePath), 0755, true);
        }
        file_put_contents($testFilePath, 'test content');

        // Mock the driver
        $mockDriver = Mockery::mock(AgentInterface::class);
        $mockDriver->shouldReceive('driver')->andReturn('claude');
        $mockDriver->shouldReceive('name')->andReturn('Claude Code');
        $mockDriver->shouldReceive('isAvailable')->andReturn(true);
        $mockDriver->shouldReceive('run')->andReturn(new AgentResult(
            success: true,
            driver: 'claude',
            results: ['analysis' => 'test'],
            rawOutput: '{"analysis": "test"}',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 1.5,
            sessionId: 'test-session',
            outputFile: '/tmp/output.json',
            metadata: [],
        ));

        // Mock the manager
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('driver')
            ->with(null)
            ->andReturn($mockDriver);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', ['case_id' => 'test-case-001'])
            ->expectsOutputToContain('Driver: Claude Code')
            ->assertExitCode(0);

        // Cleanup
        @unlink($testFilePath);
    }

    public function test_command_uses_specified_driver_via_option(): void
    {
        // Create test data
        $case = LegalCase::factory()->create(['id' => 'test-case-002']);
        $doc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'filename' => 'test-document-2.pdf',
        ]);

        // Create the test file
        $testFilePath = storage_path('app/case-documents/test-document-2.pdf');
        if (!is_dir(dirname($testFilePath))) {
            mkdir(dirname($testFilePath), 0755, true);
        }
        file_put_contents($testFilePath, 'test content');

        // Mock the gemini driver
        $mockDriver = Mockery::mock(AgentInterface::class);
        $mockDriver->shouldReceive('driver')->andReturn('gemini');
        $mockDriver->shouldReceive('name')->andReturn('Gemini CLI');
        $mockDriver->shouldReceive('isAvailable')->andReturn(true);
        $mockDriver->shouldReceive('run')->andReturn(new AgentResult(
            success: true,
            driver: 'gemini',
            results: ['analysis' => 'gemini test'],
            rawOutput: '{"analysis": "gemini test"}',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 2.0,
            sessionId: 'test-session-2',
            outputFile: '/tmp/output2.json',
            metadata: [],
        ));

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('driver')
            ->with('gemini')
            ->andReturn($mockDriver);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', [
            'case_id' => 'test-case-002',
            '--driver' => 'gemini',
        ])
            ->expectsOutputToContain('Gemini CLI')
            ->assertExitCode(0);

        // Cleanup
        @unlink($testFilePath);
    }

    public function test_command_errors_when_driver_unavailable(): void
    {
        // Create test data
        $case = LegalCase::factory()->create(['id' => 'test-case-003']);
        $doc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'filename' => 'test-document-3.pdf',
        ]);

        // Create the test file
        $testFilePath = storage_path('app/case-documents/test-document-3.pdf');
        if (!is_dir(dirname($testFilePath))) {
            mkdir(dirname($testFilePath), 0755, true);
        }
        file_put_contents($testFilePath, 'test content');

        // Mock unavailable driver
        $mockDriver = Mockery::mock(AgentInterface::class);
        $mockDriver->shouldReceive('driver')->andReturn('claude');
        $mockDriver->shouldReceive('name')->andReturn('Claude Code');
        $mockDriver->shouldReceive('isAvailable')->andReturn(false);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('driver')
            ->with(null)
            ->andReturn($mockDriver);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', ['case_id' => 'test-case-003'])
            ->expectsOutputToContain('binary not found')
            ->assertExitCode(1);

        // Cleanup
        @unlink($testFilePath);
    }

    public function test_command_uses_fallback_chain_with_option(): void
    {
        // Create test data
        $case = LegalCase::factory()->create(['id' => 'test-case-004']);
        $doc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'filename' => 'test-document-4.pdf',
        ]);

        // Create the test file
        $testFilePath = storage_path('app/case-documents/test-document-4.pdf');
        if (!is_dir(dirname($testFilePath))) {
            mkdir(dirname($testFilePath), 0755, true);
        }
        file_put_contents($testFilePath, 'test content');

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('runWithFallback')
            ->once()
            ->andReturn(new AgentResult(
                success: true,
                driver: 'gemini', // Fallback succeeded with gemini
                results: ['analysis' => 'fallback test'],
                rawOutput: '{"analysis": "fallback test"}',
                rawError: '',
                exitCode: 0,
                elapsedSeconds: 3.0,
                sessionId: 'test-session-4',
                outputFile: '/tmp/output4.json',
                metadata: [],
            ));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', [
            'case_id' => 'test-case-004',
            '--fallback' => true,
        ])
            ->expectsOutputToContain('fallback chain')
            ->assertExitCode(0);

        // Cleanup
        @unlink($testFilePath);
    }

    public function test_command_reports_failure_on_agent_error(): void
    {
        // Create test data
        $case = LegalCase::factory()->create(['id' => 'test-case-005']);
        $doc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'filename' => 'test-document-5.pdf',
        ]);

        // Create the test file
        $testFilePath = storage_path('app/case-documents/test-document-5.pdf');
        if (!is_dir(dirname($testFilePath))) {
            mkdir(dirname($testFilePath), 0755, true);
        }
        file_put_contents($testFilePath, 'test content');

        // Mock failing driver
        $mockDriver = Mockery::mock(AgentInterface::class);
        $mockDriver->shouldReceive('driver')->andReturn('claude');
        $mockDriver->shouldReceive('name')->andReturn('Claude Code');
        $mockDriver->shouldReceive('isAvailable')->andReturn(true);
        $mockDriver->shouldReceive('run')->andReturn(new AgentResult(
            success: false,
            driver: 'claude',
            results: null,
            rawOutput: '',
            rawError: 'API rate limit exceeded',
            exitCode: 1,
            elapsedSeconds: 0.5,
            sessionId: 'test-session-5',
            outputFile: null,
            metadata: [],
        ));

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('driver')
            ->with(null)
            ->andReturn($mockDriver);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', ['case_id' => 'test-case-005'])
            ->assertExitCode(1);

        // Cleanup
        @unlink($testFilePath);
    }

    public function test_command_shows_file_count(): void
    {
        // Create test data with multiple documents
        $case = LegalCase::factory()->create(['id' => 'test-case-006']);
        CaseDocument::factory()->count(3)->create([
            'case_id' => $case->id,
            'filename' => fn() => 'doc-' . uniqid() . '.pdf',
        ]);

        // Create test files
        $docs = CaseDocument::where('case_id', 'test-case-006')->get();
        $testFiles = [];
        foreach ($docs as $doc) {
            $testFilePath = storage_path("app/case-documents/{$doc->filename}");
            if (!is_dir(dirname($testFilePath))) {
                mkdir(dirname($testFilePath), 0755, true);
            }
            file_put_contents($testFilePath, 'test content');
            $testFiles[] = $testFilePath;
        }

        $mockDriver = Mockery::mock(AgentInterface::class);
        $mockDriver->shouldReceive('driver')->andReturn('claude');
        $mockDriver->shouldReceive('name')->andReturn('Claude Code');
        $mockDriver->shouldReceive('isAvailable')->andReturn(true);
        $mockDriver->shouldReceive('run')->andReturn(new AgentResult(
            success: true,
            driver: 'claude',
            results: ['analysis' => 'test'],
            rawOutput: '{}',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 1.0,
            sessionId: 'test-session-6',
            outputFile: null,
            metadata: [],
        ));

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('driver')->andReturn($mockDriver);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('case:agent-analyze', ['case_id' => 'test-case-006'])
            ->expectsOutputToContain('Files: 3 accessible')
            ->assertExitCode(0);

        // Cleanup
        foreach ($testFiles as $file) {
            @unlink($file);
        }
    }
}
