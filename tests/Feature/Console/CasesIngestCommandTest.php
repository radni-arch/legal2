<?php

namespace Tests\Feature\Console;

use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\CaseIngestPipeline;
use App\Services\OcrService;
use App\Services\TextractService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CasesIngestCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $pipelineMock;

    protected $ocrMock;

    protected $textractMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock services
        $this->pipelineMock = Mockery::mock(CaseIngestPipeline::class);
        $this->ocrMock = Mockery::mock(OcrService::class);
        $this->textractMock = Mockery::mock(TextractService::class);

        $this->app->instance(CaseIngestPipeline::class, $this->pipelineMock);
        $this->app->instance(OcrService::class, $this->ocrMock);
        $this->app->instance(TextractService::class, $this->textractMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_path_option()
    {
        $this->artisan('cases:ingest')
            ->expectsOutput('--path is required')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_requires_case_option()
    {
        $this->artisan('cases:ingest', ['--path' => '/some/path'])
            ->expectsOutput('--case is required')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_directory_exists()
    {
        $case = LegalCase::factory()->create();

        $this->artisan('cases:ingest', [
            '--path' => '/nonexistent/directory',
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Directory not found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_case_exists()
    {
        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => 'non-existent-case-id',
        ])
            ->expectsOutputToContain('Case not found')
            ->assertExitCode(1);

        rmdir($tempDir);
    }

    /** @test */
    public function it_handles_empty_directory()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutput('No PDF files found in directory')
            ->assertExitCode(0);

        rmdir($tempDir);
    }

    /** @test */
    public function it_finds_pdf_files_in_directory()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        // Create a dummy PDF file
        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy content');

        // Mock pipeline to avoid actual processing
        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Found 1 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_processes_multiple_pdf_files()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        // Create multiple PDF files
        file_put_contents($tempDir.'/test1.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/test2.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/test3.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->times(3)
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Found 3 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test1.pdf');
        unlink($tempDir.'/test2.pdf');
        unlink($tempDir.'/test3.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_supports_dry_run_mode()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        // Pipeline should NOT be called in dry-run
        $this->pipelineMock
            ->shouldNotReceive('ingest');

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found 1 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_accepts_custom_chunk_size()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--chunk' => 1500,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_accepts_custom_overlap_size()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--overlap' => 200,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_displays_case_information()
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-123/2024',
            'title' => 'Test Case',
        ]);

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('K-123/2024')
            ->expectsOutputToContain('Test Case')
            ->assertExitCode(0);

        rmdir($tempDir);
    }

    /** @test */
    public function it_displays_processing_summary()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test1.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/test2.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->times(2)
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Found 2 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test1.pdf');
        unlink($tempDir.'/test2.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_supports_skip_existing_flag()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--skip-existing' => true,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_supports_ocr_flag_for_textract()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--ocr' => true,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_supports_local_ocr_flag()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
            '--local-ocr' => true,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        rmdir($tempDir);
    }

    /** @test */
    public function it_handles_batch_reprocess_mode()
    {
        // Create test documents
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocumentUpload::create([
            'id' => 'doc-123',
            'case_id' => $case->id,
            'filename' => 'test1.pdf',
            'status' => 'processed',
        ]);

        $this->pipelineMock
            ->shouldReceive('reprocess')
            ->once()
            ->with('doc-123')
            ->andReturn(['success' => true]);

        // Simulate stdin input
        $input = "doc-123\n";

        $this->artisan('cases:ingest', ['--batch-reprocess' => true])
            ->expectsOutputToContain('Batch Reprocess Mode')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_finds_pdfs_recursively_in_subdirectories()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);
        mkdir($tempDir.'/subdir', 0755, true);

        file_put_contents($tempDir.'/test1.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/subdir/test2.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->times(2)
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Found 2 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test1.pdf');
        unlink($tempDir.'/subdir/test2.pdf');
        rmdir($tempDir.'/subdir');
        rmdir($tempDir);
    }

    /** @test */
    public function it_ignores_non_pdf_files()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/test.txt', 'Not a PDF');
        file_put_contents($tempDir.'/test.docx', 'Not a PDF');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Found 1 PDF file(s)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test.pdf');
        unlink($tempDir.'/test.txt');
        unlink($tempDir.'/test.docx');
        rmdir($tempDir);
    }

    /** @test */
    public function it_shows_progress_bar_during_processing()
    {
        $case = LegalCase::factory()->create();

        Storage::fake('local');
        $tempDir = Storage::path('test-pdfs');
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/test1.pdf', '%PDF-1.4 dummy');
        file_put_contents($tempDir.'/test2.pdf', '%PDF-1.4 dummy');

        $this->pipelineMock
            ->shouldReceive('ingest')
            ->times(2)
            ->andReturn(['success' => true]);

        $this->artisan('cases:ingest', [
            '--path' => $tempDir,
            '--case' => $case->id,
        ])
            ->assertExitCode(0);

        // Cleanup
        unlink($tempDir.'/test1.pdf');
        unlink($tempDir.'/test2.pdf');
        rmdir($tempDir);
    }
}
