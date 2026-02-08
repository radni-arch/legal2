<?php

namespace Tests\Feature\Console;

use App\Console\Commands\AnalyzeCourtDecisionsBatch;
use App\Models\ApiKey;
use App\Services\ApiRotator\ApiKeyRotatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class AnalyzeCourtDecisionsBatchWithRotatorTest extends TestCase
{
    use RefreshDatabase;

    private string $testInputFile;
    private string $testOutputDir;
    private string $testPdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test directories
        $this->testOutputDir = storage_path('app/test_court_output');
        $this->testInputFile = storage_path('app/test_court_links.txt');
        $this->testPdfPath = storage_path('app/test_court_output/pdf_cache/test-doc-123.pdf');

        File::ensureDirectoryExists(dirname($this->testPdfPath));
        File::ensureDirectoryExists($this->testOutputDir);
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if (File::isDirectory($this->testOutputDir)) {
            File::deleteDirectory($this->testOutputDir);
        }
        if (File::exists($this->testInputFile)) {
            File::delete($this->testInputFile);
        }

        parent::tearDown();
    }

    // =================================================================
    // Integration Tests: ApiKeyRotatorService Injection
    // =================================================================

    public function test_command_displays_no_keys_error_when_rotator_reports_none_available(): void
    {
        // Create empty input file for minimal test
        File::put($this->testInputFile, "https://example.com/doc.pdf\n");

        // Mock the rotator to return no available keys
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([]);
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn(null);

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        // Run command (not dry-run, should check for keys)
        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
        ])
            ->assertFailed()
            ->expectsOutput('No API keys available. Add keys with: php artisan apikey:manage add');
    }

    public function test_command_fails_when_no_pdf_capable_keys_available(): void
    {
        // Create empty input file
        File::put($this->testInputFile, "https://example.com/doc.pdf\n");

        // Mock: keys exist but none support PDF
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'Key 1', 'available' => true],
            ]);
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn(null); // No PDF-capable key

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
        ])
            ->assertFailed()
            ->expectsOutput('No PDF-capable keys available. Ensure keys have supports_pdf=true.');
    }

    public function test_command_displays_available_key_count_from_rotator(): void
    {
        // Create empty input file
        File::put($this->testInputFile, "https://example.com/doc.pdf\n");

        // Mock the rotator to return 2 available keys
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'Key 1', 'available' => true],
                ['id' => 2, 'name' => 'Key 2', 'available' => true],
                ['id' => 3, 'name' => 'Key 3', 'available' => false], // In cooldown
            ]);
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn(null); // Dry-run doesn't check this

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        // Run command with dry-run (won't call analyzeDocument)
        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('Available API keys: 2')
            ->assertSuccessful();
    }

    public function test_command_skips_key_check_for_dry_run(): void
    {
        // Create input file
        File::put($this->testInputFile, "https://example.com/doc.pdf\n");

        // Mock the rotator - should still be called for status but not fail on empty
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([]); // No keys available
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn(null); // Dry-run doesn't check this

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        // Dry-run should work even without keys
        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('Available API keys: 0')
            ->assertSuccessful();
    }

    public function test_command_calls_rotator_analyze_document_for_each_pdf(): void
    {
        // Create input file with two documents - using simple UUIDs for doc IDs
        File::put($this->testInputFile, implode("\n", [
            "https://example.com/doc.pdf?id=aaa-111-bbb",
            "https://example.com/doc.pdf?id=ccc-222-ddd",
        ]));

        // Create fake cached PDFs at the correct location
        $pdfCacheDir = $this->testOutputDir . '/pdf_cache';
        if (!File::isDirectory($pdfCacheDir)) {
            File::makeDirectory($pdfCacheDir, 0755, true);
        }
        $pdfContent = '%PDF-1.4 fake pdf content';
        file_put_contents($pdfCacheDir . '/aaa-111-bbb.pdf', $pdfContent);
        file_put_contents($pdfCacheDir . '/ccc-222-ddd.pdf', $pdfContent);

        // Verify files exist before running command
        $this->assertTrue(file_exists($pdfCacheDir . '/aaa-111-bbb.pdf'), 'Test PDF 1 should exist');
        $this->assertTrue(file_exists($pdfCacheDir . '/ccc-222-ddd.pdf'), 'Test PDF 2 should exist');

        // Create a real API key for getAvailableKey response
        $pdfKey = ApiKey::factory()->create([
            'name' => 'Test Key',
            'provider' => 'gemini',
            'is_active' => true,
            'supports_pdf' => true,
        ]);

        // Mock the rotator
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'Key 1', 'available' => true],
            ]);
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn($pdfKey);

        // Expect analyzeDocument to be called twice (once per PDF)
        $mockRotator->shouldReceive('analyzeDocument')
            ->twice()
            ->withArgs(function ($pdfPath, $systemPrompt, $userPrompt, $taskType, $documentId, $batchId) {
                return str_ends_with($pdfPath, '.pdf')
                    && $taskType === 'pdf'
                    && !empty($systemPrompt)
                    && !empty($userPrompt);
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    'content' => '{"case_code": "test"}',
                    'usage' => ['total_tokens' => 100],
                ],
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
            ]);

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
        ])
            ->assertSuccessful();
    }

    public function test_command_handles_rotator_failure_gracefully(): void
    {
        // Create input file
        File::put($this->testInputFile, "https://example.com/doc.pdf?id=fff-999-eee\n");

        // Create fake cached PDF at the correct location
        $pdfCacheDir = $this->testOutputDir . '/pdf_cache';
        if (!File::isDirectory($pdfCacheDir)) {
            File::makeDirectory($pdfCacheDir, 0755, true);
        }
        file_put_contents($pdfCacheDir . '/fff-999-eee.pdf', '%PDF-1.4 fake pdf');

        // Verify file exists before running command
        $this->assertTrue(file_exists($pdfCacheDir . '/fff-999-eee.pdf'), 'Test PDF should exist');

        // Create a real API key for getAvailableKey response
        $pdfKey = ApiKey::factory()->create([
            'name' => 'Test Key',
            'provider' => 'gemini',
            'is_active' => true,
            'supports_pdf' => true,
        ]);

        // Mock the rotator
        $mockRotator = Mockery::mock(ApiKeyRotatorService::class);
        $mockRotator->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'Key 1', 'available' => true],
            ]);
        $mockRotator->shouldReceive('getAvailableKey')
            ->with('pdf')
            ->andReturn($pdfKey);

        // Simulate failure
        $mockRotator->shouldReceive('analyzeDocument')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'All keys exhausted',
                'attempts' => 3,
            ]);

        $this->app->instance(ApiKeyRotatorService::class, $mockRotator);

        // Command should complete but report failure
        $this->artisan('court:analyze-batch', [
            '--input' => $this->testInputFile,
            '--output' => $this->testOutputDir,
            '--skip-download' => true,
        ])
            ->assertFailed(); // Should fail due to analysis failure
    }
}
